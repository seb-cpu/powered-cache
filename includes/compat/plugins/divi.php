<?php
/**
 * Compat with Divi Builder
 *
 * @package SwiftPress\Compat
 * @link    https://www.elegantthemes.com/
 */

namespace SwiftPress\Compat\Divi;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Dont use file optimizer when the page on builder mode.
 *
 * @since 2.5
 */
if ( ! empty( $_GET['et_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	add_filter( 'swiftpress_fo_disable', '__return_true' );
}
