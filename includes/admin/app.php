<?php
/**
 * SwiftPress — "Editorial Console" admin application.
 *
 * The revamped admin: a server-rendered shell (nav rail + top bar) that
 * dispatches to views (Brief / Tune / Server / Copilot). The Brief is the
 * AI-first home; settings forms reuse the EXISTING save path
 * (process_form_submit) verbatim, so no new write code is introduced.
 *
 * Enqueues a scoped design-system CSS + a small vanilla controller. The
 * heavier streaming Preact "Brief island" is a documented fast-follow; v1
 * ships the lean version to stay true to the performance brand.
 *
 * @package SwiftPress
 * @since   2.0
 */

namespace SwiftPress\Admin\App;

use SwiftPress\Config;
use function SwiftPress\Utils\get_settings;
use function SwiftPress\Utils\get_cache_dir;
use function SwiftPress\Utils\get_timeout_with_interval;
use const SwiftPress\Constants\MENU_SLUG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the revamped UI is active. Filterable so the classic settings page
 * can be restored as a fallback.
 *
 * @return bool
 */
function is_enabled() {
	return (bool) apply_filters( 'swiftpress_use_editorial_console', true );
}

/**
 * Register enqueue. Called from setup() in dashboard bootstrap area.
 */
function setup() {
	if ( ! is_enabled() ) {
		return;
	}
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue' );
	add_filter( 'admin_body_class', __NAMESPACE__ . '\\body_class' );
}

/**
 * Mark our screen so we can full-bleed it.
 *
 * @param string $classes existing classes.
 * @return string
 */
function body_class( $classes ) {
	if ( is_our_screen() ) {
		$classes .= ' swiftpress-editorial';
	}
	return $classes;
}

/**
 * @return bool true on the SwiftPress settings screen.
 */
function is_our_screen() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}
	$screen = get_current_screen();
	return $screen && false !== strpos( (string) $screen->id, MENU_SLUG );
}

/**
 * Enqueue scoped assets + the self-hosted fonts and JS bridge data.
 *
 * @param string $hook current admin page hook.
 */
function enqueue( $hook ) {
	if ( ! is_our_screen() ) {
		return;
	}

	// Self-host the three faces through SwiftPress's own pipeline ideally; for
	// the admin we load from Google Fonts with display=swap (admin-only, no
	// front-end / Lighthouse impact). A future pass self-hosts via FontOptimizer.
	wp_enqueue_style(
		'swiftpress-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,360..600;1,9..144,360..560&family=IBM+Plex+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap',
		[],
		SWIFTPRESS_VERSION
	);

	wp_enqueue_style(
		'swiftpress-app',
		SWIFTPRESS_URL . 'assets/css/admin/swiftpress-app.css',
		[ 'swiftpress-fonts' ],
		SWIFTPRESS_VERSION
	);

	wp_enqueue_script(
		'swiftpress-app',
		SWIFTPRESS_URL . 'assets/js/admin/swiftpress-app.js',
		[],
		SWIFTPRESS_VERSION,
		true
	);

	$settings = get_settings();

	wp_localize_script(
		'swiftpress-app',
		'swiftpressApp',
		[
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'aiNonce'       => wp_create_nonce( 'swiftpress_ai' ),
			'ajaxNonce'     => wp_create_nonce( 'swiftpress_settings_ajax' ),
			'settingsNonce' => wp_create_nonce( 'swiftpress_update_settings' ),
			'site'          => wp_parse_url( home_url(), PHP_URL_HOST ),
			'i18n'       => [
				'analyzing'  => esc_html__( 'Reading your site…', 'swiftpress' ),
				'applied'    => esc_html__( 'Applied', 'swiftpress' ),
				'applying'   => esc_html__( 'Applying…', 'swiftpress' ),
				'noKey'      => esc_html__( 'Add your OpenRouter key in Copilot to get plain-language explanations. Standard recommendations are shown without a key.', 'swiftpress' ),
				'failed'     => esc_html__( 'Something went wrong. Please try again.', 'swiftpress' ),
			],
		]
	);

	// The AI module (AI::factory()->enqueue) localizes the `swiftpressAI` object
	// (nonce/hasKey/keySource/hasSnapshot) on this same page, so we do not duplicate it.
}

/**
 * Render the application (called by dashboard settings_page()).
 */
