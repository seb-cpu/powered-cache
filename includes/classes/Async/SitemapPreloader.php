<?php
/**
 * Sitemap auto-detection, parsing, and preload queue management
 *
 * @package SwiftPress\Async
 * @since   3.8
 */

namespace SwiftPress\Async;

use function SwiftPress\Utils\get_settings;
use function SwiftPress\Utils\log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SitemapPreloader
 *
 * Discovers sitemaps (robots.txt, core, Yoast, generic), parses them
 * recursively, stores URLs in a custom DB table, feeds them into the
 * CachePreloader background process in configurable batches, and
 * tracks progress for the admin UI.
 *
 * @since 3.8
 */
class SitemapPreloader {

	/**
	 * Plugin settings
	 *
	 * @var array
	 */
	private $settings = [];

	/**
	 * Maximum URLs to collect from sitemaps
	 *
	 * @var int
	 */
	const MAX_URLS = 10000;

	/**
	 * Total time limit for sitemap fetching (seconds)
	 *
	 * @var int
	 */
	const FETCH_TIMEOUT = 30;

	/**
	 * Batch size for processing URLs per cron run
	 *
	 * @var int
	 */
	const BATCH_SIZE = 25;

	/**
	 * Maximum consecutive failures before marking URL as 'failed'
	 *
	 * @var int
	 */
	const MAX_FAILURES = 3;

	/**
	 * Transient key for cached detected sitemap URL
	 *
	 * @var string
	 */
	const SITEMAP_TRANSIENT = 'swiftpress_detected_sitemap_url';

	/**
	 * Cron hook for daily sitemap refresh
	 *
	 * @var string
	 */
	const REFRESH_CRON_HOOK = 'swiftpress_sitemap_refresh';

	/**
	 * Cron hook for batch processing
	 *
	 * @var string
	 */
	const BATCH_CRON_HOOK = 'swiftpress_sitemap_batch_preload';

	/**
	 * Timestamp when fetching started (for timeout enforcement)
	 *
	 * @var float
	 */
	private $fetch_start_time = 0;

	/**
	 * Collected URLs count (for MAX_URLS enforcement)
	 *
	 * @var int
	 */
	private $collected_count = 0;

