<?php
/**
 * Database optimization: clean the cruft WordPress accumulates and keep the
 * tables lean — the "table stakes" feature every major cache plugin ships.
 *
 * What it cleans (deliberately conservative — nothing a site owner could miss):
 *  - post revisions (keeps the newest 3 per post)
 *  - auto-drafts older than 7 days
 *  - trashed posts older than 30 days (WP would purge them itself at 30 days)
 *  - spam + trashed comments
 *  - expired transients (never the live ones)
 *  - OPTIMIZE TABLE on WordPress core tables that report overhead
 *
 * Runs on demand from the admin (batched AJAX) or weekly via WP-Cron when
 * `db_scheduled_cleanup` is enabled.
 *
 * @package SwiftPress
 */

namespace SwiftPress;

use function SwiftPress\Utils\get_settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DatabaseOptimizer
 */
class DatabaseOptimizer {

	/**
	 * Weekly cleanup cron hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'swiftpress_db_cleanup';

	/**
	 * Revisions kept per post.
	 *
	 * @var int
	 */
	const KEEP_REVISIONS = 3;

	/**
	 * Constructor (placeholder, mirrors codebase style).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return DatabaseOptimizer
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
	 * Schedule/unschedule the weekly cleanup per the setting.
	 *
	 * @return void
	 */
	public function setup() {
		add_action( self::CRON_HOOK, [ $this, 'run_all' ] );

		$settings = get_settings();
		if ( ! empty( $settings['db_scheduled_cleanup'] ) ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK );
			}
		} elseif ( wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * IDs of revisions beyond the newest N per post. Computed in PHP (capped)
	 * so it works on MySQL 5.7 / MariaDB without window functions.
	 *
	 * @param int $cap Max rows scanned.
	 *
	 * @return int[]
	 */
	private function stale_revision_ids( $cap = 5000 ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent FROM {$wpdb->posts}
				 WHERE post_type = 'revision'
				 ORDER BY post_parent ASC, post_modified_gmt DESC
				 LIMIT %d",
				$cap
			)
		);

		$ids      = [];
		$per_post = [];
		foreach ( (array) $rows as $row ) {
			$parent              = (int) $row->post_parent;
			$per_post[ $parent ] = isset( $per_post[ $parent ] ) ? $per_post[ $parent ] + 1 : 1;
			if ( $per_post[ $parent ] > self::KEEP_REVISIONS ) {
				$ids[] = (int) $row->ID;
			}
		}

		return $ids;
	}

	/**
	 * Current counts of cleanable items (cheap SELECT COUNTs for the UI).
	 *
	 * @return array<string,int>
	 */
	public function stats() {
		global $wpdb;

		$revisions = count( $this->stale_revision_ids() );

		$auto_drafts = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_modified_gmt < (UTC_TIMESTAMP() - INTERVAL 7 DAY)"
		);

		$trashed_posts = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_modified_gmt < (UTC_TIMESTAMP() - INTERVAL 30 DAY)"
		);

		$spam_comments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')"
		);

		$expired_transients = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < UNIX_TIMESTAMP()",
				$wpdb->esc_like( '_transient_timeout_' ) . '%'
			)
		);

		return [
			'revisions'          => $revisions,
			'auto_drafts'        => $auto_drafts,
			'trashed_posts'      => $trashed_posts,
			'spam_comments'      => $spam_comments,
			'expired_transients' => $expired_transients,
		];
	}

	/**
	 * Run every cleanup task; returns per-task removal counts.
	 *
	 * @return array<string,int>
	 */
	public function run_all() {
		$out = [];
		foreach ( [ 'revisions', 'auto_drafts', 'trashed_posts', 'spam_comments', 'expired_transients', 'optimize_tables' ] as $task ) {
			$out[ $task ] = $this->run( $task );
		}

		return $out;
	}

	/**
	 * Run one cleanup task.
	 *
	 * @param string $task Task id.
	 *
	 * @return int Items removed (tables optimized for optimize_tables).
	 */
	public function run( $task ) {
		global $wpdb;

		switch ( $task ) {
			case 'revisions':
				$ids = array_slice( $this->stale_revision_ids(), 0, 500 );
				foreach ( $ids as $id ) {
					wp_delete_post_revision( (int) $id );
				}

				return count( $ids );

			case 'auto_drafts':
				$ids = $wpdb->get_col(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_modified_gmt < (UTC_TIMESTAMP() - INTERVAL 7 DAY) LIMIT 500"
				);
				foreach ( $ids as $id ) {
					wp_delete_post( (int) $id, true );
				}

				return count( $ids );

			case 'trashed_posts':
				$ids = $wpdb->get_col(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_modified_gmt < (UTC_TIMESTAMP() - INTERVAL 30 DAY) LIMIT 500"
				);
				foreach ( $ids as $id ) {
					wp_delete_post( (int) $id, true );
				}

				return count( $ids );

			case 'spam_comments':
				$ids = $wpdb->get_col(
					"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash') LIMIT 1000"
				);
				foreach ( $ids as $id ) {
					wp_delete_comment( (int) $id, true );
				}

				return count( $ids );

			case 'expired_transients':
				// Use core's cleaner when present (multisite-aware), then sweep.
				if ( function_exists( 'delete_expired_transients' ) ) {
					delete_expired_transients( true );

					return 1;
				}
				$names = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < UNIX_TIMESTAMP() LIMIT 1000",
						$wpdb->esc_like( '_transient_timeout_' ) . '%'
					)
				);
				foreach ( $names as $timeout ) {
					$key = substr( $timeout, strlen( '_transient_timeout_' ) );
					delete_transient( $key );
				}

				return count( $names );

			case 'optimize_tables':
				$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
				$done   = 0;
				foreach ( $tables as $table ) {
					// Table names come from SHOW TABLES on our own prefix — not user input.
					$wpdb->query( "OPTIMIZE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$done++;
				}

				return $done;
		}

		return 0;
	}
}
