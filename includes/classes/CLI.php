<?php
/**
 * WP-CLI commands for SwiftPress
 *
 * @package SwiftPress
 * @since   1.0.0
 */

namespace SwiftPress;

use SwiftPress\Async\SitemapPreloader;
use function SwiftPress\Utils\get_cache_dir;
use function SwiftPress\Utils\get_settings;
use function SwiftPress\Utils\remove_dir;
use function SwiftPress\Utils\swiftpress_flush;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage SwiftPress cache and performance settings.
 *
 * ## EXAMPLES
 *
 *     # Flush all cache
 *     $ wp swiftpress flush
 *     Success: Cache flushed successfully. 142 files removed.
 *
 *     # Flush font cache
 *     $ wp swiftpress flush fonts
 *     Success: Font cache cleared. Fonts will be re-downloaded on next page load.
 *
 *     # Show preload status
 *     $ wp swiftpress preload status
 *     +-------------------+----------------------------+
 *     | Metric            | Value                      |
 *     +-------------------+----------------------------+
 *     | Total URLs        | 847                        |
 *     | Completed         | 812                        |
 *     | Pending           | 30                         |
 *     | Failed            | 5                          |
 *     | Last Run          | 2026-02-08 14:30:00        |
 *     | Detected Sitemap  | https://example.com/s...   |
 *     +-------------------+----------------------------+
 *
 *     # Refresh sitemap
 *     $ wp swiftpress preload refresh
 *     Success: Sitemap refreshed. 847 URLs found.
 *
 *     # Show configuration status
 *     $ wp swiftpress status
 *
 * @since 1.0.0
 */
class CLI extends \WP_CLI_Command {

	/**
	 * Flush cache files.
	 *
	 * Clears all page cache files and file optimization cache.
	 * Optionally pass "fonts" to clear only the font cache.
	 *
	 * ## OPTIONS
	 *
	 * [<target>]
	 * : What to flush. Default is all cache. Use "fonts" to flush only font cache.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - fonts
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Flush all page and file optimization cache
	 *     $ wp swiftpress flush
	 *     Success: Cache flushed successfully. 142 files removed.
	 *
	 *     # Flush font cache only
	 *     $ wp swiftpress flush fonts
	 *     Success: Font cache cleared. Fonts will be re-downloaded on next page load.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function flush( $args = [], $assoc_args = [] ) {
		$target = isset( $args[0] ) ? $args[0] : 'all';

		if ( 'fonts' === $target ) {
			$this->flush_fonts();
			return;
		}

		// Count files before flush
		$count = $this->count_cache_files();

		swiftpress_flush();

		// Also clean file optimization cache
		if ( defined( 'SWIFTPRESS_FO_CACHE_DIR' ) && is_dir( SWIFTPRESS_FO_CACHE_DIR ) ) {
			remove_dir( SWIFTPRESS_FO_CACHE_DIR );
		}

		\WP_CLI::success(
			sprintf(
				'Cache flushed successfully. %d files removed.',
				$count
			)
		);
	}

	/**
	 * Flush font cache directory.
	 *
	 * @since 1.0.0
	 */
	private function flush_fonts() {
		$font_cache_dir = get_cache_dir() . 'swiftpress/fonts/';

		if ( is_dir( $font_cache_dir ) ) {
			remove_dir( $font_cache_dir );
			\WP_CLI::success( 'Font cache cleared. Fonts will be re-downloaded on next page load.' );
		} else {
			\WP_CLI::success( 'Font cache directory does not exist. Nothing to clear.' );
		}
	}

	/**
	 * Manage cache preloading.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The preload action to perform.
	 * ---
	 * options:
	 *   - status
	 *   - refresh
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show preload progress
	 *     $ wp swiftpress preload status
	 *
	 *     # Refresh sitemap and re-parse URLs
	 *     $ wp swiftpress preload refresh
	 *     Success: Sitemap refreshed. 847 URLs found.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function preload( $args = [], $assoc_args = [] ) {
		if ( empty( $args[0] ) ) {
			\WP_CLI::error( 'Please specify an action: status or refresh' );
		}

		$action = $args[0];

		switch ( $action ) {
			case 'status':
				$this->preload_status();
				break;
			case 'refresh':
				$this->preload_refresh();
				break;
			default:
				\WP_CLI::error( sprintf( 'Unknown action: %s. Use "status" or "refresh".', $action ) );
		}
	}

	/**
	 * Display preload progress as a WP-CLI table.
	 *
	 * @since 1.0.0
	 */
	private function preload_status() {
		$stats = SitemapPreloader::factory()->get_preload_stats();

		$items = [
			[
				'Metric' => 'Total URLs',
				'Value'  => $stats['total_urls'],
			],
			[
				'Metric' => 'Completed',
				'Value'  => $stats['completed_count'],
			],
			[
				'Metric' => 'Pending',
				'Value'  => $stats['pending_count'],
			],
			[
				'Metric' => 'Failed',
				'Value'  => $stats['failed_count'],
			],
			[
				'Metric' => 'Last Run',
				'Value'  => $stats['last_run_timestamp'] ?: '(never)',
			],
			[
				'Metric' => 'Detected Sitemap',
				'Value'  => $stats['detected_sitemap_url'] ?: '(none)',
			],
		];

		\WP_CLI\Utils\format_items( 'table', $items, [ 'Metric', 'Value' ] );
	}

