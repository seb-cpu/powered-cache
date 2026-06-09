<?php
/**
 * Image optimization: next-gen (WebP/AVIF) sibling generation for media
 * library images + front-end HTML rewrite to serve them.
 *
 * Design:
 * - On upload (`wp_generate_attachment_metadata`) every JPEG/PNG original and
 *   sub-size gets a sibling encoded in the preferred next-gen format, written
 *   as `<file>.<orig-ext>.webp` (appended extension — collision-free and
 *   trivially reversible). A sibling is kept only when it is actually smaller
 *   than the source.
 * - On the front end an output buffer rewrites <img>/<source> src/srcset
 *   attributes and CSS url() references to the sibling URL when the sibling
 *   exists on disk. No Accept-header negotiation and no <picture> wrapping, so
 *   the markup keeps its exact structure (theme CSS selectors stay valid) and
 *   the rewritten page is safe to full-page-cache and CDN-cache.
 * - Bulk conversion of existing media: `wp swiftpress optimize-images`.
 *
 * AVIF is used only when the preferred format is `avif` AND the PHP GD build
 * supports `imageavif()`; otherwise WebP is used. With neither available the
 * module deactivates itself.
 *
 * @package SwiftPress
 */

namespace SwiftPress;

use function SwiftPress\Utils\get_settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ImageOptimizer
 */
class ImageOptimizer {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings = [];

	/**
	 * Output format extension ('webp' or 'avif', '' = unsupported/disabled).
	 *
	 * @var string
	 */
	private $ext = '';

	/**
	 * Per-request cache of sibling existence checks (URL path => bool).
	 *
	 * @var array
	 */
	private $exists = [];

