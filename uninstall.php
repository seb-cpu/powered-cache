<?php
/**
 * Uninstall SwiftPress
 * Deletes all plugin related data and configurations
 *
 * @package SwiftPress
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once 'swiftpress.php';

// flush cache
\SwiftPress\Utils\swiftpress_flush();

if ( is_multisite() ) {
	$sites = get_sites();
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );
		\SwiftPress\Utils\log( sprintf( 'Uninstalling site %s', $site->blog_id ) );
		swiftpress_uninstall_site();
		restore_current_blog();
	}
} else {
	\SwiftPress\Utils\log( sprintf( 'Uninstalling...' ) );
	swiftpress_uninstall_site();
}

\SwiftPress\Config::factory()->define_wp_cache( false );

// delete advanced cache file
if ( file_exists( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' ) ) {
	\SwiftPress\Utils\log( sprintf( 'Removing: %s', 'advanced-cache.php' ) );

	unlink( untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php' );
}

// delete cache directory
if ( file_exists( \SwiftPress\Utils\get_cache_dir() ) ) {
	\SwiftPress\Utils\log( sprintf( 'Removing dir: %s', \SwiftPress\Utils\get_cache_dir() ) );
	\SwiftPress\Utils\remove_dir( \SwiftPress\Utils\get_cache_dir() );
}

// delete configuration files
if ( file_exists( WP_CONTENT_DIR . '/sp-config' ) ) {
	\SwiftPress\Utils\log( 'Removing config dir...' );
	\SwiftPress\Utils\remove_dir( WP_CONTENT_DIR . '/sp-config' );
}

// Phase 4: cleanup font cache directory
$font_cache_dir = WP_CONTENT_DIR . '/cache/fonts/';
if ( file_exists( $font_cache_dir ) ) {
	\SwiftPress\Utils\log( 'Removing font cache dir...' );
	\SwiftPress\Utils\remove_dir( $font_cache_dir );
}

/**
 * Uninstall SwiftPress
 *
 * @since 2.0
 */
function swiftpress_uninstall_site() {
	global $wpdb;

	// delete network settings
	delete_site_option( \SwiftPress\Constants\SETTING_OPTION );
	delete_site_option( 'swiftpress_db_version' );

	// delete site settings
	delete_option( \SwiftPress\Constants\SETTING_OPTION );
	delete_option( 'swiftpress_db_version' );

	// remove cron tasks
	wp_clear_scheduled_hook( \SwiftPress\Constants\PURGE_CACHE_CRON_NAME );
	wp_clear_scheduled_hook( \SwiftPress\Constants\PURGE_FO_CRON_NAME );
	wp_clear_scheduled_hook( \SwiftPress\Constants\DEFERRED_PRELOAD_QUEUE_CRON_NAME );

	// Sitemap preloader cron hooks
	wp_clear_scheduled_hook( \SwiftPress\Async\SitemapPreloader::REFRESH_CRON_HOOK );
	wp_clear_scheduled_hook( \SwiftPress\Async\SitemapPreloader::BATCH_CRON_HOOK );

	// Sitemap preloader transients
	delete_transient( \SwiftPress\Async\SitemapPreloader::SITEMAP_TRANSIENT );

	delete_site_transient( \SwiftPress\Constants\PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT );
	delete_transient( \SwiftPress\Constants\PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT );

	// AI module data — including the encrypted OpenRouter/PSI key ciphers. The
	// file docblock and readme both promise "all plugin data" is removed and that
	// the key can be deleted, so these must go too (they are autoloaded options
	// that WP core never cleans up on its own).
	foreach ( [ 'swiftpress_ai_secrets', 'swiftpress_ai_snapshots', 'swiftpress_ai_usage', 'swiftpress_ai_model', 'swiftpress_ai_last_result', 'swiftpress_tracking_sources' ] as $ai_option ) {
		delete_option( $ai_option );
		delete_site_option( $ai_option );
	}
	delete_transient( 'swiftpress_ai_debounce' );
	delete_transient( 'swiftpress_github_release' );

	// Self-hosted tracking copies + their refresh cron.
	wp_clear_scheduled_hook( 'swiftpress_tracking_refresh' );
	\SwiftPress\Utils\remove_dir( WP_CONTENT_DIR . '/cache/swiftpress/tracking/' );

	// Diagnostic response cache is a family of hashed transients — clear both the
	// values and their timeouts directly.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_swiftpress\_ai\_diag\_%' OR option_name LIKE '\_transient\_timeout\_swiftpress\_ai\_diag\_%'" ); // phpcs:ignore WordPress.DB

	// Phase 5: drop preload URLs table
	$table_name = $wpdb->prefix . 'swiftpress_preload_urls';
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore
}