	/**
	 * Return an instance of the current class (singleton)
	 *
	 * @return SitemapPreloader
	 * @since  3.8
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
	 * Setup routine – register hooks when sitemap preloading is enabled
	 *
	 * @since 3.8
	 */
	public function setup() {
		$this->settings = get_settings();

		// Register cron hooks regardless of enabled state (needed for cleanup)
		add_action( self::REFRESH_CRON_HOOK, [ $this, 'refresh_sitemap_urls' ] );
		add_action( self::BATCH_CRON_HOOK, [ $this, 'process_batch' ] );

		if ( empty( $this->settings['enable_sitemap_preload'] ) || empty( $this->settings['enable_cache_preload'] ) ) {
			// Unschedule if previously scheduled
			$this->unschedule_crons();
			return;
		}

		// Schedule daily sitemap refresh (Req 6)
		if ( ! wp_next_scheduled( self::REFRESH_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::REFRESH_CRON_HOOK );
		}

		// Schedule batch processing
		$this->ensure_batch_cron();

		// Re-crawl completed URLs when cache is purged (Req 4)
		add_action( 'swiftpress_purge_all_cache', [ $this, 'reset_completed_urls' ] );
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 1 – Sitemap Auto-Detection
	// ──────────────────────────────────────────────────────────────

	/**
	 * Detect the sitemap URL, checking locations in priority order.
	 * Returns cached result if available, or performs fresh detection.
	 *
	 * @param bool $force_refresh Skip transient cache
	 *
	 * @return string Sitemap URL or empty string
	 * @since  3.8
	 */
	public function detect_sitemap_url( $force_refresh = false ) {
		// Manual override via settings (Req 1 – custom URL field)
		if ( ! empty( $this->settings['preload_sitemap'] ) ) {
			return $this->settings['preload_sitemap'];
		}

		// Check transient cache (24 hours)
		if ( ! $force_refresh ) {
			$cached = get_transient( self::SITEMAP_TRANSIENT );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$site_url    = trailingslashit( get_home_url() );
		$sitemap_url = '';

		// (a) robots.txt
		$sitemap_url = $this->detect_from_robots_txt( $site_url );

		// (b) WordPress core sitemap (WP 5.5+)
		if ( empty( $sitemap_url ) ) {
			$sitemap_url = $this->check_url_exists( $site_url . 'wp-sitemap.xml' );
		}

		// (c) Yoast SEO default
		if ( empty( $sitemap_url ) ) {
			$sitemap_url = $this->check_url_exists( $site_url . 'sitemap_index.xml' );
		}

		// (d) Generic / Rank Math / AIOSEO
		if ( empty( $sitemap_url ) ) {
			$sitemap_url = $this->check_url_exists( $site_url . 'sitemap.xml' );
		}

		// Cache result for 24 hours
		set_transient( self::SITEMAP_TRANSIENT, $sitemap_url, DAY_IN_SECONDS );

		if ( ! empty( $sitemap_url ) ) {
			log( sprintf( 'SitemapPreloader: detected sitemap at %s', $sitemap_url ) );
		} else {
			log( 'SitemapPreloader: no sitemap found' );
		}

		return $sitemap_url;
	}

	/**
	 * Parse robots.txt for Sitemap: directives
	 *
	 * @param string $site_url Site URL with trailing slash
	 *
	 * @return string First sitemap URL found, or empty string
	 * @since  3.8
	 */
	private function detect_from_robots_txt( $site_url ) {
		$response = wp_remote_get(
			$site_url . 'robots.txt',
			[
				'timeout'   => 10,
				'sslverify' => false,
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );

		if ( preg_match_all( '/^Sitemap:\s*(.+)$/mi', $body, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$url = trim( $url );
				if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
					return $url;
				}
			}
		}

		return '';
	}

	/**
	 * Check if a URL exists (returns 200)
	 *
	 * @param string $url URL to check
	 *
	 * @return string The URL if it exists, empty string otherwise
	 * @since  3.8
	 */
	private function check_url_exists( $url ) {
		$response = wp_remote_head(
			$url,
			[
				'timeout'   => 10,
				'sslverify' => false,
			]
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );

		return ( 200 === $code ) ? $url : '';
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 2 – Sitemap Parsing
	// ──────────────────────────────────────────────────────────────

	/**
	 * Parse a sitemap URL and return all discovered page URLs.
	 * Handles sitemap index files recursively and gzipped sitemaps.
	 *
	 * @param string $sitemap_url Root sitemap URL
	 *
	 * @return array List of page URLs
	 * @since  3.8
	 */
	public function parse_sitemap( $sitemap_url ) {
		$this->fetch_start_time = microtime( true );
		$this->collected_count  = 0;

		$urls = $this->parse_sitemap_recursive( $sitemap_url );

		log( sprintf( 'SitemapPreloader: parsed %d URLs from %s', count( $urls ), $sitemap_url ) );

		return $urls;
	}

	/**
	 * Recursively parse sitemap or sitemap index
	 *
	 * @param string $url Sitemap URL
	 *
	 * @return array Discovered URLs
	 * @since  3.8
	 */
	private function parse_sitemap_recursive( $url ) {
		// Enforce time and count limits
		if ( $this->is_fetch_timed_out() || $this->collected_count >= self::MAX_URLS ) {
			return [];
		}

		$xml_string = $this->fetch_sitemap_content( $url );

		if ( empty( $xml_string ) ) {
			return [];
		}

		// Suppress XML warnings
		$use_errors = libxml_use_internal_errors( true );
		$xml        = simplexml_load_string( $xml_string );
		libxml_use_internal_errors( $use_errors );

		if ( false === $xml ) {
			log( sprintf( 'SitemapPreloader: failed to parse XML from %s', $url ) );
			return [];
		}

		$urls = [];

		// Register namespaces (sitemaps use default namespace)
		$namespaces = $xml->getNamespaces( true );
		$ns         = '';
		if ( ! empty( $namespaces ) ) {
			// Use the first (default) namespace
			$ns = reset( $namespaces );
			$xml->registerXPathNamespace( 'sm', $ns );
		}

		// Check for sitemap index (contains <sitemap> entries)
		$sitemap_entries = $ns ? $xml->xpath( '//sm:sitemap/sm:loc' ) : $xml->xpath( '//sitemap/loc' );

		if ( ! empty( $sitemap_entries ) ) {
			foreach ( $sitemap_entries as $entry ) {
				if ( $this->is_fetch_timed_out() || $this->collected_count >= self::MAX_URLS ) {
					break;
				}

				$child_url  = trim( (string) $entry );
				$child_urls = $this->parse_sitemap_recursive( $child_url );
				$urls       = array_merge( $urls, $child_urls );
			}

			return $urls;
		}

		// Standard sitemap (contains <url><loc> entries)
		$url_entries = $ns ? $xml->xpath( '//sm:url/sm:loc' ) : $xml->xpath( '//url/loc' );

		if ( ! empty( $url_entries ) ) {
			foreach ( $url_entries as $entry ) {
				if ( $this->collected_count >= self::MAX_URLS ) {
					break;
				}

				$page_url = trim( (string) $entry );
				if ( filter_var( $page_url, FILTER_VALIDATE_URL ) ) {
					$urls[] = $page_url;
					$this->collected_count++;
				}
			}
		}

		return $urls;
	}

	/**
	 * Fetch sitemap content, handling gzipped sitemaps
	 *
	 * @param string $url Sitemap URL
	 *
	 * @return string XML string or empty on failure
	 * @since  3.8
	 */
	private function fetch_sitemap_content( $url ) {
		$response = wp_remote_get(
			$url,
			[
				'timeout'   => 10,
				'sslverify' => false,
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );

		// Handle gzipped sitemaps (.xml.gz)
		if ( preg_match( '/\.gz$/i', $url ) && function_exists( 'gzdecode' ) ) {
			$decoded = gzdecode( $body );
			if ( false !== $decoded ) {
				$body = $decoded;
			}
		}

		return $body;
	}

	/**
	 * Check if the total fetch time has exceeded the timeout
	 *
	 * @return bool
	 * @since  3.8
	 */
	private function is_fetch_timed_out() {
		return ( microtime( true ) - $this->fetch_start_time ) > self::FETCH_TIMEOUT;
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 3 – Database Storage
	// ──────────────────────────────────────────────────────────────

	/**
	 * Get the preload URLs table name (with prefix)
	 *
	 * @return string
	 * @since  3.8
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'swiftpress_preload_urls';
	}

	/**
	 * Create the custom table. Called from Install class.
	 *
	 * @since 3.8
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url varchar(2048) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			fail_count smallint(5) unsigned NOT NULL DEFAULT 0,
			last_crawled datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY url_idx (url(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		log( 'SitemapPreloader: table created/updated' );
	}

	/**
	 * Insert URLs into the table (ignores duplicates)
	 *
	 * @param array $urls List of URLs
	 *
	 * @return int Number of rows inserted
	 * @since  3.8
	 */
	public function insert_urls( $urls ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$inserted   = 0;

		foreach ( $urls as $url ) {
			$url = esc_url_raw( $url );

			if ( empty( $url ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table_name} (url, status, created_at) VALUES (%s, 'pending', NOW())", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$url
				)
			);

			if ( false !== $result && $result > 0 ) {
				$inserted++;
			}
		}

		return $inserted;
	}

	/**
	 * Remove URLs from the table that are no longer in the sitemap
	 *
	 * @param array $current_urls URLs currently in the sitemap
	 *
	 * @return int Number of rows deleted
	 * @since  3.8
	 */
	public function remove_stale_urls( $current_urls ) {
		global $wpdb;

		if ( empty( $current_urls ) ) {
			return 0;
		}

		$table_name   = self::get_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $current_urls ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE url NOT IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$current_urls
			)
		);

		return (int) $deleted;
	}

	/**
	 * Get a batch of pending URLs for preloading
	 *
	 * @param int $limit Number of URLs to fetch
	 *
	 * @return array Array of row objects with id and url
	 * @since  3.8
	 */
	public function get_pending_urls( $limit = self::BATCH_SIZE ) {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, url FROM {$table_name} WHERE status = 'pending' ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			)
		);
	}

