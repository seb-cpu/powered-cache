<?php
/**
 * Compat with Cornerstone builder
 *
 * @package SwiftPress\Compat
 * @link    https://codecanyon.net/item/cornerstone-the-wordpress-page-builder/15518868
 */

namespace SwiftPress\Compat\CornerstoneBuilder;

if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
	return;
}

$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

/**
 * Cornerstone builder escapes form is_admin() checks
 * Dont use file optimizer when the page on builder mode.
 *
 * @since 2.2
 */
if ( false !== stripos( $request_uri, '/cornerstone/' ) ) {
	add_filter( 'swiftpress_fo_disable', '__return_true' );
}
