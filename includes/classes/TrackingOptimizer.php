<?php
/**
 * Self-host third-party tracking scripts (Google Analytics / Tag Manager and
 * Meta Pixel) from the site's own domain.
 *
 * Why: Google serves gtag.js/gtm.js with a ~15-minute cache lifetime, which
 * trips Lighthouse's "serve static assets with an efficient cache policy"
 * audit, and every tracker adds a third-party DNS + TLS round-trip. Serving a
 * locally cached copy fixes both. Copies refresh twice daily via WP-Cron, and
 * if a refresh fails the last good copy keeps serving (never a broken page).
 *
 * Scope (honest): for a GTM container this localizes the container script
 * itself — tags configured inside the container still load from their own
 * domains. Measurement beacons (collect requests) always go to Google/Meta;
 * that is how analytics works and is unaffected by self-hosting.
 *
 * Rewrites handled in the output buffer:
 *  - <script src="https://www.googletagmanager.com/gtag/js?id=…">  (GA4/gtag)
 *  - https://www.googletagmanager.com/gtm.js?id=GTM-…              (explicit src)
 *  - the inline GTM snippet's string literal '…/gtm.js?id=' + i    (single-container pages)
 *  - https://www.google-analytics.com/analytics.js                 (legacy UA)
 *  - https://connect.facebook.net/<locale>/fbevents.js             (Meta Pixel)
 *
 * @package SwiftPress
 */

namespace SwiftPress;

use function SwiftPress\Utils\get_settings;
use function SwiftPress\Utils\get_cache_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrackingOptimizer
 */
class TrackingOptimizer {

	/**
	 * Cron hook refreshing the cached tracker files.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'swiftpress_tracking_refresh';

	/**
	 * Option storing name => remote URL for every cached file (refresh map).
	 *
	 * @var string
	 */
	const SOURCES_OPTION = 'swiftpress_tracking_sources';

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings = [];

	/**
	 * Per-request cache of name => local URL ('' = unavailable).
	 *
	 * @var array
	 */
	private $resolved = [];

	/**
	 * Constructor (placeholder, mirrors codebase style).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return TrackingOptimizer
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Register hooks when either tracker is enabled.
	 *
	 * @return void
	 */
	public function setup() {
		$this->settings = get_settings();

		$google = ! empty( $this->settings['enable_google_tracking'] );
		$fb     = ! empty( $this->settings['enable_fb_tracking'] );

		if ( ! $google && ! $fb ) {
			if ( wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
			return;
		}

		add_action( self::CRON_HOOK, [ $this, 'refresh_all' ] );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK );
		}

