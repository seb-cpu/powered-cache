<?php
/**
 * Compat with ACF
 *
 * @package SwiftPress\Compat
 * @link    https://wordpress.org/plugins/advanced-custom-fields
 */

namespace SwiftPress\Compat\AdvancedCustomFields;

use function SwiftPress\Utils\clean_site_cache_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Delete page cache on ACF options save
 *
 * @param int|string $post_id of the updated page
 *
 * @link https://www.advancedcustomfields.com/resources/acf-save_post/
 */
function purge_cache_on_options_save( $post_id ) {
	if ( 'options' === $post_id ) {
		clean_site_cache_dir();
	}
}

add_action( 'acf/save_post', __NAMESPACE__ . '\\purge_cache_on_options_save' );
