<?php
/**
 * Font optimization: self-host Google Fonts, preload, font-display swap
 *
 * @package SwiftPress
 * @since   3.8
 */

namespace SwiftPress;

use function SwiftPress\Utils\get_cache_dir;
use function SwiftPress\Utils\remove_dir;
use function SwiftPress\Utils\log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FontOptimizer
 *
 * Detects Google Fonts enqueued by themes/plugins, downloads and self-hosts
 * the font files, rewrites style references, adds preload hints for
 * above-the-fold fonts, injects font-display: swap, and strips external
 * dns-prefetch / preconnect hints for Google Fonts domains.
 *
 * @since 3.8
 */
class FontOptimizer {

	/**
	 * Plugin settings
	 *
	 * @var array
	 */
	private $settings = [];

	/**
	 * Font cache base directory (wp-content/cache/swiftpress/fonts/)
	 *
	 * @var string
	 */
	private $fonts_cache_dir = '';

	/**
	 * Font cache base URL
	 *
	 * @var string
	 */
	private $fonts_cache_url = '';

	/**
	 * Collected local font CSS URLs keyed by original handle
	 *
	 * @var array
	 */
	private $local_css_urls = [];

	/**
	 * Collected font file URLs to preload (absolute URLs)
	 *
	 * @var array
	 */
	private $preload_fonts = [];

	/**
	 * Google Fonts handles that were detected and dequeued
	 *
	 * @var array
	 */
	private $detected_handles = [];

	/**
	 * User-Agent string used to request woff2 from Google Fonts API
	 *
	 * @var string
	 */
	const GOOGLE_FONTS_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

	/**
	 * Google / Bunny font domains to detect
	 *
	 * @var array
	 */
	const FONT_DOMAINS = [
		'fonts.googleapis.com',
		'fonts.bunny.net',
	];

	/**
	 * Hint domains to strip (dns-prefetch / preconnect)
	 *
	 * @var array
	 */
	const HINT_DOMAINS = [
		'fonts.googleapis.com',
		'fonts.gstatic.com',
	];