		if ( is_admin() ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'start_buffer' ], 3 );
	}

	/**
	 * Start the rewrite buffer on regular front-end GETs.
	 *
	 * @return void
	 */
	public function start_buffer() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		/**
		 * Filters whether tracking-script self-hosting is disabled for this request.
		 *
		 * @hook  swiftpress_tracking_disable
		 * @since 1.2
		 */
		if ( apply_filters( 'swiftpress_tracking_disable', false ) ) {
			return;
		}

		ob_start( [ $this, 'rewrite' ] );
	}

	/**
	 * Output-buffer callback: swap known tracker URLs for local copies.
	 *
	 * Every swap only happens when the local file actually exists (download
	 * succeeded now or earlier); otherwise the original URL is left untouched,
	 * so a failed download can never break tracking.
	 *
	 * @param string $html Document HTML.
	 *
	 * @return string
	 */
	public function rewrite( $html ) {
		if ( empty( $html ) || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		$map = [];

		if ( ! empty( $this->settings['enable_google_tracking'] ) ) {
			// GA4 / gtag.js (full URL carries the measurement id and sometimes &l=).
			if ( preg_match_all( '#https?://www\.googletagmanager\.com/gtag/js\?[^"\'\s<>\\\\)]+#i', $html, $m ) ) {
				foreach ( array_unique( $m[0] ) as $url ) {
					$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
					wp_parse_str( $query, $q );
					$id = isset( $q['id'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $q['id'] ) : '';
					if ( '' === $id ) {
						continue;
					}
					$local = $this->ensure_local( 'gtag-' . $id . '.js', 'https://www.googletagmanager.com/gtag/js?id=' . $id );
					if ( '' !== $local ) {
						$map[ $url ] = $local;
					}
				}
			}

			// GTM containers — explicit src URLs.
			if ( preg_match_all( '#https?://www\.googletagmanager\.com/gtm\.js\?[^"\'\s<>\\\\)]*id=(GTM-[A-Z0-9]+)[^"\'\s<>\\\\)]*#i', $html, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $hit ) {
					$local = $this->ensure_local( 'gtm-' . strtoupper( $hit[1] ) . '.js', 'https://www.googletagmanager.com/gtm.js?id=' . strtoupper( $hit[1] ) );
					if ( '' !== $local ) {
						$map[ $hit[0] ] = $local;
					}
				}
			}

			// The standard inline GTM snippet builds the URL by concatenation
			// ('…/gtm.js?id=' + i). When the page has exactly one container id we
			// can safely swap the string literal — the id/query appended to a
			// static file is ignored by the server.
			if ( preg_match_all( '/GTM-[A-Z0-9]{4,}/', $html, $gm ) ) {
				$ids = array_unique( $gm[0] );
				if ( 1 === count( $ids ) ) {
					$gtm_id = $ids[0];
					$local  = $this->ensure_local( 'gtm-' . $gtm_id . '.js', 'https://www.googletagmanager.com/gtm.js?id=' . $gtm_id );
					if ( '' !== $local ) {
						foreach ( [ 'https://www.googletagmanager.com/gtm.js?id=', '//www.googletagmanager.com/gtm.js?id=' ] as $literal ) {
							$map[ $literal ] = $local . ( false === strpos( $local, '?' ) ? '?id=' : '&id=' );
						}
					}
				}
			}

			// Legacy Universal Analytics.
			if ( false !== stripos( $html, 'google-analytics.com/analytics.js' ) ) {
				$local = $this->ensure_local( 'analytics.js', 'https://www.google-analytics.com/analytics.js' );
				if ( '' !== $local ) {
					$map['https://www.google-analytics.com/analytics.js'] = $local;
					$map['//www.google-analytics.com/analytics.js']       = $local;
				}
			}
		}

		if ( ! empty( $this->settings['enable_fb_tracking'] ) ) {
			if ( preg_match_all( '#https?://connect\.facebook\.net/([a-zA-Z_]{2,10})/fbevents\.js#i', $html, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $hit ) {
					$local = $this->ensure_local( 'fbevents-' . $hit[1] . '.js', 'https://connect.facebook.net/' . $hit[1] . '/fbevents.js' );
					if ( '' !== $local ) {
						$map[ $hit[0] ] = $local;
					}
				}
			}
		}

		return $map ? strtr( $html, $map ) : $html;
	}

	/**
	 * Make sure a local copy of a tracker exists; return its URL (with a
	 * filemtime version) or '' when no usable copy could be obtained.
	 *
	 * @param string $name   Local file name (sanitized, fixed by the caller).
	 * @param string $remote Canonical remote URL (allow-listed hosts only).
	 *
	 * @return string
	 */
	private function ensure_local( $name, $remote ) {
		if ( isset( $this->resolved[ $name ] ) ) {
			return $this->resolved[ $name ];
		}

		$dir  = get_cache_dir() . 'swiftpress/tracking/';
		$path = $dir . $name;

		// Fresh enough (cron also refreshes twice daily)?
		if ( ! file_exists( $path ) || ( time() - (int) filemtime( $path ) ) > DAY_IN_SECONDS ) {
			$this->download( $remote, $path );
		}

		if ( ! file_exists( $path ) || filesize( $path ) < 512 ) {
			return $this->resolved[ $name ] = '';
		}

		// Remember the source so the cron can refresh it.
		$sources = get_option( self::SOURCES_OPTION, [] );
		if ( ! is_array( $sources ) ) {
			$sources = [];
		}
		if ( ! isset( $sources[ $name ] ) || $sources[ $name ] !== $remote ) {
			$sources[ $name ] = $remote;
			update_option( self::SOURCES_OPTION, $sources, false );
		}

		return $this->resolved[ $name ] = content_url( '/cache/swiftpress/tracking/' . $name ) . '?ver=' . (int) filemtime( $path );
	}

	/**
	 * Download a tracker to disk; keeps the previous copy when the fetch fails.
	 *
	 * @param string $remote Remote URL (allow-listed hosts, built by this class).
	 * @param string $path   Target path.
	 *
	 * @return void
	 */
	private function download( $remote, $path ) {
		// Back off after a failure: without this, a blocked/unreachable tracker
		// host would be retried synchronously (up to 10s) on EVERY uncached
		// pageview. The cron refresh still retries on schedule.
		$backoff_key = 'swiftpress_trk_fail_' . md5( $remote );
		if ( get_transient( $backoff_key ) ) {
			return;
		}

		if ( ! wp_mkdir_p( dirname( $path ) ) ) {
			return;
		}

		$response = wp_remote_get(
			$remote,
			[
				'timeout'            => 10,
				'user-agent'         => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
				'reject_unsafe_urls' => true,
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $backoff_key, 1, 2 * HOUR_IN_SECONDS );
			return;
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) < 512 ) {
			set_transient( $backoff_key, 1, 2 * HOUR_IN_SECONDS );
			return; // Suspiciously small — keep whatever we had.
		}

		// gtag.js used with a Universal Analytics property loads analytics.js
		// internally from google-analytics.com. Localize that nested reference in
		// the body we serve (measurement /collect beacons are left untouched — they
		// are the analytics data itself and must reach Google).
		if ( preg_match( '/^gtag-/', basename( $path ) ) && false !== strpos( $body, 'https://www.google-analytics.com/analytics.js' ) ) {
			$nested = $this->ensure_local( 'analytics.js', 'https://www.google-analytics.com/analytics.js' );
			if ( '' !== $nested ) {
				$body = str_replace( 'https://www.google-analytics.com/analytics.js', $nested, $body );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $path, $body, LOCK_EX );
	}

	/**
	 * Cron: refresh every cached tracker from its recorded source URL.
	 *
	 * @return void
	 */
	public function refresh_all() {
		$sources = get_option( self::SOURCES_OPTION, [] );
		if ( ! is_array( $sources ) ) {
			return;
		}

		$dir = get_cache_dir() . 'swiftpress/tracking/';
		foreach ( $sources as $name => $remote ) {
			// Names only ever come from this class, but be defensive anyway.
			$name = basename( (string) $name );
			if ( '' === $name || ! preg_match( '/\.js$/', $name ) ) {
				continue;
			}
			$this->download( (string) $remote, $dir . $name );
		}
	}
}