	/**
	 * Constructor (placeholder, mirrors codebase style).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return ImageOptimizer
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
	 * Register hooks when the feature is enabled and the server can encode.
	 *
	 * @return void
	 */
	public function setup() {
		$this->settings = get_settings();

		if ( empty( $this->settings['enable_image_optimization'] ) ) {
			return;
		}

		$this->ext = $this->output_ext();
		if ( '' === $this->ext ) {
			return; // GD lacks both webp and avif encoders.
		}

		// Generate siblings for new uploads (and metadata regenerations).
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'on_generate_metadata' ], 20, 2 );

		// Clean up siblings when an attachment is deleted.
		add_action( 'delete_attachment', [ $this, 'delete_siblings' ] );

		// Front-end rewrite buffer.
		if ( ! is_admin() ) {
			add_action( 'template_redirect', [ $this, 'start_buffer' ], 2 );
		}
	}

	/**
	 * Whether the module ended up active (toggle on + encoder available).
	 *
	 * @return bool
	 */
	public function is_active() {
		return '' !== $this->ext;
	}

	/**
	 * Decide the output format from settings + server capabilities.
	 *
	 * @return string 'avif', 'webp' or '' when the server can encode neither.
	 */
	private function output_ext() {
		$preferred = isset( $this->settings['image_optimizer_preferred_format'] ) ? (string) $this->settings['image_optimizer_preferred_format'] : '';

		if ( 'avif' === $preferred && function_exists( 'imageavif' ) ) {
			return 'avif';
		}

		if ( function_exists( 'imagewebp' ) ) {
			return 'webp';
		}

		if ( function_exists( 'imageavif' ) ) {
			return 'avif';
		}

		return '';
	}

	// ──────────────────────────────────────────────────────────────
	//  Generation
	// ──────────────────────────────────────────────────────────────

	/**
	 * Hook: generate siblings for a freshly uploaded/regenerated attachment.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 *
	 * @return array Unmodified metadata (filter contract).
	 */
	public function on_generate_metadata( $metadata, $attachment_id ) {
		$this->optimize_attachment( $attachment_id, is_array( $metadata ) ? $metadata : null );

		return $metadata;
	}

	/**
	 * Generate next-gen siblings for one attachment (original + all sizes).
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $metadata      Pre-fetched metadata (optional).
	 *
	 * @return int Number of sibling files created.
	 */
	public function optimize_attachment( $attachment_id, $metadata = null ) {
		if ( ! $this->is_active() ) {
			return 0;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/png' ], true ) ) {
			return 0;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return 0;
		}

		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
		}

		$created = 0;
		$created += $this->convert_file( $file ) ? 1 : 0;

		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$dir = trailingslashit( dirname( $file ) );
			foreach ( $metadata['sizes'] as $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$created += $this->convert_file( $dir . $size['file'] ) ? 1 : 0;
			}
		}

		return $created;
	}

	/**
	 * Encode one JPEG/PNG file into the sibling next-gen file.
	 *
	 * The sibling is kept only when smaller than the source; otherwise it is
	 * discarded so the rewrite never serves a "optimized" file that is larger.
	 *
	 * @param string $file Absolute path to the source image.
	 *
	 * @return bool True when a sibling was (re)created.
	 */
	private function convert_file( $file ) {
		if ( ! file_exists( $file ) || ! preg_match( '/\.(jpe?g|png)$/i', $file, $m ) ) {
			return false;
		}

		$sibling = $file . '.' . $this->ext;

		// Up to date already.
		if ( file_exists( $sibling ) && filemtime( $sibling ) >= filemtime( $file ) ) {
			return false;
		}

		wp_raise_memory_limit( 'image' );

		$is_png = 'png' === strtolower( $m[1] );
		$image  = $is_png ? @imagecreatefrompng( $file ) : @imagecreatefromjpeg( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $image ) {
			return false;
		}

		if ( $is_png ) {
			imagepalettetotruecolor( $image );
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
		}

		/**
		 * Filters the encode quality for next-gen image siblings.
		 *
		 * @hook  swiftpress_image_optimizer_quality
		 * @since 1.1
		 */
		$quality = (int) apply_filters( 'swiftpress_image_optimizer_quality', 'avif' === $this->ext ? 60 : 82, $this->ext, $file );

		$ok = 'avif' === $this->ext ? imageavif( $image, $sibling, $quality ) : imagewebp( $image, $sibling, $quality );
		imagedestroy( $image );

		if ( ! $ok || ! file_exists( $sibling ) ) {
			return false;
		}

		// Only keep genuinely smaller files.
		if ( filesize( $sibling ) >= filesize( $file ) ) {
			@unlink( $sibling ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}

		return true;
	}

	/**
	 * Remove next-gen siblings when an attachment is deleted.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return void
	 */
	public function delete_siblings( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return;
		}

		$paths    = [ $file ];
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$dir = trailingslashit( dirname( $file ) );
			foreach ( $metadata['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$paths[] = $dir . $size['file'];
				}
			}
		}

		foreach ( $paths as $path ) {
			foreach ( [ 'webp', 'avif' ] as $ext ) {
				if ( file_exists( $path . '.' . $ext ) ) {
					@unlink( $path . '.' . $ext ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}
		}
	}

	// ──────────────────────────────────────────────────────────────
	//  Front-end rewrite
	// ──────────────────────────────────────────────────────────────

	/**
	 * Start the rewrite output buffer on regular front-end requests.
	 *
	 * @return void
	 */
	public function start_buffer() {
		if ( is_feed() || is_robots() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		/**
		 * Filters whether the next-gen image rewrite buffer is disabled.
		 *
		 * @hook  swiftpress_image_optimizer_disable_rewrite
		 * @since 1.1
		 */
		if ( apply_filters( 'swiftpress_image_optimizer_disable_rewrite', false ) ) {
			return;
		}

		ob_start( [ $this, 'rewrite_images' ] );
	}

	/**
	 * Rewrite image URLs in the HTML buffer to their next-gen siblings.
	 *
	 * @param string $html Output buffer.
	 *
	 * @return string
	 */
	public function rewrite_images( $html ) {
		if ( empty( $html ) || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		// <img>/<source> attributes (src, srcset and common lazyload variants).
		$html = preg_replace_callback(
			'/<(?:img|source)\b[^>]*>/i',
			function ( $tag_match ) {
				$tag = $tag_match[0];

				if ( false !== stripos( $tag, 'data-no-webp' ) ) {
					return $tag;
				}

				return preg_replace_callback(
					'/\b(src|srcset|data-src|data-srcset|data-lazy-src|data-lazy-srcset)\s*=\s*([\'"])(.*?)\2/is',
					function ( $attr ) {
						$value = false !== stripos( $attr[1], 'srcset' )
							? $this->swap_srcset( $attr[3] )
							: $this->swap_url( $attr[3] );

						return null === $value ? $attr[0] : $attr[1] . '=' . $attr[2] . $value . $attr[2];
					},
					$tag
				);
			},
			$html
		);

		// CSS url(...) references (inline style attributes and <style> blocks).
		$html = preg_replace_callback(
			'/url\(\s*([\'"]?)([^)\'"\s]+\.(?:jpe?g|png)(?:\?[^)\'"]*)?)\1\s*\)/i',
			function ( $m ) {
				$swapped = $this->swap_url( $m[2] );

				return null === $swapped ? $m[0] : 'url(' . $m[1] . $swapped . $m[1] . ')';
			},
			$html
		);

		return $html;
	}

	/**
	 * Swap every candidate inside a srcset value.
	 *
	 * @param string $srcset srcset attribute value.
	 *
	 * @return string|null New value, or null when nothing changed.
	 */
	private function swap_srcset( $srcset ) {
		$changed    = false;
		$candidates = explode( ',', $srcset );

		foreach ( $candidates as $i => $candidate ) {
			$parts = preg_split( '/\s+/', trim( $candidate ), 2 );
			if ( empty( $parts[0] ) ) {
				continue;
			}

			$swapped = $this->swap_url( $parts[0] );
			if ( null !== $swapped ) {
				$parts[0]         = $swapped;
				$candidates[ $i ] = ' ' . implode( ' ', $parts );
				$changed          = true;
			}
		}

		return $changed ? ltrim( implode( ',', $candidates ) ) : null;
	}

	/**
	 * Map one image URL to its next-gen sibling URL when the sibling exists.
	 *
	 * @param string $url Image URL (absolute, protocol-relative or root-relative).
	 *
	 * @return string|null Sibling URL, or null when not applicable.
	 */
	private function swap_url( $url ) {
		$url = trim( $url );

		if ( '' === $url || false !== stripos( $url, '.webp' ) || false !== stripos( $url, '.avif' ) ) {
			return null;
		}

		// Split off any query string; the file check runs on the path only.
		$query = '';
		$qpos  = strpos( $url, '?' );
		if ( false !== $qpos ) {
			$query = substr( $url, $qpos );
			$url   = substr( $url, 0, $qpos );
		}

		if ( ! preg_match( '/\.(jpe?g|png)$/i', $url ) ) {
			return null;
		}

		$rel = $this->content_relative_path( $url );
		if ( null === $rel ) {
			return null;
		}

		$cache_key = $rel;
		if ( ! array_key_exists( $cache_key, $this->exists ) ) {
			$this->exists[ $cache_key ] = file_exists( WP_CONTENT_DIR . $rel . '.' . $this->ext );
		}

		if ( ! $this->exists[ $cache_key ] ) {
			return null;
		}

		return $url . '.' . $this->ext . $query;
	}

	/**
	 * Resolve a same-site wp-content URL to its path relative to WP_CONTENT_DIR.
	 *
	 * @param string $url Image URL without query string.
	 *
	 * @return string|null Leading-slash relative path, or null for foreign URLs.
	 */
	private function content_relative_path( $url ) {
		static $prefixes = null;

		if ( null === $prefixes ) {
			$content  = content_url();                       // https://example.com/wp-content
			$prefixes = [ $content ];

			$swapped = 0 === strpos( $content, 'https://' )
				? 'http://' . substr( $content, 8 )
				: 'https://' . substr( $content, 7 );
			$prefixes[] = $swapped;

			$bare       = preg_replace( '#^https?:#', '', $content ); // //example.com/wp-content
			$prefixes[] = $bare;

			$path       = wp_parse_url( $content, PHP_URL_PATH );     // /wp-content
			$prefixes[] = is_string( $path ) ? $path : '/wp-content';
		}

		foreach ( $prefixes as $prefix ) {
			if ( '' !== $prefix && 0 === strpos( $url, $prefix ) ) {
				$rel = substr( $url, strlen( $prefix ) );

				// Guard against traversal — the rewrite only ever reads
				// beneath WP_CONTENT_DIR.
				if ( false !== strpos( $rel, '..' ) ) {
					return null;
				}

				return '/' === substr( $rel, 0, 1 ) ? $rel : '/' . $rel;
			}
		}

		return null;
	}
}