function render() {
	if ( ! is_enabled() ) {
		// Fallback to the classic settings page.
		include __DIR__ . '/partials/settings-page.php';
		return;
	}

	global $is_apache;
	$settings = get_settings();
	$view     = isset( $_GET['sp_view'] ) ? sanitize_key( wp_unslash( $_GET['sp_view'] ) ) : 'brief'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$server   = ! empty( $is_apache ) ? 'apache' : 'nginx';
	$cache    = cache_stats();

	$base_url = SWIFTPRESS_IS_NETWORK ? network_admin_url( 'admin.php?page=' . MENU_SLUG ) : admin_url( 'admin.php?page=' . MENU_SLUG );

	echo '<div class="wrap swiftpress-app" data-sp-theme="dark">';
	echo '<div class="sp-shell">';
	render_rail( $view, $settings, $base_url );
	echo '<div class="sp-main">';
	render_topbar( $server, $cache );
	echo '<div class="sp-scroll"><div class="sp-wrap">';

	switch ( $view ) {
		case 'tune':
			view_tune( $settings );
			break;
		case 'server':
			view_server( $server );
			break;
		case 'copilot':
			view_copilot();
			break;
		case 'brief':
		default:
			view_brief( $settings, $server, $cache );
			break;
	}

	echo '</div></div>'; // /sp-scroll /sp-wrap
	echo '</div></div></div>'; // /sp-main /sp-shell /wrap
	render_cmdk();
}

/**
 * Cheap cache stats (size + file count) for the pulse strip.
 *
 * @return array{size:string,count:int}
 */
function cache_stats() {
	$dir   = trailingslashit( get_cache_dir() ) . 'swiftpress/';
	$bytes = 0;
	$count = 0;
	if ( is_dir( $dir ) ) {
		try {
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				if ( $f->isFile() ) {
					$bytes += $f->getSize();
					$count++;
					if ( $count > 50000 ) {
						break; // safety cap
					}
				}
			}
		} catch ( \Exception $e ) {
			$bytes = 0;
		}
	}
	return [ 'size' => size_h( $bytes ), 'count' => $count ];
}

/**
 * Human-readable bytes.
 *
 * @param int $b bytes.
 * @return string
 */
function size_h( $b ) {
	if ( $b <= 0 ) {
		return '0 MB';
	}
	$u = [ 'B', 'KB', 'MB', 'GB' ];
	$i = (int) floor( log( $b, 1024 ) );
	$i = min( $i, count( $u ) - 1 );
	return round( $b / pow( 1024, $i ), $i ? 1 : 0 ) . ' ' . $u[ $i ];
}

/** Icon helper. */
function icon( $name ) {
	$p = [
		'brief'   => '<path d="M8 4h8a2 2 0 0 1 2 2v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V6a2 2 0 0 1 2-2z"/><path d="M9 9h6M9 13h6M9 17h3"/>',
		'spark'   => '<path d="M12 3v2m0 14v2M5 12H3m18 0h-2m-2.6 6.4 1.4 1.4M5.2 5.2l1.4 1.4m10 0 1.4-1.4M5.2 18.8l1.4-1.4"/><circle cx="12" cy="12" r="4"/>',
		'refresh' => '<path d="M21 12a9 9 0 1 1-3-6.7M21 4v4h-4"/>',
		'shield'  => '<path d="M12 2 3 7v6c0 5 9 9 9 9s9-4 9-9V7l-9-5z"/>',
	];
	return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">' . ( $p[ $name ] ?? '' ) . '</svg>';
}

