<?php
/**
 * Compat with BricksBuilder
 *
 * @package SwiftPress\Compat
 * @link    https://bricksbuilder.io/
 */

namespace SwiftPress\Compat\Bricks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Don't use file optimizer when the page on builder mode.
 *
 * @since 3.2
 */
if ( isset( $_GET['bricks'] ) || isset( $_GET['bricks_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	add_filter( 'swiftpress_fo_disable', '__return_true' );
}
