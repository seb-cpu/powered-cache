<?php
/**
 * Settings snapshot store + restore (undo).
 *
 * Stores ONLY SETTING_OPTION values (never the AI key, which isn't in settings) as a
 * small ring buffer (last 5). Restore re-applies a stored settings array through the
 * exact existing save path.
 *
 * @package SwiftPress
 */

namespace SwiftPress\AI;

use const SwiftPress\Constants\SETTING_OPTION;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Snapshot
 */
class Snapshot {

	/**
	 * Snapshot store option.
	 *
	 * @var string
	 */
	const OPTION = 'swiftpress_ai_snapshots';

	/**
	 * Ring buffer size.
	 *
	 * @var int
	 */
	const MAX = 5;

	/**
	 * Constructor (placeholder).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return Snapshot
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Whether we operate on the network option store.
	 *
	 * @return bool
	 */
	private function is_network() {
		return defined( 'SWIFTPRESS_IS_NETWORK' ) && SWIFTPRESS_IS_NETWORK;
	}

	/**
	 * Read all snapshots (oldest first).
	 *
	 * @return array
	 */
	private function all() {
		$snapshots = $this->is_network() ? get_site_option( self::OPTION, [] ) : get_option( self::OPTION, [] );

		return is_array( $snapshots ) ? $snapshots : [];
	}

	/**
	 * Persist snapshots.
	 *
	 * @param array $snapshots Snapshots.
	 *
	 * @return void
	 */
	private function save( array $snapshots ) {
		if ( $this->is_network() ) {
			update_site_option( self::OPTION, $snapshots );
		} else {
			update_option( self::OPTION, $snapshots, false ); // never autoload 5 full settings arrays
		}
	}

	/**
	 * Push a pre-apply snapshot.
	 *
	 * @param array $settings        Settings before apply (SETTING_OPTION values only).
	 * @param array $applied_changes The changes about to be applied.
	 * @param array $metrics_before  Metrics that produced the proposal.
	 *
	 * @return string Snapshot id.
	 */
	public function push( array $settings, array $applied_changes = [], array $metrics_before = [] ) {
		$snapshots = $this->all();

		$id = uniqid( 'snap_', true );

		$snapshots[] = [
			'id'              => $id,
			'timestamp'       => time(),
			'settings'        => $settings,
			'applied_changes' => $applied_changes,
			'metrics_before'  => $metrics_before,
		];

		// Trim to ring buffer size (keep newest MAX).
		if ( count( $snapshots ) > self::MAX ) {
			$snapshots = array_slice( $snapshots, -self::MAX );
		}

		$this->save( $snapshots );

		return $id;
	}

	/**
	 * The most recent snapshot, or null.
	 *
	 * @return array|null
	 */
	public function latest() {
		$snapshots = $this->all();

		if ( empty( $snapshots ) ) {
			return null;
		}

		return end( $snapshots );
	}

	/**
	 * Whether at least one snapshot exists.
	 *
	 * @return bool
	 */
	public function has_snapshot() {
		return null !== $this->latest();
	}

	/**
	 * Get a snapshot by id.
	 *
	 * @param string $id Snapshot id.
	 *
	 * @return array|null
	 */
	public function get( $id ) {
		foreach ( $this->all() as $snapshot ) {
			if ( isset( $snapshot['id'] ) && $snapshot['id'] === $id ) {
				return $snapshot;
			}
		}

		return null;
	}

	/**
	 * Remove a snapshot by id.
	 *
	 * @param string $id Snapshot id.
	 *
	 * @return void
	 */
	public function remove( $id ) {
		$snapshots = array_values(
			array_filter(
				$this->all(),
				static function ( $snapshot ) use ( $id ) {
					return ! isset( $snapshot['id'] ) || $snapshot['id'] !== $id;
				}
			)
		);

		$this->save( $snapshots );
	}

	/**
	 * Restore a snapshot by id through the existing save path. Returns the restored
	 * settings, or WP_Error.
	 *
	 * @param string $id Snapshot id.
	 *
	 * @return array|\WP_Error
	 */
	public function restore( $id ) {
		$snapshot = $this->get( $id );

		if ( null === $snapshot || empty( $snapshot['settings'] ) || ! is_array( $snapshot['settings'] ) ) {
			return new \WP_Error( 'snapshot_not_found', __( 'That snapshot is no longer available.', 'swiftpress' ) );
		}

		$old = \SwiftPress\Utils\get_settings();
		$new = self::commit( $snapshot['settings'], $old );

		// Drop the restored snapshot so repeated undo doesn't loop on the same state.
		$this->remove( $id );

		/**
		 * Fires after an AI change is undone.
		 *
		 * @hook  swiftpress_ai_undone
		 *
		 * @param {string} $id  Snapshot id.
		 * @param {array}  $new Restored settings.
		 *
		 * @since 1.0
		 */
		do_action( 'swiftpress_ai_undone', $id, $new );

		return $new;
	}

	/**
	 * Commit a settings array through the EXACT existing save path:
	 * sanitize_options() -> update_option(SETTING_OPTION) -> Config::save_configuration()
	 * -> do_action('swiftpress_settings_saved').
	 *
	 * Shared by apply (AI::ajax_apply) and undo (Snapshot::restore).
	 *
	 * @param array $candidate The desired full settings array.
	 * @param array $old       The settings before this commit.
	 *
	 * @return array The sanitized, persisted settings.
	 */
	public static function commit( array $candidate, array $old ) {
		// Inherit all coercions from the real sanitizer.
		$new = \SwiftPress\Admin\Dashboard\sanitize_options( $candidate );

		if ( defined( 'SWIFTPRESS_IS_NETWORK' ) && SWIFTPRESS_IS_NETWORK ) {
			update_site_option( SETTING_OPTION, $new );
		} else {
			update_option( SETTING_OPTION, $new );
		}

		\SwiftPress\Config::factory()->save_configuration( $new, defined( 'SWIFTPRESS_IS_NETWORK' ) && SWIFTPRESS_IS_NETWORK );

		// Same transition side-effects as a manual save (preloader start/stop,
		// cache cleanup, cron) — AI apply/undo must not skip them.
		if ( function_exists( '\\SwiftPress\\Admin\\Dashboard\\apply_settings_transitions' ) ) {
			\SwiftPress\Admin\Dashboard\apply_settings_transitions( $old, $new );
		}

		/**
		 * Fires after saving configurations (identical to a manual save).
		 *
		 * @hook  swiftpress_settings_saved
		 *
		 * @param {array} $old Old settings.
		 * @param {array} $new New settings.
		 *
		 * @since 1.0
		 */
		do_action( 'swiftpress_settings_saved', $old, $new );

		return $new;
	}
}