/** Nav rail. */
function render_rail( $view, $settings, $base ) {
	$item = function ( $slug, $label, $dotclass, $right = '' ) use ( $view, $base ) {
		$active = ( $view === $slug || ( 'brief' === $slug && 'brief' === $view ) ) ? ' active' : '';
		$url    = esc_url( add_query_arg( 'sp_view', $slug, $base ) );
		$dot    = $dotclass ? '<span class="dot ' . esc_attr( $dotclass ) . '"></span>' : '';
		echo '<a class="' . esc_attr( ltrim( $active ) ) . '" href="' . $url . '">' . $dot . esc_html( $label ) . $right . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	};
	?>
	<aside class="sp-rail">
		<div class="sp-brand">
			<span class="glyph"><svg viewBox="0 0 24 24" fill="none"><path d="M13 2 4 14h6l-1 8 10-13h-7l1-7z" fill="currentColor"/></svg></span>
			<span class="name">AICache</span>
			<span class="ver"><?php echo esc_html( SWIFTPRESS_VERSION ); ?></span>
		</div>
		<nav class="sp-nav">
			<a class="<?php echo ( 'brief' === $view ) ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'sp_view', 'brief', $base ) ); ?>"><?php echo icon( 'brief' ); // phpcs:ignore ?> The Brief</a>
			<div class="lbl"><?php esc_html_e( 'Tune', 'swiftpress' ); ?></div>
			<?php $item( 'tune', __( 'Settings', 'swiftpress' ), $settings['enable_page_cache'] ? 'on' : 'off' ); ?>
			<div class="lbl"><?php esc_html_e( 'Operate', 'swiftpress' ); ?></div>
			<?php $item( 'server', __( 'Server', 'swiftpress' ), 'on', '<span class="tag">' . esc_html( ! empty( $GLOBALS['is_apache'] ) ? 'apache' : 'nginx' ) . '</span>' ); ?>
			<a class="<?php echo ( 'copilot' === $view ) ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'sp_view', 'copilot', $base ) ); ?>"><span class="dot on"></span> Copilot <span class="spark" id="sp-key-pill" style="display:none">KEY SET</span></a>
		</nav>
		<div class="foot">
			<button type="button" class="sp-btn ghost" id="sp-theme-btn">◐ <?php esc_html_e( 'Theme', 'swiftpress' ); ?></button>
			<button type="button" class="sp-btn" id="sp-flush-all"><?php esc_html_e( 'Flush all', 'swiftpress' ); ?></button>
		</div>
	</aside>
	<?php
}

/** Top bar. */
function render_topbar( $server, $cache ) {
	$php   = PHP_VERSION;
	$redis = ( class_exists( 'Redis' ) || defined( 'WP_REDIS_HOST' ) || wp_using_ext_object_cache() ) ? true : false;
	?>
	<header class="sp-top">
		<div class="sp-crumbs">
			<b><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></b><span class="sep">·</span>
			<span><?php echo esc_html( $server ); ?></span><span class="sep">·</span>
			<span>PHP <?php echo esc_html( $php ); ?></span><span class="sep">·</span>
			<span><?php esc_html_e( 'Object cache', 'swiftpress' ); ?> <?php echo $redis ? '<span class="ok">✓</span>' : '<span style="color:var(--ink-4)">—</span>'; // phpcs:ignore ?></span>
		</div>
		<div class="spacer"></div>
		<div class="sp-ask" id="sp-ask-trigger">
			<?php echo icon( 'spark' ); // phpcs:ignore ?>
			<?php esc_html_e( 'Ask AICache, or type a command…', 'swiftpress' ); ?>
			<span class="kbd">⌘K</span>
		</div>
		<button type="button" class="sp-btn amber" id="sp-run-diagnostic"><?php echo icon( 'refresh' ); // phpcs:ignore ?> <?php esc_html_e( 'Run Diagnostic', 'swiftpress' ); ?></button>
	</header>
	<?php
}

