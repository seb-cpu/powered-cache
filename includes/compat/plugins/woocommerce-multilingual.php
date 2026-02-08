<?php
/**
 * Compat with WooCommerce Multilingual & Multicurrency with WPML
 *
 * @package SwiftPress\Compat
 * @link    https://wordpress.org/plugins/woocommerce-multilingual/
 * @since   2.4
 */

namespace SwiftPress\Compat\WoocommerceMultilingual;

use SwiftPress\Config;
use function SwiftPress\Utils\clean_site_cache_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( defined( 'WCML_VERSION' ) ) {
	add_filter( 'swiftpress_mod_rewrite', '__return_false', 25 );
	add_filter( 'swiftpress_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
	add_filter( 'wcml_user_store_strategy', __NAMESPACE__ . '\\use_cookie_strategy', 10, 2 );
}

/**
 * Force using the cookie strategy
 *
 * @return string
 */
function use_cookie_strategy() {
	return 'cookie';
}


/**
 * Add cookie to vary cookie options
 *
 * @param array $cookies The list of vary cookies
 *
 * @return array Altered cookie list.
 * @since 2.4
 */
function add_vary_cookie( $cookies ) {
	$cookies[] = 'wcml_client_currency';
	$cookies[] = 'wcml_client_currency_language';
	$cookies[] = 'wcml_client_country';

	return $cookies;
}

/**
 * Setup vary cookie on activation
 *
 * @since 2.4
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
 * @since 2.4
 */
function deactivate() {
	remove_filter( 'swiftpress_mod_rewrite', '__return_false', 25 );
	remove_filter( 'swiftpress_vary_cookies', __NAMESPACE__ . '\\add_vary_cookie' );
	$settings = \SwiftPress\Utils\get_settings();
	Config::factory()->save_configuration( $settings, SWIFTPRESS_IS_NETWORK );
	clean_site_cache_dir();
}

add_action( 'activate_woocommerce-multilingual/wpml-woocommerce.php', __NAMESPACE__ . '\\activate', 25 );
add_action( 'deactivate_woocommerce-multilingual/wpml-woocommerce.php', __NAMESPACE__ . '\\deactivate', 25 );
