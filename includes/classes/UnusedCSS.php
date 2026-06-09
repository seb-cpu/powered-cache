<?php
/**
 * CSS delivery optimization: Critical CSS (safe) and Remove Unused CSS (aggressive).
 *
 * Both modes share one engine that computes the subset of each stylesheet whose
 * selectors actually appear in the server-rendered HTML (plus a generous safelist
 * of dynamic/stateful patterns). What differs is what happens to the originals:
 *
 *  - critical_css (SAFE): the computed "used" CSS is inlined in <head> so the page
 *    paints immediately with no render-blocking request, and the FULL original
 *    stylesheets are then loaded asynchronously (media=print → onload swap, with a
 *    <noscript> fallback). Nothing is permanently removed — if the used-CSS
 *    computation missed a rule, the async full stylesheet fills it in a moment
 *    later. This is forgiving and cannot "break" styling, only briefly delay a
 *    non-critical rule.
 *
 *  - remove_unused_css (AGGRESSIVE): only the used CSS is inlined and the originals
 *    are dropped entirely. Maximum byte savings, but it trusts the static analysis
 *    completely, so classes added by JavaScript that aren't in the safelist would
 *    lose their styles. Off by default; meant to be validated per site.
 *
 * The analysis is static (server HTML + safelist), never a headless browser, so it
 * is fast and dependency-free; the trade-off is the safelist, which must cover
 * classes a theme/plugin toggles with JS.
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
 * Class UnusedCSS
 */
class UnusedCSS {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings = [];

	/**
	 * Max size of reduced CSS to inline in <head>; larger sets are written to a
	 * cached file and linked instead (so the HTML stays light and cacheable).
	 *
	 * @var int
	 */
	const INLINE_MAX = 40000;

	/**
	 * Active mode: '' (off), 'critical' (safe) or 'remove' (aggressive).
	 *
	 * @var string
	 */
	private $mode = '';

	/**
	 * Map of stylesheet handle => absolute file path for internal stylesheets
	 * collected while the document head is printed.
	 *
	 * @var array
	 */
	private $stylesheets = [];

	/**
	 * Constructor (placeholder, mirrors codebase style).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return UnusedCSS
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
	 * Register hooks when a CSS-delivery mode is enabled on a cacheable request.
	 *
	 * @return void
	 */
	public function setup() {
		$this->settings = get_settings();

		if ( ! empty( $this->settings['remove_unused_css'] ) ) {
			$this->mode = 'remove';
		} elseif ( ! empty( $this->settings['critical_css'] ) ) {
			$this->mode = 'critical';
		} else {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		// Collect internal stylesheets as the head prints them (we still need the
		// full rendered HTML for the token set, hence the output buffer below).
		add_filter( 'style_loader_tag', [ $this, 'collect_stylesheet' ], 8, 4 );

		add_action( 'template_redirect', [ $this, 'start_buffer' ], 1 );
	}

	/**
	 * Whether the engine is active (a mode is on).
	 *
	 * @return bool
	 */
	public function is_active() {
		return '' !== $this->mode;
	}

	/**
	 * Record an internal stylesheet's source path (does not alter the tag).
	 *
	 * @param string $tag    Link tag HTML.
	 * @param string $handle Style handle.
	 * @param string $href   Resolved href.
	 * @param string $media  Media attribute.
	 *
	 * @return string Unmodified tag.
	 */
	public function collect_stylesheet( $tag, $handle, $href, $media = 'all' ) {
		// Only screen/all stylesheets — never print/speech, never preloads.
		if ( '' !== (string) $media && ! in_array( (string) $media, [ 'all', 'screen' ], true ) ) {
			return $tag;
		}

		if ( ! Optimizer\Helper::is_internal_url( $href, home_url() ) ) {
			return $tag;
		}

		// Never touch font stylesheets. They are small, render-critical, and their
		// load timing is exactly what the font-display/CLS optimization depends on
		// — deferring or reordering them reintroduces font-swap layout shift.
		if ( preg_match( '#(/fonts?/|font-?face|webfont|swiftpress/fonts)#i', (string) $href ) ) {
			return $tag;
		}

		if ( $this->is_excluded( $href ) ) {
			return $tag;
		}

		$path = Optimizer\Helper::realpath( $href, home_url() );
		if ( $path && is_string( $path ) && file_exists( $path ) && 'css' === strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			$this->stylesheets[ $handle ] = [ 'path' => $path, 'href' => $href ];
		}

		return $tag;
	}

	/**
	 * Start the output buffer that rewrites CSS delivery for the whole document.
	 *
	 * @return void
	 */
	public function start_buffer() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( is_user_logged_in() || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return; // Stay on the cacheable, anonymous path only.
		}

		if ( is_feed() || ( function_exists( 'is_embed' ) && is_embed() ) ) {
			return;
		}

		/**
		 * Filters whether CSS-delivery optimization is disabled for this request.
		 *
		 * @hook  swiftpress_ucss_disable
		 * @since 1.2
		 */
		if ( apply_filters( 'swiftpress_ucss_disable', false ) ) {
			return;
		}

		ob_start( [ $this, 'process' ] );
	}

