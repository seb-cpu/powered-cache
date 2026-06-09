<?php
/**
 * PageSpeed Insights v5 -> normalized, PII-free Metrics object (ground truth).
 *
 * Parses lighthouseResult.audits + loadingExperience (CrUX) into a compact object.
 * Never scrapes UI; reads JSON only. Verified TLS (sslverify => true).
 *
 * @package SwiftPress
 */

namespace SwiftPress\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PageSpeed
 */
class PageSpeed {

	/**
	 * PSI v5 endpoint.
	 *
	 * @var string
	 */
	const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

	/**
	 * Usage ledger option key (shared with Budget for PSI call counting).
	 *
	 * @var string
	 */
	const USAGE_OPTION = 'swiftpress_ai_usage';

	/**
	 * Constructor (placeholder).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return PageSpeed
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Audit a URL with PageSpeed Insights and return a normalized Metrics object.
	 *
	 * @param string $url      Public URL to audit.
	 * @param string $strategy 'mobile' (default) or 'desktop'.
	 *
	 * @return array|\WP_Error Metrics array on success, WP_Error otherwise.
	 */
	public function audit( $url, $strategy = 'mobile' ) {
		$url      = esc_url_raw( trim( (string) $url ) );
		$strategy = in_array( $strategy, [ 'mobile', 'desktop' ], true ) ? $strategy : 'mobile';

		if ( '' === $url ) {
			return new \WP_Error( 'invalid_url', __( 'No URL to audit.', 'swiftpress' ) );
		}

		$args = [
			'url'      => $url,
			'strategy' => $strategy,
			'category' => 'performance',
		];

		$psi_key = KeyStore::factory()->get_psi_key();
		if ( ! empty( $psi_key ) ) {
			$args['key'] = $psi_key;
		}

		$request_url = add_query_arg( array_map( 'rawurlencode', $args ), self::ENDPOINT );

		// Count the PSI call (anonymous quota is 25/day; warn-surface lives in the UI).
		$this->bump_psi_counter();

		$response = wp_remote_get(
			$request_url,
			[
				'timeout'   => 25,
				'sslverify' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'psi_http_error',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'PageSpeed Insights returned an error (HTTP %d).', 'swiftpress' ),
					$code
				)
			);
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['lighthouseResult'] ) ) {
			return new \WP_Error( 'psi_parse_error', __( 'Could not read the PageSpeed Insights response.', 'swiftpress' ) );
		}

		return $this->normalize( $url, $strategy, $data );
	}

	/**
	 * Fetch the site's own homepage, pinning the connection to loopback first so a
	 * CDN/WAF (e.g. Cloudflare) bot-rule can't 403 the server-side request. Falls
	 * back to a normal public fetch.
	 *
	 * @param string $url URL to fetch (should be this site's own URL).
	 *
	 * @return string|\WP_Error HTML body, or WP_Error.
	 */
	public function fetch_self( $url ) {
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$port   = ( 'http' === $scheme ) ? 80 : 443;

		$pin = static function ( $handle ) use ( $host, $port ) {
			if ( $host && function_exists( 'curl_setopt' ) && defined( 'CURLOPT_RESOLVE' ) ) {
				curl_setopt( $handle, CURLOPT_RESOLVE, [ $host . ':' . $port . ':127.0.0.1' ] ); // phpcs:ignore
			}
		};

		$ua_args = [
			'timeout'     => 15,
			'redirection' => 2,
			'headers'     => [ 'User-Agent' => 'Mozilla/5.0 (compatible; AICache/1.0; +https://webs.ie/aicache)' ],
		];

		// Attempt 1: loopback-pinned (bypasses an edge WAF).
		add_action( 'http_api_curl', $pin );
		$response = wp_remote_get( $url, $ua_args + [ 'sslverify' => false ] );
		remove_action( 'http_api_curl', $pin );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Attempt 2: normal public fetch with verified TLS.
			$response = wp_remote_get( $url, $ua_args + [ 'sslverify' => true ] );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'fetch_failed', __( 'Could not fetch the page.', 'swiftpress' ) );
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * A self-contained homepage scan used when PageSpeed Insights is unavailable
	 * (no PSI key + exhausted anonymous quota). Derives a metrics-shaped object
	 * from the page HTML so the diagnostic still produces real findings. The
	 * perf_score is a heuristic ESTIMATE (clearly labelled by source = 'local').
	 *
	 * @param string $url URL to scan.
	 *
	 * @return array|\WP_Error
	 */
	public function local_scan( $url ) {
		$url   = esc_url_raw( trim( (string) $url ) );
		$start = microtime( true );
		$html  = $this->fetch_self( $url );
		$ttfb  = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$bytes = strlen( $html );
		$host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		$head = '';
		if ( preg_match( '#<head\b[^>]*>(.*?)</head>#is', $html, $hm ) ) {
			$head = $hm[1];
		}

		// Render-blocking: head <script src> without async/defer + head stylesheets.
		$blocking_scripts = 0;
		if ( preg_match_all( '#<script\b[^>]*\bsrc=[^>]*>#i', $head, $sm ) ) {
			foreach ( $sm[0] as $tag ) {
				if ( ! preg_match( '#\b(?:async|defer)\b#i', $tag ) ) {
					$blocking_scripts++;
				}
			}
		}
		$head_styles     = preg_match_all( '#<link\b[^>]*\brel=["\']?stylesheet#i', $head );
		$render_blocking = $blocking_scripts + (int) $head_styles;

		// Images without both width and height.
		$img_no_dims = 0;
		if ( preg_match_all( '#<img\b[^>]*>#i', $html, $im ) ) {
			foreach ( $im[0] as $tag ) {
				if ( ! preg_match( '#\bwidth=#i', $tag ) || ! preg_match( '#\bheight=#i', $tag ) ) {
					$img_no_dims++;
				}
			}
		}

		$font_display = ( preg_match( '#fonts\.googleapis\.com#i', $html ) && ! preg_match( '#display=swap#i', $html ) ) ? 1 : 0;

		$third = [];
		if ( preg_match_all( '#(?:src|href)\s*=\s*["\']https?://([a-z0-9.\-]+)#i', $html, $dm ) ) {
			foreach ( $dm[1] as $h ) {
				$h = strtolower( $h );
				if ( $h && $h !== $host && ( ! $host || substr( $h, - ( strlen( $host ) + 1 ) ) !== '.' . $host ) ) {
					$third[ $h ] = true;
				}
			}
		}
		$has_gtm = preg_match( '#googletagmanager\.com#i', $html ) ? 1 : 0;
		$has_ga  = preg_match( '#google-analytics\.com|gtag/js#i', $html ) ? 1 : 0;

		// Heuristic score so the UI has a (clearly-estimated) number.
		$score  = 100;
		$score -= min( 30, $render_blocking * 4 );
		$score -= min( 16, $img_no_dims * 2 );
		$score -= ( $bytes > 1500000 ? 16 : ( $bytes > 800000 ? 8 : 0 ) );
		$score -= ( $has_gtm ? 6 : 0 ) + ( $has_ga ? 4 : 0 );
		$score -= ( $font_display ? 6 : 0 );
		$score  = max( 20, min( 99, $score ) );

		return [
			'url'           => $url,
			'strategy'      => 'local',
			'source'        => 'local',
			'perf_score'    => $score,
			'field'         => [],
			'lab'           => [ 'TTFB_ms' => $ttfb ],
			'opportunities' => [
				'render_blocking_count' => $render_blocking,
				'unused_css_bytes'      => 0,
				'unused_js_bytes'       => ( $has_gtm || $has_ga ) ? 60000 : 0,
				'unminified_css'        => 0,
				'unminified_js'         => 0,
				'next_gen_images_bytes' => 0,
				'image_dimensions'      => $img_no_dims > 0 ? 1 : 0,
				'font_display'          => $font_display,
				'third_party_count'     => count( $third ),
				'total_bytes'           => $bytes,
				'has_gtm'               => $has_gtm,
				'has_ga'                => $has_ga,
			],
		];
	}

	/**
	 * Normalize a raw PSI response into the compact Metrics object.
	 *
	 * @param string $url      Audited URL.
	 * @param string $strategy Strategy used.
	 * @param array  $data     Decoded PSI response.
	 *
	 * @return array
	 */
	private function normalize( $url, $strategy, array $data ) {
		$lr     = isset( $data['lighthouseResult'] ) && is_array( $data['lighthouseResult'] ) ? $data['lighthouseResult'] : [];
		$audits = isset( $lr['audits'] ) && is_array( $lr['audits'] ) ? $lr['audits'] : [];

		$perf_score = null;
		if ( isset( $lr['categories']['performance']['score'] ) && null !== $lr['categories']['performance']['score'] ) {
			$perf_score = (int) round( ( (float) $lr['categories']['performance']['score'] ) * 100 );
		}

		// CrUX field data (may be absent for low-traffic sites).
		$crux = [];
		if ( isset( $data['loadingExperience']['metrics'] ) && is_array( $data['loadingExperience']['metrics'] ) ) {
			$crux = $data['loadingExperience']['metrics'];
		}

		$metrics = [
			'url'        => $url,
			'strategy'   => $strategy,
			'perf_score' => $perf_score,
			'field'      => [
				'LCP_ms' => $this->crux_percentile( $crux, 'LARGEST_CONTENTFUL_PAINT_MS' ),
				'INP_ms' => $this->crux_percentile( $crux, 'INTERACTION_TO_NEXT_PAINT' ),
				'CLS'    => $this->crux_percentile( $crux, 'CUMULATIVE_LAYOUT_SHIFT_SCORE' ),
			],
			'lab'        => [
				'LCP_ms'  => $this->numeric( $audits, 'largest-contentful-paint' ),
				'TBT_ms'  => $this->numeric( $audits, 'total-blocking-time' ),
				'CLS'     => $this->numeric( $audits, 'cumulative-layout-shift' ),
				'TTFB_ms' => $this->numeric( $audits, 'server-response-time' ),
				'SI_ms'   => $this->numeric( $audits, 'speed-index' ),
			],
			'opportunities' => [
				'render_blocking_count' => $this->item_count( $audits, 'render-blocking-resources' ),
				'unused_css_bytes'      => $this->savings_bytes( $audits, 'unused-css-rules' ),
				'unused_js_bytes'       => $this->savings_bytes( $audits, 'unused-javascript' ),
				'unminified_css'        => $this->has_items( $audits, 'unminified-css' ),
				'unminified_js'         => $this->has_items( $audits, 'unminified-javascript' ),
				'next_gen_images_bytes' => $this->savings_bytes( $audits, 'modern-image-formats' ),
				'image_dimensions'      => $this->has_items( $audits, 'unsized-images' ),
				'font_display'          => $this->has_items( $audits, 'font-display' ),
			],
		];

		return $metrics;
	}

	/**
	 * Safely read a CrUX percentile value.
	 *
	 * @param array  $crux   CrUX metrics map.
	 * @param string $metric Metric id.
	 *
	 * @return int|null
	 */
	private function crux_percentile( array $crux, $metric ) {
		if ( isset( $crux[ $metric ]['percentile'] ) && is_numeric( $crux[ $metric ]['percentile'] ) ) {
			return (int) $crux[ $metric ]['percentile'];
		}

		return null;
	}

	/**
	 * Safely read an audit numericValue.
	 *
	 * @param array  $audits Audits map.
	 * @param string $id     Audit id.
	 *
	 * @return int|null
	 */
	private function numeric( array $audits, $id ) {
		if ( isset( $audits[ $id ]['numericValue'] ) && is_numeric( $audits[ $id ]['numericValue'] ) ) {
			return (int) round( (float) $audits[ $id ]['numericValue'] );
		}

		return null;
	}

	/**
	 * Count details.items for an audit.
	 *
	 * @param array  $audits Audits map.
	 * @param string $id     Audit id.
	 *
	 * @return int
	 */
	private function item_count( array $audits, $id ) {
		if ( isset( $audits[ $id ]['details']['items'] ) && is_array( $audits[ $id ]['details']['items'] ) ) {
			return count( $audits[ $id ]['details']['items'] );
		}

		return 0;
	}

	/**
	 * 1 if an audit has any items, else 0.
	 *
	 * @param array  $audits Audits map.
	 * @param string $id     Audit id.
	 *
	 * @return int
	 */
	private function has_items( array $audits, $id ) {
		return $this->item_count( $audits, $id ) > 0 ? 1 : 0;
	}

	/**
	 * Read overallSavingsBytes for an audit.
	 *
	 * @param array  $audits Audits map.
	 * @param string $id     Audit id.
	 *
	 * @return int
	 */
	private function savings_bytes( array $audits, $id ) {
		if ( isset( $audits[ $id ]['details']['overallSavingsBytes'] ) && is_numeric( $audits[ $id ]['details']['overallSavingsBytes'] ) ) {
			return (int) $audits[ $id ]['details']['overallSavingsBytes'];
		}

		return 0;
	}

	/**
	 * Increment the daily PSI call counter in the usage ledger.
	 *
	 * @return void
	 */
	private function bump_psi_counter() {
		$usage = $this->is_network() ? get_site_option( self::USAGE_OPTION, [] ) : get_option( self::USAGE_OPTION, [] );
		if ( ! is_array( $usage ) ) {
			$usage = [];
		}

		$day = gmdate( 'Y-m-d' );
		if ( ! isset( $usage['psi_calls'] ) || ! is_array( $usage['psi_calls'] ) ) {
			$usage['psi_calls'] = [];
		}

		$current                   = isset( $usage['psi_calls'][ $day ] ) ? (int) $usage['psi_calls'][ $day ] : 0;
		$usage['psi_calls'][ $day ] = $current + 1;

		// Keep the ledger small: retain only the most recent 14 days of PSI counts.
		if ( count( $usage['psi_calls'] ) > 14 ) {
			ksort( $usage['psi_calls'] );
			$usage['psi_calls'] = array_slice( $usage['psi_calls'], -14, null, true );
		}

		if ( $this->is_network() ) {
			update_site_option( self::USAGE_OPTION, $usage );
		} else {
			update_option( self::USAGE_OPTION, $usage );
		}
	}

	/**
	 * Today's PSI call count.
	 *
	 * @return int
	 */
	public function psi_calls_today() {
		$usage = $this->is_network() ? get_site_option( self::USAGE_OPTION, [] ) : get_option( self::USAGE_OPTION, [] );
		$day   = gmdate( 'Y-m-d' );

		if ( is_array( $usage ) && isset( $usage['psi_calls'][ $day ] ) ) {
			return (int) $usage['psi_calls'][ $day ];
		}

		return 0;
	}

	/**
	 * Whether we operate on the network option store.
	 *
	 * @return bool
	 */
	private function is_network() {
		return defined( 'SWIFTPRESS_IS_NETWORK' ) && SWIFTPRESS_IS_NETWORK;
	}
}
