<?php
/**
 * Compat with CookieYes | GDPR Cookie Consent & Compliance Notice (CCPA Ready)
 *
 * @package SwiftPress\Compat
 * @link    https://wordpress.org/plugins/cookie-law-info/
 */

namespace SwiftPress\Compat\CookieLawInfo;

use SwiftPress\Config;
use function SwiftPress\Utils\clean_site_cache_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( '\Cookie_Law_Info' ) ) {
	add_filter( 'swiftpress_mod_rewrite', '__return_false', 20 );
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
	$cookies[] = 'viewed_cookie_policy';
	$cookies[] = 'cookielawinfo-checkbox-necessary';
	$cookies[] = 'cookielawinfo-checkbox-functional';
	$cookies[] = 'cookielawinfo-checkbox-performance';
	$cookies[] = 'cookielawinfo-checkbox-analytics';
	$cookies[] = 'cookielawinfo-checkbox-advertisement';
	$cookies[] = 'cookielawinfo-checkbox-others';

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
	remove_filter( 'swiftpress_mod_rewrite', '__return_false', 20 );
	remove_filter( 'swiftpress_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
	$settings = \SwiftPress\Utils\get_settings();
	Config::factory()->save_configuration( $settings, SWIFTPRESS_IS_NETWORK );
	clean_site_cache_dir();
}

add_action( 'activate_cookie-law-info/cookie-law-info.php', __NAMESPACE__ . '\\activate', 20 );
add_action( 'deactivate_cookie-law-info/cookie-law-info.php', __NAMESPACE__ . '\\deactivate', 20 );