/** The Brief (home). */
function view_brief( $settings, $server, $cache ) {
	$circ = 2 * M_PI * 52;
	?>
	<div class="sp-pulse sp-reveal">
		<div class="cell"><span class="k"><span class="d" id="sp-pd-score"></span><?php esc_html_e( 'Score', 'swiftpress' ); ?></span><span class="v" id="sp-pv-score">—</span></div>
		<div class="cell"><span class="k"><span class="d" id="sp-pd-lcp"></span>LCP</span><span class="v" id="sp-pv-lcp">—</span></div>
		<div class="cell"><span class="k"><span class="d" id="sp-pd-cls"></span>CLS</span><span class="v" id="sp-pv-cls">—</span></div>
		<div class="cell"><span class="k"><span class="d" id="sp-pd-inp"></span>INP</span><span class="v" id="sp-pv-inp">—</span></div>
		<div class="cell"><span class="k"><span class="d" id="sp-pd-ttfb"></span>TTFB</span><span class="v" id="sp-pv-ttfb">—</span></div>
		<div class="cell"><span class="k"><span class="d good"></span><?php esc_html_e( 'Cache', 'swiftpress' ); ?></span><span class="v"><?php echo esc_html( $cache['size'] ); ?><small> · <?php echo (int) $cache['count']; ?></small></span></div>
	</div>

	<section class="sp-brief">
		<div class="sp-reveal" style="animation-delay:.06s">
			<div class="sp-eyebrow"><span class="live"></span> <span id="sp-brief-eyebrow"><?php esc_html_e( 'AI Brief', 'swiftpress' ); ?></span></div>
			<div id="sp-brief-content">
				<h1><?php esc_html_e( 'Let’s find out what’s slowing', 'swiftpress' ); ?> <em><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></em>.</h1>
				<p class="sp-lede">
					<?php esc_html_e( 'Run a diagnostic and I’ll read your site’s real performance data, explain in plain language what’s holding it back, and map each issue to a switch AICache already has — applied with one click and reversible.', 'swiftpress' ); ?>
				</p>
				<p class="sp-lede" style="margin-top:12px;color:var(--ink-3)"><?php esc_html_e( 'Server is', 'swiftpress' ); ?> <span class="term"><?php echo esc_html( $server ); ?></span>, <?php esc_html_e( 'PHP', 'swiftpress' ); ?> <span class="term"><?php echo esc_html( PHP_VERSION ); ?></span>. <span id="sp-key-hint"></span></p>
			</div>
		</div>

		<div class="sp-score sp-reveal" style="animation-delay:.14s" role="meter" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e( 'Performance score', 'swiftpress' ); ?>">
			<svg viewBox="0 0 120 120">
				<circle class="ring-bg" cx="60" cy="60" r="52"></circle>
				<circle class="ring-fg" id="sp-ring" cx="60" cy="60" r="52" style="stroke-dasharray:<?php echo esc_attr( $circ ); ?>;stroke-dashoffset:<?php echo esc_attr( $circ ); ?>"></circle>
			</svg>
			<div class="center"><div class="num" id="sp-score-num">—</div><div class="of">/ 100 · mobile</div></div>
			<div class="cap"><?php esc_html_e( 'Performance', 'swiftpress' ); ?> · <b id="sp-score-src"><?php esc_html_e( 'not yet measured', 'swiftpress' ); ?></b></div>
			<div class="delta" id="sp-score-delta"></div>
		</div>
	</section>

	<div class="sp-sec-head" id="sp-findings-head" style="display:none">
		<h2><?php esc_html_e( 'Explain & Fix', 'swiftpress' ); ?></h2>
		<span class="count" id="sp-findings-count"></span>
		<button type="button" class="sp-btn all" id="sp-apply-all"><?php esc_html_e( 'Apply all safe fixes', 'swiftpress' ); ?></button>
	</div>
	<div class="sp-findings" id="sp-findings"></div>

	<div class="sp-presets sp-reveal" style="animation-delay:.2s">
		<div class="lab"><?php esc_html_e( 'Or pick a starting posture', 'swiftpress' ); ?><small><?php esc_html_e( 'Each preset is an explicit map of switches with per-setting risk labels — never a mystery bundle.', 'swiftpress' ); ?></small></div>
		<button type="button" class="sp-chip" data-preset="safe"><?php esc_html_e( 'Safe', 'swiftpress' ); ?></button>
		<button type="button" class="sp-chip" data-preset="balanced"><?php esc_html_e( 'Balanced', 'swiftpress' ); ?></button>
		<button type="button" class="sp-chip" data-preset="aggressive"><?php esc_html_e( 'Aggressive', 'swiftpress' ); ?></button>
		<button type="button" class="sp-chip" data-preset="woocommerce">WooCommerce</button>
	</div>

	<div class="sp-note"><?php echo icon( 'shield' ); // phpcs:ignore ?> <?php esc_html_e( 'Every fix applies through AICache’s own save path and is snapshotted — one click reverts it. Your API key never leaves your server.', 'swiftpress' ); ?></div>
	<?php
}

