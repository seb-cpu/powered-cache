<?php
/**
 * Compat with Cookie Notice & Compliance for GDPR / CCPA
 *
 * @package SwiftPress\Compat
 * @link    https://wordpress.org/plugins/cookie-notice/
 */

namespace SwiftPress\Compat\CookieNotice;

use SwiftPress\Config;
use function SwiftPress\Utils\clean_site_cache_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( '\Cookie_Notice' ) ) {
	add_filter( 'swiftpress_mod_rewrite', '__return_false', 21 );
	add_filter( 'swiftpress_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
}


/**
 * Add cookie to vary cookie options
 *
 * @param array $cookies The list of vary cookies
 *
 * @return array Altered cookie list.
 * @since 2.0
 */
function add_vary_cookie( $cookies ) {
	$cookies[] = 'cookie_notice_accepted';

	return $cookies;
}

/**
 * Setup vary cookie on activation
 *
 * @since 2.0
 */
function activate() {
	add_filter( 'swiftpress_mod_rewrite', '__return_false' );
	add_filter( 'swiftpress_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
	$settings = \SwiftPress\Utils\get_settings();
	Config::factory()->save_configuration( $settings, SWIFTPRESS_IS_NETWORK );
	clean_site_cache_dir();
}

/**
 * Remove vary cookie on deactivation
 *
 * @since 2.0
 */
function deactivate() {
	remove_filter( 'swiftpress_mod_rewrite', '__return_false', 21 );
	remove_filter( 'swiftpress_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
	$settings = \SwiftPress\Utils\get_settings();
	Config::factory()->save_configuration( $settings, SWIFTPRESS_IS_NETWORK );
	clean_site_cache_dir();
}

add_action( 'activate_cookie-notice/cookie-notice.php', __NAMESPACE__ . '\\activate', 21 );
add_action( 'deactivate_cookie-notice/cookie-notice.php', __NAMESPACE__ . '\\deactivate', 21 );