	/**
	 * Trigger sitemap re-detection and re-parsing.
	 *
	 * @since 1.0.0
	 */
	private function preload_refresh() {
		$stats = SitemapPreloader::factory()->manual_refresh();

		\WP_CLI::success(
			sprintf(
				'Sitemap refreshed. %d URLs found.',
				$stats['total_urls']
			)
		);
	}

	/**
	 * Show current configuration summary and cache sizes.
	 *
	 * Displays the state of all major modules and cache directory sizes.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp swiftpress status
	 *     +---------------------+---------+
	 *     | Setting             | Value   |
	 *     +---------------------+---------+
	 *     | Page Cache          | ON      |
	 *     | File Optimization   | ON      |
	 *     | Font Optimization   | OFF     |
	 *     | Cache Preloader     | ON      |
	 *     | Gzip                | ON      |
	 *     | Cache directory     | 12.4 MB |
	 *     | Font cache          | 1.2 MB  |
	 *     | Total cached files  | 284     |
	 *     +---------------------+---------+
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args = [], $assoc_args = [] ) {
		$settings = get_settings();

		// Determine file optimization state — any of the file optimization
		// features counts as "on".
		$fo_on = ! empty( $settings['minify_html'] )
			|| ! empty( $settings['minify_css'] )
			|| ! empty( $settings['combine_css'] )
			|| ! empty( $settings['minify_js'] )
			|| ! empty( $settings['combine_js'] )
			|| ! empty( $settings['js_defer'] )
			|| ! empty( $settings['js_delay'] )
			|| ! empty( $settings['critical_css'] );

		$cache_dir      = get_cache_dir();
		$font_cache_dir = get_cache_dir() . 'swiftpress/fonts/';

		$cache_size      = $this->get_directory_size( $cache_dir );
		$font_cache_size = $this->get_directory_size( $font_cache_dir );
		$total_files     = $this->count_cache_files();

		$items = [
			[
				'Setting' => 'Page Cache',
				'Value'   => ! empty( $settings['enable_page_cache'] ) ? 'ON' : 'OFF',
			],
			[
				'Setting' => 'File Optimization',
				'Value'   => $fo_on ? 'ON' : 'OFF',
			],
			[
				'Setting' => 'Font Optimization',
				'Value'   => ! empty( $settings['enable_font_optimization'] ) ? 'ON' : 'OFF',
			],
			[
				'Setting' => 'Cache Preloader',
				'Value'   => ! empty( $settings['enable_cache_preload'] ) ? 'ON' : 'OFF',
			],
			[
				'Setting' => 'Gzip',
				'Value'   => ! empty( $settings['gzip_compression'] ) ? 'ON' : 'OFF',
			],
			[
				'Setting' => 'Cache directory',
				'Value'   => $this->format_size( $cache_size ),
			],
			[
				'Setting' => 'Font cache',
				'Value'   => $this->format_size( $font_cache_size ),
			],
			[
				'Setting' => 'Total cached files',
				'Value'   => $total_files,
			],
		];

		\WP_CLI\Utils\format_items( 'table', $items, [ 'Setting', 'Value' ] );
	}

	/**
	 * Count all cache files across the cache directory.
	 *
	 * @return int
	 */
	private function count_cache_files() {
		$cache_dir = get_cache_dir();
		$count     = 0;

		if ( ! is_dir( $cache_dir ) ) {
			return 0;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Calculate directory size in bytes.
	 *
	 * @param string $dir Directory path.
	 *
	 * @return int Size in bytes.
	 */
	private function get_directory_size( $dir ) {
		$size = 0;

		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$size += $file->getSize();
			}
		}

		return $size;
	}

	/**
	 * Format byte size into a human-readable string.
	 *
	 * @param int $bytes Size in bytes.
	 *
	 * @return string Formatted size (e.g. "12.4 MB").
	 */
	private function format_size( $bytes ) {
		if ( 0 === $bytes ) {
			return '0 B';
		}

		$units = [ 'B', 'KB', 'MB', 'GB' ];
		$i     = (int) floor( log( $bytes, 1024 ) );

		return round( $bytes / pow( 1024, $i ), 1 ) . ' ' . $units[ $i ];
	}
}