/** A styled toggle row reusing the existing setting key names (posts to process_form_submit). */
function toggle_row( $key, $label, $desc, $settings, $stub = false ) {
	$checked = ! empty( $settings[ $key ] );
	?>
	<div class="sp-row<?php echo $stub ? ' stub' : ''; ?>">
		<div>
			<div class="label"><?php echo esc_html( $label ); ?><?php if ( $stub ) : ?><span class="sp-pill-soon"><?php esc_html_e( 'engine pending', 'swiftpress' ); ?></span><?php endif; ?></div>
			<?php if ( $desc ) : ?><div class="desc"><?php echo wp_kses( $desc, [ 'code' => [] ] ); ?></div><?php endif; ?>
		</div>
		<label class="sp-toggle">
			<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $checked ); ?> <?php disabled( $stub ); ?> />
			<span class="track"></span><span class="knob"></span>
		</label>
	</div>
	<?php
}

/** Tune — re-skinned settings; reuses the existing save handler + nonce. */
function view_tune( $settings ) {
	list( $timeout_v, $timeout_i ) = get_timeout_with_interval( $settings['cache_timeout'] );
	?>
	<div class="sp-page-head"><h1><?php esc_html_e( 'Tune', 'swiftpress' ); ?></h1><p><?php esc_html_e( 'Every optimization, grouped and explained. Saved through AICache’s existing, battle-tested settings pipeline.', 'swiftpress' ); ?></p></div>

	<form method="post" action="">
		<?php wp_nonce_field( 'swiftpress_update_settings', 'swiftpress_settings_nonce' ); ?>
		<input type="hidden" name="swiftpress_form_action" value="save_settings" />

		<div class="sp-panel">
			<h2><?php esc_html_e( 'Page Cache', 'swiftpress' ); ?><span class="hint"><?php esc_html_e( 'Serve static HTML to anonymous visitors', 'swiftpress' ); ?></span></h2>
			<div class="sp-panel-body">
				<?php
				toggle_row( 'enable_page_cache', __( 'Enable page cache', 'swiftpress' ), __( 'Cache full pages so repeat visitors skip PHP and the database entirely.', 'swiftpress' ), $settings );
				toggle_row( 'gzip_compression', __( 'Gzip compression', 'swiftpress' ), __( 'Store pre-compressed cache files for faster transfer.', 'swiftpress' ), $settings );
				toggle_row( 'cache_mobile', __( 'Mobile cache', 'swiftpress' ), __( 'Serve cached pages to mobile visitors.', 'swiftpress' ), $settings );
				?>
				<div class="sp-row">
					<div><div class="label"><?php esc_html_e( 'Cache expiration', 'swiftpress' ); ?></div><div class="desc"><?php esc_html_e( 'How long before a cached page is rebuilt.', 'swiftpress' ); ?></div></div>
					<div style="display:flex;gap:8px;align-items:center">
						<input class="sp-input" type="number" name="cache_timeout" min="0" value="<?php echo esc_attr( $timeout_v ); ?>" />
						<select class="sp-select" name="cache_timeout_interval">
							<option value="MINUTE" <?php selected( $timeout_i, 'MINUTE' ); ?>><?php esc_html_e( 'Minutes', 'swiftpress' ); ?></option>
							<option value="HOUR" <?php selected( $timeout_i, 'HOUR' ); ?>><?php esc_html_e( 'Hours', 'swiftpress' ); ?></option>
							<option value="DAY" <?php selected( $timeout_i, 'DAY' ); ?>><?php esc_html_e( 'Days', 'swiftpress' ); ?></option>
						</select>
					</div>
				</div>
			</div>
		</div>

		<div class="sp-panel">
			<h2><?php esc_html_e( 'Optimize CSS & JavaScript', 'swiftpress' ); ?></h2>
			<div class="sp-panel-body">
				<?php
				toggle_row( 'minify_css', __( 'Minify CSS', 'swiftpress' ), __( 'Strip whitespace and comments from stylesheets.', 'swiftpress' ), $settings );
				toggle_row( 'combine_css', __( 'Combine CSS', 'swiftpress' ), __( 'Merge stylesheets to cut requests (HTTP/2 makes this optional).', 'swiftpress' ), $settings );
				toggle_row( 'critical_css', __( 'Optimize CSS delivery (Critical CSS)', 'swiftpress' ), __( 'Inline above-the-fold CSS, defer the rest. <code>Algorithmic engine — shipping soon.</code>', 'swiftpress' ), $settings, true );
				toggle_row( 'remove_unused_css', __( 'Remove unused CSS', 'swiftpress' ), __( 'Strip selectors a page never uses. <code>Algorithmic engine — shipping soon.</code>', 'swiftpress' ), $settings, true );
				toggle_row( 'minify_js', __( 'Minify JavaScript', 'swiftpress' ), __( 'Shrink scripts.', 'swiftpress' ), $settings );
				toggle_row( 'js_defer', __( 'Defer JavaScript', 'swiftpress' ), __( 'Add <code>defer</code> so scripts stop blocking render.', 'swiftpress' ), $settings );
				toggle_row( 'js_delay', __( 'Delay JavaScript', 'swiftpress' ), __( 'Hold non-critical scripts (analytics, chat) until the visitor interacts — the single biggest LCP win for script-heavy themes.', 'swiftpress' ), $settings );
				?>
			</div>
		</div>

		<div class="sp-panel">
			<h2><?php esc_html_e( 'Media & Fonts', 'swiftpress' ); ?></h2>
			<div class="sp-panel-body">
				<?php
				toggle_row( 'add_missing_image_dimensions', __( 'Add missing image dimensions', 'swiftpress' ), __( 'Write width/height back into markup to stop layout shift (CLS).', 'swiftpress' ), $settings );
				toggle_row( 'enable_image_optimization', __( 'Image optimization', 'swiftpress' ), __( 'Serve next-gen formats. Choose the preferred format below.', 'swiftpress' ), $settings );
				?>
				<div class="sp-row">
					<div><div class="label"><?php esc_html_e( 'Preferred image format', 'swiftpress' ); ?></div></div>
					<select class="sp-select" name="image_optimizer_preferred_format">
						<option value="" <?php selected( $settings['image_optimizer_preferred_format'], '' ); ?>><?php esc_html_e( 'Auto', 'swiftpress' ); ?></option>
						<option value="webp" <?php selected( $settings['image_optimizer_preferred_format'], 'webp' ); ?>>WebP</option>
						<option value="avif" <?php selected( $settings['image_optimizer_preferred_format'], 'avif' ); ?>>AVIF</option>
					</select>
				</div>
				<?php
				toggle_row( 'enable_font_optimization', __( 'Font optimization', 'swiftpress' ), __( 'Self-host Google Fonts (no call to Google — GDPR-friendlier) and control loading.', 'swiftpress' ), $settings );
				toggle_row( 'self_host_google_fonts', __( 'Self-host Google Fonts', 'swiftpress' ), __( 'Download and serve fonts locally.', 'swiftpress' ), $settings );
				toggle_row( 'font_preload', __( 'Preload fonts', 'swiftpress' ), __( 'Preload above-the-fold font files.', 'swiftpress' ), $settings );
				toggle_row( 'font_display_swap', __( 'Force font-display: swap', 'swiftpress' ), __( 'Show text immediately instead of waiting on the font.', 'swiftpress' ), $settings );
				?>
			</div>
		</div>

		<div class="sp-panel">
			<h2><?php esc_html_e( 'Delivery & resource hints', 'swiftpress' ); ?><span class="hint"><?php esc_html_e( 'Previously hidden — now surfaced', 'swiftpress' ); ?></span></h2>
			<div class="sp-panel-body">
				<?php
				toggle_row( 'enable_lcp_optimization', __( 'LCP optimization', 'swiftpress' ), __( 'Prioritise the largest above-the-fold image (fetchpriority, no lazy-load).', 'swiftpress' ), $settings );
				toggle_row( 'prefetch_links', __( 'Prefetch links on hover', 'swiftpress' ), __( 'Pre-load the next page when a visitor hovers a link.', 'swiftpress' ), $settings );
				toggle_row( 'enable_cloudflare', __( 'Cloudflare integration', 'swiftpress' ), __( 'Purge Cloudflare when AICache clears cache (configure credentials in the classic settings for now).', 'swiftpress' ), $settings );
				?>
				<div class="sp-row">
					<div><div class="label"><?php esc_html_e( 'DNS-prefetch domains', 'swiftpress' ); ?></div><div class="desc"><?php esc_html_e( 'One domain per line — resolves DNS early for third-party hosts.', 'swiftpress' ); ?></div></div>
				</div>
				<textarea class="sp-textarea" name="prefetch_dns" rows="3" placeholder="//fonts.gstatic.com"><?php echo esc_textarea( $settings['prefetch_dns'] ); ?></textarea>
			</div>
		</div>

		<div class="sp-panel">
			<h2><?php esc_html_e( 'Integrations & bloat control', 'swiftpress' ); ?></h2>
			<div class="sp-panel-body">
				<?php
				toggle_row( 'enable_google_tracking', __( 'Self-host Google Analytics', 'swiftpress' ), __( 'Serve the GA script locally to remove a render-blocking third-party request.', 'swiftpress' ), $settings );
				toggle_row( 'enable_fb_tracking', __( 'Self-host Facebook Pixel', 'swiftpress' ), __( 'Serve the Pixel locally.', 'swiftpress' ), $settings );
				toggle_row( 'enable_heartbeat', __( 'Heartbeat control', 'swiftpress' ), __( 'Throttle the WordPress Heartbeat API to cut admin/server load.', 'swiftpress' ), $settings );
				toggle_row( 'disable_emoji_scripts', __( 'Disable emoji scripts', 'swiftpress' ), __( 'Remove the emoji polyfill most modern sites don’t need.', 'swiftpress' ), $settings );
				toggle_row( 'disable_wp_embeds', __( 'Disable WordPress embeds', 'swiftpress' ), __( 'Remove the wp-embed script if you don’t embed other WP posts.', 'swiftpress' ), $settings );
				?>
			</div>
		</div>

		<div style="display:flex;gap:10px;margin:8px 4px 0">
			<button type="submit" class="sp-btn amber"><?php esc_html_e( 'Save changes', 'swiftpress' ); ?></button>
			<button type="submit" name="swiftpress_form_action" value="save_settings_and_clear_cache" class="sp-btn"><?php esc_html_e( 'Save & clear cache', 'swiftpress' ); ?></button>
		</div>
	</form>
	<?php
}

