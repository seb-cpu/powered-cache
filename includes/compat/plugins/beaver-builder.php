<?php
/**
 * Compat with Beaver Builder
 *
 * @package SwiftPress\Compat
 * @link    https://www.wpbeaverbuilder.com/
 */

namespace SwiftPress\Compat\BeaverBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Dont use file optimizer when the page on builder mode.
 *
 * @since 2.5
 */
if ( isset( $_GET['fl_builder'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	add_filter( 'swiftpress_fo_disable', '__return_true' );
}