	/**
	 * Output-buffer callback: inline the used CSS and re-route the originals.
	 *
	 * @param string $html Full document HTML.
	 *
	 * @return string
	 */
	public function process( $html ) {
		if ( empty( $html ) || empty( $this->stylesheets ) ) {
			return $html;
		}

		// Only touch full HTML documents.
		if ( false === stripos( $html, '</head>' ) || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		$tokens = $this->extract_tokens( $html );
		if ( empty( $tokens['tags'] ) ) {
			return $html; // Parsing looked wrong — do nothing rather than risk it.
		}

		$safelist = $this->safelist();

		$used_css   = '';
		$handled    = [];
		$total_in   = 0;
		$total_kept = 0;

		foreach ( $this->stylesheets as $handle => $info ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$css = (string) @file_get_contents( $info['path'] );
			if ( '' === $css ) {
				continue;
			}

			$dir     = trailingslashit( dirname( $info['href'] ) );
			$css     = $this->rebase_urls( $css, $dir );
			$reduced = $this->reduce_css( $css, $tokens, $safelist );

			$total_in   += strlen( $css );
			$total_kept += strlen( $reduced );

			if ( '' !== $reduced ) {
				$used_css .= "\n/* " . $handle . " */\n" . $reduced;
			}
			$handled[ $handle ] = true;
		}

		if ( '' === $used_css ) {
			return $html;
		}

		// Small enough to inline (best for first paint); otherwise serve it as one
		// cached, minifiable file so the HTML doesn't carry hundreds of KB on every
		// page and the browser can still cache the reduced CSS.
		if ( strlen( $used_css ) <= self::INLINE_MAX ) {
			$head_css = '<style id="swiftpress-used-css">' . $used_css . '</style>';
		} else {
			$url = $this->write_cache_file( $used_css );
			$head_css = $url
				? '<link rel="stylesheet" id="swiftpress-used-css" href="' . esc_url( $url ) . '" media="all" />'
				: '<style id="swiftpress-used-css">' . $used_css . '</style>';
		}

		// Re-route every handled stylesheet link.
		$html = $this->rewrite_links( $html, $handled );

		// Inject the used CSS at the START of <head>, before the originals. In
		// critical mode the originals reload asynchronously and sit later in the
		// document, so once they apply they correctly win the cascade and restore
		// the full styling — the used CSS only governs the brief pre-load paint.
		// Appending it at the end instead would invert the cascade and let a
		// dropped-rule's predecessor win permanently.
		if ( preg_match( '#<head\b[^>]*>#i', $html, $hm ) ) {
			$html = preg_replace( '#(<head\b[^>]*>)#i', '$1' . $head_css, $html, 1 );
		} else {
			$html = preg_replace( '#</head>#i', $head_css . '</head>', $html, 1 );
		}

		if ( defined( 'SWIFTPRESS_DEBUG' ) && SWIFTPRESS_DEBUG && $total_in > 0 ) {
			$pct   = (int) round( 100 - ( $total_kept / $total_in * 100 ) );
			$html .= "\n<!-- SwiftPress CSS: mode={$this->mode} kept={$total_kept}/{$total_in} bytes (-{$pct}%) -->";
		}

		return $html;
	}

	/**
	 * Replace the handled <link> stylesheets: drop them (remove mode) or convert
	 * them to async, non-render-blocking loads (critical mode).
	 *
	 * @param string $html    Document HTML.
	 * @param array  $handled handle => true for stylesheets we inlined.
	 *
	 * @return string
	 */
	private function rewrite_links( $html, array $handled ) {
		$hrefs = [];
		foreach ( $this->stylesheets as $handle => $info ) {
			if ( ! empty( $handled[ $handle ] ) ) {
				$hrefs[] = $info['href'];
			}
		}

		return preg_replace_callback(
			'#<link\b[^>]*rel=(["\'])stylesheet\1[^>]*>#i',
			function ( $m ) use ( $hrefs ) {
				$tag = $m[0];

				// Find this tag's href (raw, or the file-optimizer-rewritten one).
				if ( ! preg_match( '#href=(["\'])(.*?)\1#i', $tag, $hm ) ) {
					return $tag;
				}
				$tag_href = $hm[2];

				// Match against the originals we inlined. The printed href may be a
				// file-optimizer concat URL, so also match by the source filename.
				$is_ours = false;
				foreach ( $hrefs as $href ) {
					$base = basename( wp_parse_url( $href, PHP_URL_PATH ) ?: $href );
					if ( $tag_href === $href || ( '' !== $base && false !== strpos( $tag_href, $base ) ) ) {
						$is_ours = true;
						break;
					}
				}
				if ( ! $is_ours ) {
					return $tag;
				}

				if ( 'remove' === $this->mode ) {
					return ''; // Aggressive: the inlined used-CSS replaces it entirely.
				}

				// Safe: load the full stylesheet without blocking render, then apply
				// it (media swap on load), with a no-JS fallback.
				$async = preg_replace( '#\smedia=(["\']).*?\1#i', '', $tag );
				$async = preg_replace( '#<link\b#i', '<link media="print" onload="this.media=\'all\';this.onload=null;"', $async, 1 );

				return $async . '<noscript>' . $tag . '</noscript>';
			},
			$html
		);
	}

	/**
	 * Write the reduced CSS to a content-addressed cache file and return its URL.
	 * Content-addressing (md5 of the body) means the URL changes when the CSS
	 * changes, so a CDN/browser never serves a stale reduced sheet.
	 *
	 * @param string $css Reduced CSS.
	 *
	 * @return string|false Public URL, or false on failure.
	 */
	private function write_cache_file( $css ) {
		$dir = get_cache_dir() . 'swiftpress/ucss/';
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$name = md5( $css ) . '.css';
		$path = $dir . $name;

		if ( ! file_exists( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === @file_put_contents( $path, $css, LOCK_EX ) ) {
				return false;
			}
		}

		return content_url( '/cache/swiftpress/ucss/' . $name );
	}

	/**
	 * Build the token set (class names, ids, tag names) present in the HTML.
	 *
	 * @param string $html Document HTML.
	 *
	 * @return array{classes:array,ids:array,tags:array}
	 */
	private function extract_tokens( $html ) {
		$classes = [];
		$ids     = [];
		$tags    = [];

		if ( preg_match_all( '/class\s*=\s*["\']([^"\']*)["\']/i', $html, $m ) ) {
			foreach ( $m[1] as $list ) {
				foreach ( preg_split( '/\s+/', trim( $list ) ) as $c ) {
					if ( '' !== $c ) {
						$classes[ $c ] = true;
					}
				}
			}
		}

		if ( preg_match_all( '/id\s*=\s*["\']([^"\']+)["\']/i', $html, $m ) ) {
			foreach ( $m[1] as $id ) {
				$id = trim( $id );
				if ( '' !== $id ) {
					$ids[ $id ] = true;
				}
			}
		}

		if ( preg_match_all( '/<([a-zA-Z][a-zA-Z0-9-]*)/', $html, $m ) ) {
			foreach ( $m[1] as $t ) {
				$tags[ strtolower( $t ) ] = true;
			}
		}

		return [ 'classes' => $classes, 'ids' => $ids, 'tags' => $tags ];
	}

	/**
	 * Reduce a stylesheet to the rules whose selectors can match the page.
	 *
	 * @param string $css      Stylesheet body.
	 * @param array  $tokens   Token set from extract_tokens().
	 * @param array  $safelist class/id name => true safelist.
	 *
	 * @return string
	 */
	private function reduce_css( $css, array $tokens, array $safelist ) {
		$css = $this->strip_comments( $css );
		$out = '';

		foreach ( $this->split_blocks( $css ) as $block ) {
			$prelude = trim( $block['prelude'] );

			// At-rules.
			if ( '' !== $prelude && '@' === $prelude[0] ) {
				$at = strtolower( $prelude );

				// Always keep these wholesale — removing them breaks fonts/anim/etc.
				if ( preg_match( '/^@(font-face|keyframes|-webkit-keyframes|-moz-keyframes|charset|import|page|font-feature-values|counter-style|property|namespace|layer)\b/', $at ) ) {
					$out .= $block['raw'];
					continue;
				}

				// Conditional groups: recurse into the inner CSS, keep if non-empty.
				if ( preg_match( '/^@(media|supports|container|layer|document|-moz-document)\b/', $at ) ) {
					if ( null === $block['body'] ) {
						$out .= $block['raw']; // statement at-rule, keep.
						continue;
					}
					$inner = $this->reduce_css( $block['body'], $tokens, $safelist );
					if ( '' !== trim( $inner ) ) {
						$out .= $prelude . '{' . $inner . '}';
					}
					continue;
				}

				// Unknown at-rule: keep to be safe.
				$out .= $block['raw'];
				continue;
			}

			// Plain style rule.
			if ( null === $block['body'] ) {
				continue;
			}

			if ( $this->is_selector_list_used( $prelude, $tokens, $safelist ) ) {
				$out .= $prelude . '{' . $block['body'] . '}';
			}
		}

		return $out;
	}

	/**
	 * Decide whether a comma-separated selector list keeps the rule (any selector
	 * that could match the page keeps it).
	 *
	 * @param string $selectors Selector list.
	 * @param array  $tokens    Token set.
	 * @param array  $safelist  Safelist.
	 *
	 * @return bool
	 */
	private function is_selector_list_used( $selectors, array $tokens, array $safelist ) {
		// :is()/:where()/:matches() with commas are ambiguous to split — keep.
		if ( preg_match( '/:(?:is|where|matches|has)\(/i', $selectors ) ) {
			return true;
		}

		foreach ( explode( ',', $selectors ) as $selector ) {
			if ( $this->is_selector_used( $selector, $tokens, $safelist ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a single selector could match the rendered page.
	 *
	 * A selector is kept when every class and id it references is present in the
	 * page or safelisted. Pure tag / attribute / universal / pseudo selectors are
	 * kept (small and low-risk). JS-applied classes rely on the safelist.
	 *
	 * @param string $selector Single selector.
	 * @param array  $tokens   Token set.
	 * @param array  $safelist Safelist.
	 *
	 * @return bool
	 */
	private function is_selector_used( $selector, array $tokens, array $safelist ) {
		$selector = trim( $selector );
		if ( '' === $selector ) {
			return false;
		}

		// Classes referenced anywhere in the selector (incl. inside :not()).
		if ( preg_match_all( '/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', $selector, $cm ) ) {
			foreach ( $cm[1] as $class ) {
				if ( ! isset( $tokens['classes'][ $class ] ) && ! $this->safelisted( $class, $safelist ) ) {
					return false;
				}
			}
		}

		// Ids referenced anywhere in the selector.
		if ( preg_match_all( '/#(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', $selector, $im ) ) {
			foreach ( $im[1] as $id ) {
				if ( ! isset( $tokens['ids'][ $id ] ) && ! $this->safelisted( $id, $safelist ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Whether a class/id name matches the safelist (exact, or `prefix*` wildcard).
	 *
	 * @param string $name     class/id name.
	 * @param array  $safelist Safelist map (exact => true) + ['_prefixes' => []].
	 *
	 * @return bool
	 */
	private function safelisted( $name, array $safelist ) {
		if ( isset( $safelist[ $name ] ) ) {
			return true;
		}
		foreach ( $safelist['_prefixes'] as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the safelist: a generous default of dynamic/stateful patterns common
	 * to WordPress themes, page builders and JS libraries, plus the user list.
	 *
	 * @return array{0:array,_prefixes:array} exact map + prefix list.
	 */
	private function safelist() {
		// Safelist ONLY classes a theme/plugin/library adds with JavaScript at
		// runtime (so they aren't in the server HTML token set). Structural classes
		// that page builders render server-side (vc_row, elementor-*, et_pb_* …) are
		// deliberately NOT here — they appear in the tokens on the pages that use
		// them and must be removable on the pages that don't (that is the whole win
		// of removing a builder's CSS from a page that doesn't use the builder).
		$exact    = [];
		$prefixes = [
			// WP core block/runtime state.
			'wp-', 'has-', 'is-', 'admin-bar', 'screen-reader', 'menu-item-has-children',
			// Generic interaction state toggled by JS.
			'active', 'current-', 'selected', 'open', 'opened', 'show', 'shown', 'showing',
			'hide', 'hidden', 'collapse', 'collapsed', 'collapsing', 'expand', 'expanded',
			'toggled', 'toggle-', 'sticky', 'stuck', 'scrolled', 'loading', 'loaded',
			'in-view', 'visible', 'fade', 'fadein', 'fadeout', 'slidein', 'slideout',
			// Sliders / carousels (classes injected on init).
			'slick', 'swiper', 'owl', 'flickity', 'splide', 'glide', 'tns-', 'slide-',
			// Lightboxes / modals / popups (injected on open).
			'lightbox', 'fancybox', 'magnific', 'mfp-', 'modal', 'overlay', 'popup', 'pum-', 'mejs-',
			// Lazyload / scroll animation (injected on load/scroll).
			'lazy', 'lazyload', 'lazyloaded', 'animate', 'animated', 'aos', 'wow', 'reveal', 'sp-reveal',
			// Icon fonts (used by content/JS, often absent from a given page's HTML).
			'fa-', 'fas', 'far', 'fab', 'fal', 'fad', 'dashicons', 'genericon',
			// Consent / cart (injected/updated by JS).
			'cky-', 'cmplz', 'cookie', 'cart', 'added_to_cart', 'wc-block',
		];

		$user = (string) ( $this->settings['ucss_safelist'] ?? '' );
		foreach ( preg_split( '/[\s,]+/', $user ) as $entry ) {
			$entry = trim( ltrim( $entry, '.#' ) );
			if ( '' === $entry ) {
				continue;
			}
			if ( '*' === substr( $entry, -1 ) ) {
				$prefixes[] = rtrim( $entry, '*' );
			} else {
				$exact[ $entry ] = true;
			}
		}

		$exact['_prefixes'] = array_values( array_unique( $prefixes ) );

		return $exact;
	}

	/**
	 * Whether a stylesheet href is excluded from optimization by the user.
	 *
	 * @param string $href Stylesheet URL.
	 *
	 * @return bool
	 */
	private function is_excluded( $href ) {
		$lists = trim( (string) ( $this->settings['ucss_excluded_files'] ?? '' ) . "\n" . (string) ( $this->settings['excluded_css_files'] ?? '' ) );
		if ( '' === $lists ) {
			return false;
		}
		foreach ( preg_split( '/[\r\n]+/', $lists ) as $needle ) {
			$needle = trim( $needle );
			if ( '' !== $needle && false !== strpos( $href, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Rewrite relative url() references to absolute paths based on the
	 * stylesheet's own directory (the inlined CSS no longer lives at that path).
	 *
	 * @param string $css CSS body.
	 * @param string $dir Stylesheet directory URL (with trailing slash).
	 *
	 * @return string
	 */
	private function rebase_urls( $css, $dir ) {
		return preg_replace_callback(
			'/url\(\s*([\'"]?)([^)\'"]+)\1\s*\)/i',
			function ( $m ) use ( $dir ) {
				$url = trim( $m[2] );
				if ( '' === $url || 0 === strpos( $url, 'data:' ) || 0 === strpos( $url, 'http' ) || 0 === strpos( $url, '//' ) || 0 === strpos( $url, '/' ) || 0 === strpos( $url, '#' ) ) {
					return $m[0];
				}

				return 'url(' . $m[1] . $dir . $url . $m[1] . ')';
			},
			$css
		);
	}

	/**
	 * Strip CSS comments (keeping none — they don't affect rendering).
	 *
	 * @param string $css CSS.
	 *
	 * @return string
	 */
	private function strip_comments( $css ) {
		return preg_replace( '#/\*.*?\*/#s', '', $css );
	}

	/**
	 * Split CSS into top-level blocks, brace-aware so nested @media bodies stay
	 * intact. Each block is {prelude, body|null, raw}; body is null for statement
	 * at-rules like `@import …;`.
	 *
	 * @param string $css CSS (comments already stripped).
	 *
	 * @return array<int,array{prelude:string,body:?string,raw:string}>
	 */
	private function split_blocks( $css ) {
		$blocks  = [];
		$len     = strlen( $css );
		$i       = 0;
		$prelude = '';

		while ( $i < $len ) {
			$ch = $css[ $i ];

			if ( ';' === $ch && '' !== trim( $prelude ) && '@' === ltrim( $prelude )[0] ) {
				// Statement at-rule (e.g. @import …;).
				$blocks[] = [ 'prelude' => trim( $prelude ), 'body' => null, 'raw' => $prelude . ';' ];
				$prelude  = '';
				$i++;
				continue;
			}

			if ( '{' === $ch ) {
				// Capture a balanced { … } body.
				$depth = 1;
				$j     = $i + 1;
				while ( $j < $len && $depth > 0 ) {
					if ( '{' === $css[ $j ] ) {
						$depth++;
					} elseif ( '}' === $css[ $j ] ) {
						$depth--;
					}
					$j++;
				}
				$body     = substr( $css, $i + 1, $j - $i - 2 );
				$blocks[] = [ 'prelude' => trim( $prelude ), 'body' => $body, 'raw' => $prelude . '{' . $body . '}' ];
				$prelude  = '';
				$i        = $j;
				continue;
			}

			$prelude .= $ch;
			$i++;
		}

		return $blocks;
	}
}
