<?php
/**
 * Core plugin functionality.
 *
 * @package SwiftPress
 */

namespace SwiftPress\Core;

use SwiftPress\Async\CachePreloader;
use SwiftPress\Async\CachePurger;
use SwiftPress\Config;
use const SwiftPress\Constants\MENU_SLUG;
use SwiftPress\Optimizer\JS;
use \WP_Error as WP_Error;

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	add_action( 'init', __NAMESPACE__ . '\\i18n' );
	add_action( 'init', __NAMESPACE__ . '\\init' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_scripts' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\admin_styles' );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\block_editor_assets' );
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\register_async_process' );

	// Hook to allow async or defer on asset loading.
	add_filter( 'script_loader_tag', __NAMESPACE__ . '\\script_loader_tag', 10, 2 );

	/**
	 * Fires after swiftpress loaded
	 *
	 * @hook  swiftpress_loaded
	 *
	 * @since 2.0
	 */
	do_action( 'swiftpress_loaded' );
}

/**
 * Registers the default textdomain.
 *
 * @return void
 */
function i18n() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'swiftpress' ); // This filter is documented in /wp-includes/l10n.php.
	load_textdomain( 'swiftpress', WP_LANG_DIR . '/swiftpress/swiftpress-' . $locale . '.mo' );
	load_plugin_textdomain( 'swiftpress', false, plugin_basename( SWIFTPRESS_PATH ) . '/languages/' );
}

/**
 * Initializes the plugin and fires an action other plugins can hook into.
 *
 * @return void
 */
function init() {
	/**
	 * Fires during init
	 *
	 * @hook  swiftpress_init
	 *
	 * @since 2.0
	 */
	do_action( 'swiftpress_init' );
}

/**
 * Activate the plugin
 *  `SWIFTPRESS_IS_NETWORK` useless on networkwide activation at first
 *
 * @param bool $network_wide Whether network-wide configuration or not
 *
 * @return void
 */
function activate( $network_wide ) {
	$settings = \SwiftPress\Utils\get_settings( $network_wide );
	Config::factory()->save_configuration( $settings, $network_wide );
}

/**
 * Deactivate the plugin
 *
 * Uninstall routines should be in uninstall.php
 *
 * @param bool $network_wide Whether network-wide configuration or not
 *
 * @return void
 */
function deactivate( $network_wide ) {
	Config::factory()->clean_up();

	// cancel async jobs
	$cache_preloader = CachePreloader::factory();
	$cache_preloader->cancel_process();
}


/**
 * The list of knows contexts for enqueuing scripts/styles.
 *
 * @return array
 */
function get_enqueue_contexts() {
	return [ 'admin', 'frontend', 'shared', 'classic-editor' ];
}

/**
 * Generate an URL to a script, taking into account whether SCRIPT_DEBUG is enabled.
 *
 * @param string $script  Script file name (no .js extension)
 * @param string $context Context for the script ('admin', 'frontend', or 'shared')
 *
 * @return string|WP_Error URL
 */
function script_url( $script, $context ) {

	if ( ! in_array( $context, get_enqueue_contexts(), true ) ) {
		return new WP_Error( 'invalid_enqueue_context', 'Invalid $context specified in SwiftPress script loader.' );
	}

	return SWIFTPRESS_URL . "dist/js/{$script}.js";

}

/**
 * Generate an URL to a stylesheet, taking into account whether SCRIPT_DEBUG is enabled.
 *
 * @param string $stylesheet Stylesheet file name (no .css extension)
 * @param string $context    Context for the script ('admin', 'frontend', or 'shared')
 *
 * @return string URL
 */
function style_url( $stylesheet, $context ) {

	if ( ! in_array( $context, get_enqueue_contexts(), true ) ) {
		return new WP_Error( 'invalid_enqueue_context', 'Invalid $context specified in SwiftPress stylesheet loader.' );
	}

	return SWIFTPRESS_URL . "dist/css/{$stylesheet}.css";

}

/**
 * Enqueue scripts for admin.
 *
 * @param string $hook Current hook.
 *
 * @return void
 */