/** Server — nginx/Apache config generator (uses Config::nginx_rules()). */
function view_server( $server ) {
	$config   = Config::factory();
	$nginx    = method_exists( $config, 'nginx_rules' ) ? $config->nginx_rules() : '';
	$dl_nonce = wp_create_nonce( 'swiftpress_download_rewrite' );
	$dl_url   = admin_url( 'admin-post.php?action=swiftpress_download_rewrite_settings&server=nginx&_wpnonce=' . $dl_nonce );
	?>
	<div class="sp-page-head"><h1><?php esc_html_e( 'Server', 'swiftpress' ); ?></h1><p><?php esc_html_e( 'Serve cache files straight from the web server, before PHP even starts.', 'swiftpress' ); ?></p></div>

	<div class="sp-banner info">
		<?php echo icon( 'shield' ); // phpcs:ignore ?>
		<?php
		/* translators: %s server type */
		printf( esc_html__( 'Detected server: %s. The page cache already works on any server via the PHP drop-in — these rules make anonymous hits bypass PHP entirely for maximum speed.', 'swiftpress' ), '<b style="color:var(--ink)">' . esc_html( $server ) . '</b>' );
		?>
	</div>

	<div class="sp-panel">
		<h2>nginx<span class="hint"><?php esc_html_e( 'Paste into your server block, then reload nginx', 'swiftpress' ); ?></span></h2>
		<div class="sp-panel-body" style="padding-bottom:18px">
			<div class="sp-codeblock">
				<div class="head">swiftpress.conf <a class="sp-btn ghost copy" id="sp-copy-nginx" style="padding:4px 10px"><?php esc_html_e( 'Copy', 'swiftpress' ); ?></a> <a class="sp-btn ghost" href="<?php echo esc_url( $dl_url ); ?>" style="padding:4px 10px"><?php esc_html_e( 'Download', 'swiftpress' ); ?></a></div>
				<pre id="sp-nginx-pre"><?php echo esc_html( $nginx ); ?></pre>
			</div>
			<div class="sp-note" style="margin-top:14px">↳ <?php esc_html_e( 'After pasting, run', 'swiftpress' ); ?> <code style="font-family:var(--mono);background:var(--inset);padding:1px 6px;border-radius:4px">sudo nginx -t && sudo systemctl reload nginx</code></div>
		</div>
	</div>
	<?php
}