	/**
	 * Return an instance of the current class (singleton)
	 *
	 * @return FontOptimizer
	 * @since  3.8
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
	 * Setup routine – register hooks when the feature is enabled
	 *
	 * @since 3.8
	 */
	public function setup() {
		$this->settings = \SwiftPress\Utils\get_settings();

		// Master toggle – short-circuit entirely when disabled
		if ( empty( $this->settings['enable_font_optimization'] ) ) {
			return;
		}

		// Don't run in admin, AJAX, cron, or CLI contexts
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		// Build cache paths
		$this->fonts_cache_dir = get_cache_dir() . 'swiftpress/fonts/';
		$this->fonts_cache_url = content_url( '/cache/swiftpress/fonts/' );

		// ── Detection + Download + Rewrite (Req 1-3) ──
		if ( ! empty( $this->settings['self_host_google_fonts'] ) ) {
			// Priority 999: run late, after themes/plugins register styles
			add_action( 'wp_enqueue_scripts', [ $this, 'intercept_google_fonts' ], 999 );

			// Output buffer for inline <link> tags that bypass wp_enqueue
			add_action( 'template_redirect', [ $this, 'start_output_buffer' ] );
		}

		// ── Preload above-the-fold fonts (Req 4) ──
		if ( ! empty( $this->settings['font_preload'] ) ) {
			add_action( 'wp_head', [ $this, 'print_preload_hints' ], 2 );
		}

		// ── font-display: swap in output buffer (Req 5) ──
		if ( ! empty( $this->settings['font_display_swap'] ) ) {
			add_action( 'template_redirect', [ $this, 'start_swap_buffer' ] );
		}

		// ── Cleanup external hints (Req 6) ──
		add_filter( 'wp_resource_hints', [ $this, 'strip_font_resource_hints' ], 10, 2 );

		// ── Cache management (Req 7) ──
		add_action( 'swiftpress_purge_all_cache', [ $this, 'purge_font_cache' ] );
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 1 – Detection
	// ──────────────────────────────────────────────────────────────

	/**
	 * Scan wp_styles->registered for Google/Bunny Fonts handles,
	 * download + self-host, then dequeue/re-enqueue with local CSS.
	 *
	 * @since 3.8
	 */
	public function intercept_google_fonts() {
		global $wp_styles;

		if ( ! isset( $wp_styles->registered ) ) {
			return;
		}

		foreach ( $wp_styles->registered as $handle => $dep ) {
			if ( empty( $dep->src ) ) {
				continue;
			}

			if ( ! $this->is_google_font_url( $dep->src ) ) {
				continue;
			}

			$local_css_url = $this->get_local_css_url( $dep->src );

			if ( ! $local_css_url ) {
				continue;
			}

			// Dequeue and re-enqueue with the local CSS (Req 3)
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
			wp_enqueue_style( $handle, $local_css_url, [], null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion

			$this->detected_handles[] = $handle;
			$this->local_css_urls[ $handle ] = $local_css_url;
		}
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 2 – Download & Self-Host
	// ──────────────────────────────────────────────────────────────

	/**
	 * Given a remote Google Fonts CSS URL, return the local CSS URL.
	 * Downloads and caches fonts + CSS if not already cached.
	 *
	 * @param string $remote_css_url Google Fonts CSS URL
	 *
	 * @return string|false Local CSS URL on success, false on failure
	 * @since 3.8
	 */
	private function get_local_css_url( $remote_css_url ) {
		// Normalise protocol-relative URLs
		$normalised = $this->normalise_url( $remote_css_url );

		// Sub-directory based on URL hash for cache-busting
		$url_hash  = md5( $normalised );
		$cache_dir = $this->fonts_cache_dir . $url_hash . '/';
		$cache_url = $this->fonts_cache_url . $url_hash . '/';
		$css_file  = $cache_dir . 'fonts.css';
		$css_url   = $cache_url . 'fonts.css';

		// Already cached (Req 7 – only re-download when cache cleared)
		if ( file_exists( $css_file ) ) {
			$this->collect_preload_fonts( $css_file, $cache_url );

			return $css_url;
		}

		// Fetch remote CSS with woff2 user-agent
		$remote_css = $this->fetch_remote_css( $normalised );

		if ( empty( $remote_css ) ) {
			return false;
		}

		// Parse @font-face blocks and download font files
		$local_css = $this->process_font_faces( $remote_css, $cache_dir, $cache_url );

		if ( empty( $local_css ) ) {
			return false;
		}

		// Write local CSS
		if ( ! wp_mkdir_p( $cache_dir ) ) {
			log( sprintf( 'FontOptimizer: could not create directory %s', $cache_dir ) );

			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $css_file, $local_css ) ) {
			log( sprintf( 'FontOptimizer: could not write %s', $css_file ) );

			return false;
		}

		$this->collect_preload_fonts( $css_file, $cache_url );

		return $css_url;
	}

	/**
	 * Fetch Google Fonts CSS from the remote URL with a Chrome UA so
	 * the API returns woff2 @font-face declarations.
	 *
	 * @param string $url Fully qualified CSS URL
	 *
	 * @return string Raw CSS or empty string on failure
	 * @since 3.8
	 */
	private function fetch_remote_css( $url ) {
		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 15,
				'user-agent' => self::GOOGLE_FONTS_UA,
			]
		);

		if ( is_wp_error( $response ) ) {
			log( sprintf( 'FontOptimizer: failed to fetch %s – %s', $url, $response->get_error_message() ) );

			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			log( sprintf( 'FontOptimizer: HTTP %d for %s', $code, $url ) );

			return '';
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Parse @font-face blocks from remote CSS, download each font file,
	 * rewrite src URLs to local paths, and inject font-display: swap.
	 *
	 * @param string $css       Remote CSS content
	 * @param string $cache_dir Local cache directory (absolute path)
	 * @param string $cache_url Local cache URL
	 *
	 * @return string Rewritten CSS with local URLs and font-display: swap
	 * @since 3.8
	 */
	private function process_font_faces( $css, $cache_dir, $cache_url ) {
		if ( ! wp_mkdir_p( $cache_dir ) ) {
			return '';
		}

		// Match all @font-face blocks
		$pattern = '/@font-face\s*\{([^}]+)\}/si';

		$local_css = preg_replace_callback(
			$pattern,
			function ( $match ) use ( $cache_dir, $cache_url ) {
				$block = $match[1];

				// Download every url() font file in this block
				$block = preg_replace_callback(
					'/url\(\s*[\'"]?(https?:\/\/[^\s\'")]+)[\'"]?\s*\)/i',
					function ( $url_match ) use ( $cache_dir, $cache_url ) {
						$remote_font_url = $url_match[1];
						$filename        = $this->font_filename( $remote_font_url );
						$local_path      = $cache_dir . $filename;
						$local_url       = $cache_url . $filename;

						if ( ! file_exists( $local_path ) ) {
							$this->download_font_file( $remote_font_url, $local_path );
						}

						if ( file_exists( $local_path ) ) {
							return "url('" . $local_url . "')";
						}

						// Fallback: keep original URL if download failed
						return $url_match[0];
					},
					$block
				);

				// Inject font-display: swap (Req 5) if not already present
				if ( ! empty( $this->settings['font_display_swap'] ) && false === stripos( $block, 'font-display' ) ) {
					$block = rtrim( $block );
					// Ensure trailing semicolon before adding property
					if ( substr( $block, -1 ) !== ';' ) {
						$block .= ';';
					}
					$block .= "\n  font-display: swap;";
				}

				return '@font-face {' . $block . '}';
			},
			$css
		);

		return $local_css;
	}

	/**
	 * Download a single font file to the cache directory
	 *
	 * @param string $remote_url Remote font URL
	 * @param string $local_path Target local path
	 *
	 * @since 3.8
	 */
	private function download_font_file( $remote_url, $local_path ) {
		$response = wp_remote_get(
			$remote_url,
			[
				'timeout' => 30,
				'stream'  => true,
				'filename' => $local_path,
			]
		);

		if ( is_wp_error( $response ) ) {
			log( sprintf( 'FontOptimizer: could not download font %s – %s', $remote_url, $response->get_error_message() ) );

			// Clean up partial file
			if ( file_exists( $local_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $local_path );
			}
		}
	}

	/**
	 * Derive a stable filename from a font URL
	 *
	 * @param string $url Font file URL
	 *
	 * @return string Filename like "abc123def.woff2"
	 * @since 3.8
	 */
	private function font_filename( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$ext  = pathinfo( $path, PATHINFO_EXTENSION );

		if ( empty( $ext ) ) {
			$ext = 'woff2';
		}

		return substr( md5( $url ), 0, 12 ) . '.' . $ext;
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 3 – Rewrite (output buffer for inline <link>)
	// ──────────────────────────────────────────────────────────────

	/**
	 * Start output buffer to catch inline <link> tags pointing to Google Fonts
	 * that bypass wp_enqueue.
	 *
	 * @since 3.8
	 */
	public function start_output_buffer() {
		if ( ! empty( $this->settings['self_host_google_fonts'] ) ) {
			ob_start( [ $this, 'rewrite_inline_font_links' ] );
		}
	}

	/**
	 * Replace inline <link> Google Fonts URLs with local CSS URLs
	 *
	 * @param string $html Output buffer
	 *
	 * @return string Modified HTML
	 * @since 3.8
	 */
	public function rewrite_inline_font_links( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}

		// Match <link> tags pointing to Google/Bunny Fonts
		$pattern = '/<link\b[^>]*href=["\']([^"\']*(?:fonts\.googleapis\.com|fonts\.bunny\.net)[^"\']*)["\'][^>]*>/i';

		$html = preg_replace_callback(
			$pattern,
			function ( $match ) {
				$full_tag   = $match[0];
				$remote_url = $match[1];

				// Skip if this handle was already rewritten via wp_enqueue
				foreach ( $this->local_css_urls as $local_url ) {
					if ( false !== strpos( $full_tag, $local_url ) ) {
						return $full_tag;
					}
				}

				$local_css_url = $this->get_local_css_url( $remote_url );

				if ( ! $local_css_url ) {
					return $full_tag;
				}

				return str_replace( $remote_url, $local_css_url, $full_tag );
			},
			$html
		);

		return $html;
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 4 – Preload above-the-fold fonts
	// ──────────────────────────────────────────────────────────────

	/**
	 * Print <link rel="preload"> hints for above-the-fold font files
	 *
	 * @since 3.8
	 */
	public function print_preload_hints() {
		// Collect fonts if not yet populated (handles may not be intercepted
		// yet at wp_head priority 2, but cached CSS files exist on disk)
		if ( empty( $this->preload_fonts ) ) {
			$this->collect_all_cached_preload_fonts();
		}

		/**
		 * Filters how many font files to preload.
		 *
		 * @hook   swiftpress_font_preload_count
		 *
		 * @param  {int} $count Number of fonts to preload. Default 2.
		 *
		 * @return {int} New value.
		 * @since  3.8
		 */
		$count = apply_filters( 'swiftpress_font_preload_count', 2 );
		$count = max( 0, intval( $count ) );

		$fonts = array_unique( $this->preload_fonts );
		$fonts = array_slice( $fonts, 0, $count );

		foreach ( $fonts as $font_url ) {
			$type = $this->get_font_type( $font_url );
			printf(
				'<link rel="preload" href="%s" as="font" type="%s" crossorigin="anonymous">' . "\n",
				esc_url( $font_url ),
				esc_attr( $type )
			);
		}
	}

	/**
	 * Collect preload font URLs from a local CSS file
	 *
	 * @param string $css_file  Absolute path to local CSS
	 * @param string $cache_url Base URL for this cache sub-directory
	 *
	 * @since 3.8
	 */
	private function collect_preload_fonts( $css_file, $cache_url ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css = file_get_contents( $css_file );

		if ( empty( $css ) ) {
			return;
		}

		// Extract all url() values from @font-face blocks
		if ( preg_match_all( "/url\(\s*['\"]?([^'\")\s]+)['\"]?\s*\)/i", $css, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				// Only collect font file URLs (woff2, woff, ttf, etc.), skip data URIs
				if ( preg_match( '/\.(woff2?|ttf|otf|eot)(\?|$)/i', $url ) && 0 !== strpos( $url, 'data:' ) ) {
					$this->preload_fonts[] = $url;
				}
			}
		}
	}

	/**
	 * Scan all existing font cache sub-directories for preload candidates.
	 * Used by print_preload_hints when called before intercept_google_fonts.
	 *
	 * @since 3.8
	 */
	private function collect_all_cached_preload_fonts() {
		if ( ! is_dir( $this->fonts_cache_dir ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$dirs = @scandir( $this->fonts_cache_dir );

		if ( ! is_array( $dirs ) ) {
			return;
		}

		foreach ( $dirs as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$css_file = $this->fonts_cache_dir . $entry . '/fonts.css';

			if ( file_exists( $css_file ) ) {
				$this->collect_preload_fonts( $css_file, $this->fonts_cache_url . $entry . '/' );
			}
		}
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 5 – font-display: swap (output buffer)
	// ──────────────────────────────────────────────────────────────

	/**
	 * Start output buffer to inject font-display: swap into existing
	 * theme/plugin @font-face declarations that are missing it.
	 *
	 * @since 3.8
	 */
	public function start_swap_buffer() {
		ob_start( [ $this, 'inject_font_display_swap' ] );
	}

	/**
	 * Add font-display: swap to any @font-face in the HTML buffer
	 * that doesn't already have it.
	 *
	 * @param string $html Output buffer
	 *
	 * @return string Modified HTML
	 * @since 3.8
	 */
	public function inject_font_display_swap( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}

		// Only process if there are @font-face declarations
		if ( false === stripos( $html, '@font-face' ) ) {
			return $html;
		}

		$html = preg_replace_callback(
			'/@font-face\s*\{([^}]+)\}/si',
			function ( $match ) {
				$block = $match[1];

				// Already has font-display
				if ( false !== stripos( $block, 'font-display' ) ) {
					return $match[0];
				}

				$block = rtrim( $block );
				if ( substr( $block, -1 ) !== ';' ) {
					$block .= ';';
				}
				$block .= "\n  font-display: swap;";

				return '@font-face {' . $block . '}';
			},
			$html
		);

		return $html;
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 6 – Cleanup external hints
	// ──────────────────────────────────────────────────────────────

	/**
	 * Strip dns-prefetch and preconnect hints for Google Fonts domains.
	 *
	 * @param array  $urls         List of hint URLs.
	 * @param string $relation_type Hint type (dns-prefetch, preconnect, etc.)
	 *
	 * @return array Filtered URLs
	 * @since 3.8
	 */
	public function strip_font_resource_hints( $urls, $relation_type ) {
		if ( ! in_array( $relation_type, [ 'dns-prefetch', 'preconnect' ], true ) ) {
			return $urls;
		}

		return array_filter(
			$urls,
			function ( $url ) {
				$host = '';

				if ( is_array( $url ) && ! empty( $url['href'] ) ) {
					$host = wp_parse_url( $url['href'], PHP_URL_HOST );
				} elseif ( is_string( $url ) ) {
					$host = wp_parse_url( $url, PHP_URL_HOST );
				}

				if ( empty( $host ) ) {
					return true; // keep unknown entries
				}

				foreach ( self::HINT_DOMAINS as $domain ) {
					if ( $host === $domain ) {
						return false; // remove it
					}
				}

				return true;
			}
		);
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 7 – Cache management
	// ──────────────────────────────────────────────────────────────

	/**
	 * Purge the font cache directory.
	 * Hooked to swiftpress_purge_all_cache.
	 *
	 * @since 3.8
	 */
	public function purge_font_cache() {
		$dir = get_cache_dir() . 'swiftpress/fonts/';

		if ( is_dir( $dir ) ) {
			remove_dir( $dir );
			log( 'FontOptimizer: font cache purged' );
		}
	}

	// ──────────────────────────────────────────────────────────────
	//  Helpers
	// ──────────────────────────────────────────────────────────────

	/**
	 * Check whether a URL belongs to a known Google/Bunny Fonts domain
	 *
	 * @param string $url URL to check
	 *
	 * @return bool
	 * @since 3.8
	 */
	private function is_google_font_url( $url ) {
		foreach ( self::FONT_DOMAINS as $domain ) {
			if ( false !== strpos( $url, $domain ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalise a potentially protocol-relative URL to https
	 *
	 * @param string $url URL
	 *
	 * @return string Fully qualified URL
	 * @since 3.8
	 */
	private function normalise_url( $url ) {
		if ( 0 === strpos( $url, '//' ) ) {
			return 'https:' . $url;
		}

		if ( 0 === strpos( $url, 'http://' ) ) {
			return str_replace( 'http://', 'https://', $url );
		}

		return $url;
	}

	/**
	 * Determine MIME type for a font URL
	 *
	 * @param string $url Font URL
	 *
	 * @return string MIME type
	 * @since 3.8
	 */
	private function get_font_type( $url ) {
		if ( false !== strpos( $url, '.woff2' ) ) {
			return 'font/woff2';
		}

		if ( false !== strpos( $url, '.woff' ) ) {
			return 'font/woff';
		}

		if ( false !== strpos( $url, '.ttf' ) ) {
			return 'font/ttf';
		}

		if ( false !== strpos( $url, '.otf' ) ) {
			return 'font/otf';
		}

		// Default to woff2 (Google Fonts API returns woff2 with our UA)
		return 'font/woff2';
	}
}