function admin_scripts( $hook ) {

	$classic_editor_hooks = [ 'post-new.php', 'post.php' ];

	if ( in_array( $hook, $classic_editor_hooks, true ) ) {
		wp_enqueue_script(
			'swiftpress-classic-editor',
			script_url( 'classic-editor', 'classic-editor' ),
			[
				'jquery',
			],
			SWIFTPRESS_VERSION,
			true
		);
	}

	if ( empty( $_GET['page'] ) || 0 !== strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), MENU_SLUG ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	wp_enqueue_script(
		'swiftpress-settings',
		SWIFTPRESS_URL . 'assets/js/admin/swiftpress-settings.js',
		[ 'jquery' ],
		SWIFTPRESS_VERSION,
		true
	);

	wp_localize_script(
		'swiftpress-settings',
		'swiftpressSettings',
		[
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'swiftpress_settings_ajax' ),
			'i18n'    => [
				'clearing'         => esc_html__( 'Clearing...', 'swiftpress' ),
				'cacheCleared'     => esc_html__( 'All cache cleared successfully.', 'swiftpress' ),
				'fontCacheCleared' => esc_html__( 'Font cache cleared successfully.', 'swiftpress' ),
				'refreshing'       => esc_html__( 'Refreshing...', 'swiftpress' ),
				'sitemapRefreshed' => esc_html__( 'Sitemap refreshed successfully.', 'swiftpress' ),
				'refreshSitemap'   => esc_html__( 'Refresh Sitemap', 'swiftpress' ),
				'clearAllCache'    => esc_html__( 'Clear All Cache', 'swiftpress' ),
				'error'            => esc_html__( 'An error occurred. Please try again.', 'swiftpress' ),
			],
		]
	);

}

/**
 * Enqueue Block Editor assets
 *
 * @since 2.0
 */
function block_editor_assets() {

	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}

	/**
	 * Min. WP 5.3 required for block editor plugin due to useSelect
	 * Likely the older version of react didn't support hooks.
	 * The post meta-box works with compat mode vice-versa...
	 */
	if ( version_compare( get_bloginfo( 'version' ), '5.3', '>=' ) ) {
		wp_register_script(
			'swiftpress-editor',
			script_url( 'editor', 'admin' ),
			[
				'jquery',
				'lodash',
				'wp-i18n',
				'wp-edit-post',
				'wp-components',
				'wp-compose',
				'wp-data',
				'wp-edit-post',
				'wp-element',
				'wp-plugins',
			],
			SWIFTPRESS_VERSION,
			true
		);

		wp_enqueue_script( 'swiftpress-editor' );

		wp_set_script_translations(
			'swiftpress-editor',
			'swiftpress',
			plugin_dir_path( SWIFTPRESS_PLUGIN_FILE ) . 'languages'
		);

	}
}

/**
 * Enqueue styles for admin.
 *
 * @return void
 */
function admin_styles() {
	// load on any swiftpress app screen (slug prefix covers all submenus)
	if ( empty( $_GET['page'] ) || 0 !== strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), MENU_SLUG ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	wp_enqueue_style(
		'swiftpress-settings',
		SWIFTPRESS_URL . 'assets/css/admin/swiftpress-settings.css',
		[],
		SWIFTPRESS_VERSION
	);

}

/**
 * Add async/defer attributes to enqueued scripts that have the specified script_execution flag.
 *
 * @link https://core.trac.wordpress.org/ticket/12009
 *
 * @param string $tag    The script tag.
 * @param string $handle The script handle.
 *
 * @return string
 */
function script_loader_tag( $tag, $handle ) {
	$script_execution = wp_scripts()->get_data( $handle, 'script_execution' );

	if ( ! $script_execution ) {
		return $tag;
	}

	if ( 'async' !== $script_execution && 'defer' !== $script_execution ) {
		return $tag;
	}

	// Abort adding async/defer for scripts that have this script as a dependency. _doing_it_wrong()?
	foreach ( wp_scripts()->registered as $script ) {
		if ( in_array( $handle, $script->deps, true ) ) {
			return $tag;
		}
	}

	// Add the attribute if it hasn't already been added.
	if ( ! preg_match( ":\s$script_execution(=|>|\s):", $tag ) ) {
		$tag = preg_replace( ':(?=></script>):', " $script_execution", $tag, 1 );
	}

	return $tag;
}

/**
 * Invoke async classes
 */
function register_async_process() {
	CachePurger::factory();
}
