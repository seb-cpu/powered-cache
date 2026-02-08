<?php
/**
 * Compatibility with Autoptimize
 *
 * @package SwiftPress\Compat
 */

namespace SwiftPress\Compat\Autoptimize;

use function SwiftPress\Utils\clean_site_cache_dir;
use function SwiftPress\Utils\delete_page_cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) && class_exists( '\autoptimizeCache' ) ) {

	add_action( 'swiftpress_flushed', __NAMESPACE__ . '\\delete_autoptimize_cache' );
	add_action( 'swiftpress_clean_site_cache_dir', __NAMESPACE__ . '\\delete_autoptimize_cache' );
	add_action( 'autoptimize_action_cachepurged', __NAMESPACE__ . '\\flush_site_cache' );

	/**
	 * Delete site cache directoryu on autoptimize purge
	 */
	function flush_site_cache() {
		clean_site_cache_dir();
	}

	/**
	 * Delete autoptimize cache when page cache flushed
	 */
	function delete_autoptimize_cache() {
		if ( function_exists( '\autoptimizeCache::clearall' ) ) {
			\autoptimizeCache::clearall();
		}
	}

	add_action( 'swiftpress_admin_page_before_file_optimization', __NAMESPACE__ . '\\add_notice' );

	/**
	 * Show a message in the file optimization section
	 */
	function add_notice() {
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'It seems autoptimize is activated on your site. No worries, SwiftPress works perfectly fine with autoptimize but you cannot use file optimization options that conflict with autoptimize unless you deactivate it.', 'swiftpress' ); ?><br>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( '/options-general.php?page=autoptimize' ) ); ?>" style="margin-top: 10px;"><?php esc_html_e( 'Configure Autoptimize', 'swiftpress' ); ?></a>
			</p>
		</div>
		<?php
	}

	if ( 'on' === get_option( 'autoptimize_html' ) ) {
		add_filter( 'swiftpress_admin_page_fo_basic_settings_classes', __NAMESPACE__ . '\\disable_ui_option' );
		add_filter( 'swiftpress_fo_disable_html_minify', '__return_true' );
	}

	if ( 'on' === get_option( 'autoptimize_css' ) ) {
		add_filter( 'swiftpress_admin_page_fo_css_classes', __NAMESPACE__ . '\\disable_ui_option' );
		add_filter( 'swiftpress_fo_disable_css_minify', '__return_true' );
		add_filter( 'swiftpress_fo_disable_css_combine', '__return_true' );
	}


	if ( 'on' === get_option( 'autoptimize_js' ) ) {
		add_filter( 'swiftpress_admin_page_fo_js_classes', __NAMESPACE__ . '\\disable_ui_option' );
		add_filter( 'swiftpress_fo_disable_js_minify', '__return_true' );
		add_filter( 'swiftpress_fo_disable_js_combine', '__return_true' );
	}

	/**
	 * Disable UI element
	 *
	 * @param string $classes CSS classes
	 *
	 * @return string
	 */
	function disable_ui_option( $classes ) {
		$classes .= ' disabled';

		return $classes;
	}
}