	/**
	 * Mark a URL as completed
	 *
	 * @param int $id Row ID
	 *
	 * @since 3.8
	 */
	public function mark_completed( $id ) {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update(
			$table_name,
			[
				'status'       => 'completed',
				'fail_count'   => 0,
				'last_crawled' => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Record a failure for a URL. If failures exceed MAX_FAILURES, mark as 'failed'.
	 *
	 * @param int $id Row ID
	 *
	 * @since 3.8
	 */
	public function mark_failed( $id ) {
		global $wpdb;

		$table_name = self::get_table_name();

		// Increment fail_count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} SET fail_count = fail_count + 1, last_crawled = NOW() WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		// Check if we've exceeded max failures
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fail_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT fail_count FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		if ( $fail_count >= self::MAX_FAILURES ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table_name,
				[ 'status' => 'failed' ],
				[ 'id' => $id ],
				[ '%s' ],
				[ '%d' ]
			);
		}
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 4 – Preload Queue Integration
	// ──────────────────────────────────────────────────────────────

	/**
	 * Process a batch of pending URLs – called by wp_cron.
	 * Feeds URLs into the CachePreloader background process.
	 *
	 * @since 3.8
	 */
	public function process_batch() {
		$this->settings = get_settings();

		if ( empty( $this->settings['enable_sitemap_preload'] ) || empty( $this->settings['enable_cache_preload'] ) ) {
			return;
		}

		if ( empty( $this->settings['enable_page_cache'] ) ) {
			return;
		}

		$pending = $this->get_pending_urls( self::BATCH_SIZE );

		if ( empty( $pending ) ) {
			log( 'SitemapPreloader: no pending URLs to process' );
			return;
		}

		$preloader = CachePreloader::factory();

		foreach ( $pending as $row ) {
			$preloader->push_to_queue( $row->url );
			$this->mark_completed( $row->id );
		}

		$preloader->save()->dispatch();

		log( sprintf( 'SitemapPreloader: dispatched batch of %d URLs', count( $pending ) ) );

		// Ensure next batch is scheduled
		$this->ensure_batch_cron();
	}

	/**
	 * Get all sitemap URLs and add them to the preload queue.
	 * Called from Preloader::setup_preload_queue() when sitemap
	 * preloading is enabled.
	 *
	 * @since 3.8
	 */
	public function populate_from_sitemap() {
		$sitemap_url = $this->detect_sitemap_url();

		if ( empty( $sitemap_url ) ) {
			return;
		}

		$urls = $this->parse_sitemap( $sitemap_url );

		if ( empty( $urls ) ) {
			return;
		}

		// Insert into DB (new URLs as pending)
		$inserted = $this->insert_urls( $urls );

		// Remove stale URLs no longer in sitemap (Req 6)
		$deleted = $this->remove_stale_urls( $urls );

		log( sprintf( 'SitemapPreloader: inserted %d new, removed %d stale URLs', $inserted, $deleted ) );

		// Ensure batch cron is running
		$this->ensure_batch_cron();
	}

	/**
	 * Reset all completed URLs back to pending.
	 * Called when cache is purged (Req 4).
	 *
	 * @since 3.8
	 */
	public function reset_completed_urls() {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$updated = $wpdb->query(
			"UPDATE {$table_name} SET status = 'pending', fail_count = 0 WHERE status IN ('completed', 'failed')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( $updated > 0 ) {
			log( sprintf( 'SitemapPreloader: reset %d URLs to pending after cache purge', $updated ) );
			$this->ensure_batch_cron();
		}
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 5 – Progress Tracking
	// ──────────────────────────────────────────────────────────────

	/**
	 * Get preload statistics for the admin UI.
	 *
	 * @return array {
	 *     @type int    $total_urls          Total URLs in the table
	 *     @type int    $pending_count       URLs waiting to be crawled
	 *     @type int    $completed_count     URLs successfully crawled
	 *     @type int    $failed_count        URLs that exceeded max retries
	 *     @type string $last_run_timestamp  Last crawl datetime or empty
	 *     @type string $detected_sitemap_url Auto-detected sitemap URL
	 * }
	 * @since 3.8
	 */
	public function get_preload_stats() {
		global $wpdb;

		$table_name = self::get_table_name();

		// Check if table exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( ! $table_exists ) {
			return [
				'total_urls'          => 0,
				'pending_count'       => 0,
				'completed_count'     => 0,
				'failed_count'        => 0,
				'last_run_timestamp'  => '',
				'detected_sitemap_url' => $this->detect_sitemap_url(),
			];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total_urls,
				SUM( CASE WHEN status = 'pending' THEN 1 ELSE 0 END ) AS pending_count,
				SUM( CASE WHEN status = 'completed' THEN 1 ELSE 0 END ) AS completed_count,
				SUM( CASE WHEN status = 'failed' THEN 1 ELSE 0 END ) AS failed_count,
				MAX( last_crawled ) AS last_run_timestamp
			FROM {$table_name}" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return [
			'total_urls'           => (int) ( $stats->total_urls ?? 0 ),
			'pending_count'        => (int) ( $stats->pending_count ?? 0 ),
			'completed_count'      => (int) ( $stats->completed_count ?? 0 ),
			'failed_count'         => (int) ( $stats->failed_count ?? 0 ),
			'last_run_timestamp'   => $stats->last_run_timestamp ?? '',
			'detected_sitemap_url' => $this->detect_sitemap_url(),
		];
	}

	// ──────────────────────────────────────────────────────────────
	//  Requirement 6 – Refresh Cycle
	// ──────────────────────────────────────────────────────────────

	/**
	 * Re-detect and re-parse sitemaps (daily cron).
	 * Adds new URLs as pending, removes stale URLs.
	 *
	 * @since 3.8
	 */
	public function refresh_sitemap_urls() {
		$this->settings = get_settings();

		if ( empty( $this->settings['enable_sitemap_preload'] ) ) {
			return;
		}

		log( 'SitemapPreloader: daily refresh started' );

		// Force re-detection
		$sitemap_url = $this->detect_sitemap_url( true );

		if ( empty( $sitemap_url ) ) {
			return;
		}

		$urls = $this->parse_sitemap( $sitemap_url );

		if ( empty( $urls ) ) {
			return;
		}

		$inserted = $this->insert_urls( $urls );
		$deleted  = $this->remove_stale_urls( $urls );

		log( sprintf( 'SitemapPreloader: daily refresh – %d new, %d stale removed', $inserted, $deleted ) );
	}

	/**
	 * Manual sitemap refresh trigger (for admin UI button in Phase 6)
	 *
	 * @return array Stats after refresh
	 * @since 3.8
	 */
	public function manual_refresh() {
		// Clear transient to force re-detection
		delete_transient( self::SITEMAP_TRANSIENT );

		$this->refresh_sitemap_urls();

		return $this->get_preload_stats();
	}

	// ──────────────────────────────────────────────────────────────
	//  Cron Scheduling Helpers
	// ──────────────────────────────────────────────────────────────

	/**
	 * Ensure the batch processing cron is scheduled with the configured interval
	 *
	 * @since 3.8
	 */
	private function ensure_batch_cron() {
		if ( ! wp_next_scheduled( self::BATCH_CRON_HOOK ) ) {
			$interval = ! empty( $this->settings['preload_crawl_interval'] )
				? absint( $this->settings['preload_crawl_interval'] )
				: 60;

			wp_schedule_single_event( time() + $interval, self::BATCH_CRON_HOOK );
		}
	}

	/**
	 * Unschedule all sitemap-related cron events
	 *
	 * @since 3.8
	 */
	private function unschedule_crons() {
		$refresh_ts = wp_next_scheduled( self::REFRESH_CRON_HOOK );
		if ( $refresh_ts ) {
			wp_unschedule_event( $refresh_ts, self::REFRESH_CRON_HOOK );
		}

		$batch_ts = wp_next_scheduled( self::BATCH_CRON_HOOK );
		if ( $batch_ts ) {
			wp_unschedule_event( $batch_ts, self::BATCH_CRON_HOOK );
		}
	}
}