/** Copilot — AI key + budget. */
function view_copilot() {
	?>
	<div class="sp-page-head"><h1>Copilot</h1><p><?php esc_html_e( 'Bring your own OpenRouter key. Calls are server-side, on-demand, and your key never reaches the browser or the cache files.', 'swiftpress' ); ?></p></div>

	<div class="sp-panel">
		<h2><?php esc_html_e( 'OpenRouter API key', 'swiftpress' ); ?><span class="hint" id="sp-key-source"></span></h2>
		<div class="sp-panel-body" style="padding-bottom:18px">
			<div class="sp-keystate none" id="sp-key-state" style="margin-bottom:12px"><span class="d"></span><span id="sp-key-state-text"><?php esc_html_e( 'No key set — standard recommendations only', 'swiftpress' ); ?></span></div>
			<div class="sp-keyrow">
				<input class="sp-input" type="password" id="sp-key-input" placeholder="sk-or-…" autocomplete="off" />
				<button type="button" class="sp-btn amber" id="sp-key-save"><?php esc_html_e( 'Save key', 'swiftpress' ); ?></button>
				<button type="button" class="sp-btn" id="sp-key-clear"><?php esc_html_e( 'Remove', 'swiftpress' ); ?></button>
			</div>
			<div class="sp-note"><?php echo icon( 'shield' ); // phpcs:ignore ?> <?php esc_html_e( 'Encrypted at rest with your WordPress salts. For the strongest option, define', 'swiftpress' ); ?> <code style="font-family:var(--mono);background:var(--inset);padding:1px 6px;border-radius:4px">SWIFTPRESS_OPENROUTER_KEY</code> <?php esc_html_e( 'in wp-config.php (then it never touches the database).', 'swiftpress' ); ?></div>
		</div>
	</div>

	<div class="sp-panel">
		<h2><?php esc_html_e( 'Model & budget', 'swiftpress' ); ?></h2>
		<div class="sp-panel-body">
			<div class="sp-row"><div><div class="label"><?php esc_html_e( 'Model', 'swiftpress' ); ?></div><div class="desc"><?php esc_html_e( 'Cheapest capable default. A full diagnostic costs well under one cent.', 'swiftpress' ); ?></div></div>
				<select class="sp-select" id="sp-ai-model" style="min-width:240px"><option value="google/gemini-2.5-flash-lite">Gemini 2.5 Flash-Lite</option><option value="google/gemini-2.5-flash">Gemini 2.5 Flash</option></select></div>
			<div class="sp-row"><div><div class="label"><?php esc_html_e( 'Monthly spend cap', 'swiftpress' ); ?></div><div class="desc"><?php esc_html_e( 'Calls refuse past this. On-demand only — never per page view.', 'swiftpress' ); ?></div></div>
				<div style="display:flex;align-items:center;gap:6px"><span style="color:var(--ink-3)">$</span><input class="sp-input" type="number" id="sp-ai-cap" value="2" min="0" step="1" /></div></div>
		</div>
	</div>

	<div class="sp-banner warn">
		<?php echo icon( 'shield' ); // phpcs:ignore ?>
		<?php esc_html_e( 'Data sent on a diagnostic: derived performance metrics + which AICache features are on. No page content, no visitor data, no personal information. Services: Google PageSpeed Insights + OpenRouter (see readme).', 'swiftpress' ); ?>
	</div>
	<?php
}

/** Command palette markup. */
function render_cmdk() {
	?>
	<div class="sp-cmdk" id="sp-cmdk">
		<div class="panel">
			<div class="in"><?php echo icon( 'spark' ); // phpcs:ignore ?><input id="sp-cmd-input" placeholder="<?php esc_attr_e( 'Ask, or command in plain English…', 'swiftpress' ); ?>" autocomplete="off" /></div>
			<div class="hints">
				<div class="hint"><span style="color:var(--amber)">↳</span> <span class="q">“speed it up but don’t cache the members area”</span></div>
				<div class="hint"><span style="color:var(--amber)">↳</span> <span class="q">“why is my LCP so slow?”</span></div>
				<div class="hint"><span style="color:var(--amber)">↳</span> <span class="q">“generate my nginx config”</span></div>
			</div>
			<div class="foot"><span>↵ <?php esc_html_e( 'to run', 'swiftpress' ); ?></span><span>esc <?php esc_html_e( 'to close', 'swiftpress' ); ?></span><span><?php esc_html_e( 'every command shows a diff before it applies', 'swiftpress' ); ?></span></div>
		</div>
	</div>
	<div class="sp-toasts" id="sp-toasts"></div>
	<?php
}
