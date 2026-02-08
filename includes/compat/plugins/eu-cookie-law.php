<?php
/**
 * Compat with EU Cookie Law for GDPR/CCPA Plugin
 *
 * @package SwiftPress\Compat
 * @link    https://wordpress.org/plugins/eu-cookie-law/
 */

namespace SwiftPress\Compat\EUCookieLaw;

use SwiftPress\Config;
use function SwiftPress\Utils\clean_site_cache_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( function_exists( '\eucookie_start' ) ) {
	add_filter( 'swiftpress_mod_rewrite', '__return_false', 22 );
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
	$options = get_option( 'peadig_eucookie' );

	if ( ! empty( $options['enabled'] ) ) {
		$cookies[] = 'euCookie';
	}

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
	remove_filter( 'swiftpress_mod_rewrite', '__return_false', 22 );
	remove_filter( 'swiftpress_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
	$settings = \SwiftPress\Utils\get_settings();
	Config::factory()->save_configuration( $settings, SWIFTPRESS_IS_NETWORK );
	clean_site_cache_dir();
}

add_action( 'activate_eu-cookie-law/eu-cookie-law.php', __NAMESPACE__ . '\\activate', 22 );
add_action( 'deactivate_eu-cookie-law/eu-cookie-law.php', __NAMESPACE__ . '\\deactivate', 22 );


