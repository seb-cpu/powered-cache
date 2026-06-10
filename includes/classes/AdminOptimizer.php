<?php
/**
 * WP-admin backend speed: trim the things that make wp-admin feel slow.
 *
 * One honest toggle (`optimize_admin`) that:
 *  - removes the dashboard widgets that perform remote HTTP fetches on load
 *    (WordPress Events & News) plus the rarely-used Quick Draft box;
 *  - throttles the Heartbeat API outside the editor (15s → 120s), which cuts
 *    a steady stream of admin-ajax.php POSTs on every open admin tab;
 *  - leaves the post editor's heartbeat alone, so autosave and post locking
 *    keep working exactly as before.
 *
 * @package SwiftPress
 */

namespace SwiftPress;

use function SwiftPress\Utils\get_settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminOptimizer
 */
class AdminOptimizer {

	/**
	 * Constructor (placeholder, mirrors codebase style).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return AdminOptimizer
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
	 * Register hooks when enabled. Admin-only by nature.
	 *
	 * @return void
	 */
	public function setup() {
		$settings = get_settings();

		if ( empty( $settings['optimize_admin'] ) || ! is_admin() ) {
			return;
		}

		add_action( 'wp_dashboard_setup', [ $this, 'trim_dashboard_widgets' ], 99 );
		add_filter( 'heartbeat_settings', [ $this, 'throttle_heartbeat' ] );

		// Admin page loads can block 0.3–4s on api.wordpress.org when an update
		// transient happens to expire mid-request. WP-Cron runs the same checks
		// twice daily anyway, so updates still surface — just never synchronously
		// inside someone's pageload. (Update screens trigger their own checks.)
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
	}

	/**
	 * Remove the dashboard widgets that call home / are rarely used.
	 *
	 * `wp_dashboard_primary` ("WordPress Events and News") fetches remote feeds
	 * during the dashboard load — the single biggest cause of a slow wp-admin
	 * landing page on many sites.
	 *
	 * @return void
	 */
	public function trim_dashboard_widgets() {
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );    // WP Events & News (remote fetch).
		remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );  // Legacy secondary feed.
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' ); // Quick Draft.

		/**
		 * Filters whether AICache also removes the Site Health dashboard widget.
		 *
		 * @hook  swiftpress_admin_remove_site_health_widget
		 * @since 1.2
		 */
		if ( apply_filters( 'swiftpress_admin_remove_site_health_widget', false ) ) {
			remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
		}
	}

	/**
	 * Slow the Heartbeat outside the editor. The editor screen needs its fast
	 * pulse for autosave/locking; list tables and the dashboard do not.
	 *
	 * @param array $settings Heartbeat client settings.
	 *
	 * @return array
	 */
	public function throttle_heartbeat( $settings ) {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_editor = $screen && ( 'post' === $screen->base || 'site-editor' === $screen->base );

		if ( ! $is_editor ) {
			/**
			 * Filters the throttled Heartbeat interval (seconds) outside the editor.
			 *
			 * @hook  swiftpress_admin_heartbeat_interval
			 * @since 1.2
			 */
			$settings['interval'] = max( 15, min( 300, (int) apply_filters( 'swiftpress_admin_heartbeat_interval', 120 ) ) );
		}

		return $settings;
	}
}
