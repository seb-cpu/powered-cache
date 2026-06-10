<?php
/**
 * Bloat control: small, surgical switches that remove WordPress baggage most
 * sites never use. Each toggle is independent and reversible.
 *
 *  - disable_xmlrpc:          turns off the XML-RPC API + pingback surface
 *                             (a brute-force/amplification target nobody on a
 *                             modern site needs; the REST API is unaffected).
 *  - disable_jquery_migrate:  drops the jquery-migrate shim on the FRONT END
 *                             (admin keeps it for old plugin screens).
 *  - disable_dashicons_guests: stops loading the dashicons icon font for
 *                             logged-out visitors (themes rarely use it).
 *  - disable_cart_fragments:  stops WooCommerce's cart-fragments AJAX on
 *                             non-shop pages (a classic uncached-request tax);
 *                             only applies when WooCommerce is active.
 *
 * @package SwiftPress
 */

namespace SwiftPress;

use function SwiftPress\Utils\get_settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BloatControl
 */
class BloatControl {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings = [];

	/**
	 * Constructor (placeholder, mirrors codebase style).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return BloatControl
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
	 * Register the enabled removals.
	 *
	 * @return void
	 */
	public function setup() {
		$this->settings = get_settings();

		if ( ! empty( $this->settings['disable_xmlrpc'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', [ $this, 'strip_pingback_header' ] );
			add_filter( 'pings_open', '__return_false', 20 );
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( ! empty( $this->settings['disable_jquery_migrate'] ) ) {
			add_action( 'wp_default_scripts', [ $this, 'strip_jquery_migrate' ] );
		}

		if ( ! empty( $this->settings['disable_dashicons_guests'] ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_dashicons' ], 100 );
		}

		if ( ! empty( $this->settings['disable_cart_fragments'] ) && class_exists( 'WooCommerce' ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_cart_fragments' ], 100 );
		}
	}

	/**
	 * Remove the X-Pingback response header.
	 *
	 * @param array $headers Response headers.
	 *
	 * @return array
	 */
	public function strip_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );

		return $headers;
	}

	/**
	 * Drop jquery-migrate from the front-end jQuery bundle only.
	 *
	 * @param \WP_Scripts $scripts Default scripts registry.
	 *
	 * @return void
	 */
	public function strip_jquery_migrate( $scripts ) {
		if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
			return;
		}

		$jquery = $scripts->registered['jquery'];
		if ( is_array( $jquery->deps ) ) {
			$jquery->deps = array_values( array_diff( $jquery->deps, [ 'jquery-migrate' ] ) );
		}
	}

	/**
	 * Dashicons off for logged-out visitors (the admin bar needs it when
	 * logged in, so guests only).
	 *
	 * @return void
	 */
	public function dequeue_dashicons() {
		if ( is_user_logged_in() ) {
			return;
		}
		wp_dequeue_style( 'dashicons' );
		wp_deregister_style( 'dashicons' );
	}

	/**
	 * WooCommerce cart fragments only where a cart actually lives.
	 *
	 * @return void
	 */
	public function dequeue_cart_fragments() {
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			return;
		}
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return;
		}
		wp_dequeue_script( 'wc-cart-fragments' );
	}
}
