<?php
/**
 * AI module orchestrator / hook + endpoint registrar.
 *
 * The ONLY class in the module that registers hooks. Registration is admin-only
 * (is_admin() / wp_doing_ajax()); there are NO front-end hooks — the front-end cache
 * path stays 100% zero-LLM.
 *
 * @package SwiftPress
 */

namespace SwiftPress\AI;

use const SwiftPress\Constants\MENU_SLUG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AI
 */
class AI {

	/**
	 * AJAX/nonce action name.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'swiftpress_ai';

	/**
	 * Sentinel value meaning "delete the stored key".
	 *
	 * @var string
	 */
	const CLEAR_SENTINEL = '__CLEAR__';

	/**
	 * Constructor (placeholder).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return AI
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
	 * Register hooks. Admin-only; never on the front end.
	 *
	 * @return void
	 */
	public function setup() {
		// H1/H3: self-declare the module's secret keys to the core strip lists (append-only).
		add_filter(
			'swiftpress_secret_strip_keys',
			static function ( $keys ) {
				return array_values( array_unique( array_merge( (array) $keys, KeyStore::SECRET_KEYS ) ) );
			}
		);

		// Invalidate diagnostic cache whenever settings are saved (any path).
		add_action(
			'swiftpress_settings_saved',
			static function () {
				Diagnostic::factory()->invalidate_cache();
			}
		);

		// Hard gate: only register the rest in admin or admin-ajax context.
		if ( ! is_admin() && ! ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return;
		}

		// 5 core AJAX actions.
		add_action( 'wp_ajax_swiftpress_ai_run_diagnostic', [ $this, 'ajax_run_diagnostic' ] );
		add_action( 'wp_ajax_swiftpress_ai_apply', [ $this, 'ajax_apply' ] );
		add_action( 'wp_ajax_swiftpress_ai_undo', [ $this, 'ajax_undo' ] );
		add_action( 'wp_ajax_swiftpress_ai_save_key', [ $this, 'ajax_save_key' ] );
		add_action( 'wp_ajax_swiftpress_ai_status', [ $this, 'ajax_status' ] );

		// Enqueue + localize on the settings page only.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

		// H10: decryption-failure admin notice.
		add_action( 'admin_notices', [ $this, 'maybe_decryption_notice' ] );
		add_action( 'network_admin_notices', [ $this, 'maybe_decryption_notice' ] );
	}

	/* -----------------------------------------------------------------
	 * Gate
	 * ----------------------------------------------------------------- */

	/**
	 * Single capability-before-nonce, network-aware gate used by every handler.
	 *
	 * H8: reads the nonce from POST or GET so read-only (GET) handlers also work.
	 *
	 * @param string $nonce_action Nonce action.
	 *
	 * @return void Sends a JSON error and exits on failure.
	 */
	private function guard( $nonce_action = self::NONCE_ACTION ) {
		$cap = ( defined( 'SWIFTPRESS_IS_NETWORK' ) && SWIFTPRESS_IS_NETWORK ) ? 'manage_network' : 'manage_options';

		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'swiftpress' ) ], 403 );
		}

		$nonce = '';
		if ( isset( $_POST['nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
		} elseif ( isset( $_GET['nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ) );
		}

		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'swiftpress' ) ], 400 );
		}
	}

	/* -----------------------------------------------------------------
	 * Enqueue
	 * ----------------------------------------------------------------- */

	/**
	 * Enqueue + localize the AI bundle on the SwiftPress settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue( $hook ) {
		unset( $hook ); // gate on the page query var to match core.php behavior.

		if ( empty( $_GET['page'] ) || MENU_SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$key_store = KeyStore::factory();
		$budget    = Budget::factory();
		$snapshot  = Snapshot::factory();

		// Register a handle without a physical file dependency so localize has a target.
		// The real UI bundle is wired by the dashboard revamp; this only carries config.
		if ( ! wp_script_is( 'swiftpress-ai', 'registered' ) ) {
			wp_register_script( 'swiftpress-ai', '', [ 'jquery' ], SWIFTPRESS_VERSION, true );
		}
		wp_enqueue_script( 'swiftpress-ai' );

		wp_localize_script(
			'swiftpress-ai',
			'swiftpressAI',
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
				'hasKey'      => $key_store->has_key(),
				'keySource'   => $key_store->source(),
				'hasSnapshot' => $snapshot->has_snapshot(),
				'monthlyCap'  => $budget->monthly_cap(),
				'spend'       => round( $budget->month_to_date(), 4 ),
				'clearToken'  => self::CLEAR_SENTINEL,
				'i18n'        => [
					'analyzing'   => esc_html__( 'Analyzing your site…', 'swiftpress' ),
					'applying'    => esc_html__( 'Applying…', 'swiftpress' ),
					'reverting'   => esc_html__( 'Reverting…', 'swiftpress' ),
					'undoWarning' => esc_html__( 'This reverts ALL settings to the state before the AI changes, including any manual changes made since then.', 'swiftpress' ),
					'error'       => esc_html__( 'An error occurred. Please try again.', 'swiftpress' ),
				],
			]
		);
	}

	/* -----------------------------------------------------------------
	 * Handlers
	 * ----------------------------------------------------------------- */

	/**
	 * Run PSI + LLM (or rules fallback) and return the normalized payload.
	 *
	 * @return void
	 */
	public function ajax_run_diagnostic() {
		$this->guard();

		$budget = Budget::factory();
		$force  = ! empty( $_POST['force'] );

		// Debounce only fresh (non-forced is still debounced to prevent rapid spend).
		if ( $budget->is_debounced() ) {
			wp_send_json_success(
				[
					'meta' => [
						'debounced'         => true,
						'debounce_remaining' => $budget->debounce_remaining(),
					],
				]
			);
		}

		$budget->start_debounce();

		$payload = Diagnostic::factory()->run( (bool) $force );

		wp_send_json_success( $payload );
	}

	/**
	 * Apply a user-confirmed subset of appliable changes through the real save path.
	 *
	 * @return void
	 */
	public function ajax_apply() {
		$this->guard();

		$raw_changes = isset( $_POST['changes'] ) ? wp_unslash( $_POST['changes'] ) : '';
		$changes     = $this->parse_changes( $raw_changes );

		if ( empty( $changes ) ) {
			wp_send_json_error( [ 'message' => __( 'No changes to apply.', 'swiftpress' ) ], 400 );
		}

		$old = \SwiftPress\Utils\get_settings();

		// Re-run the gate server-side against the submitted changes — never trust the
		// client to have validated. This re-derives `from` and drops anything invalid.
		$gated = ValidationGate::factory()->filter( $changes, $old );

		if ( empty( $gated['appliable'] ) ) {
			wp_send_json_error( [ 'message' => __( 'None of the requested changes are valid to apply.', 'swiftpress' ) ], 400 );
		}

		// Build the candidate full settings array = old merged with appliable `to` values.
		$candidate = $old;
		$applied   = [];
		foreach ( $gated['appliable'] as $change ) {
			$candidate[ $change['setting_key'] ] = $change['to'];
			$applied[]                           = $change;
		}

		// Snapshot BEFORE applying (settings-only ring buffer).
		$metrics_before = isset( $_POST['metrics_before'] ) ? $this->parse_changes( wp_unslash( $_POST['metrics_before'] ) ) : [];
		$snapshot_id    = Snapshot::factory()->push( $old, $applied, is_array( $metrics_before ) ? $metrics_before : [] );

		// Commit via the EXACT existing save path (sanitize -> update_option ->
		// save_configuration -> swiftpress_settings_saved). This also invalidates the
		// diagnostic cache through the settings_saved hook registered in setup().
		Snapshot::commit( $candidate, $old );

		/**
		 * Fires after AI changes are applied.
		 *
		 * @hook  swiftpress_ai_applied
		 *
		 * @param {array}  $applied     Applied changes.
		 * @param {string} $snapshot_id Snapshot id (for undo).
		 *
		 * @since 1.0
		 */
		do_action( 'swiftpress_ai_applied', $applied, $snapshot_id );

		wp_send_json_success(
			[
				'applied'     => $applied,
				'snapshot_id' => $snapshot_id,
				'hasSnapshot' => true,
			]
		);
	}

	/**
	 * Restore the last (or a specific) pre-apply snapshot.
	 *
	 * @return void
	 */
	public function ajax_undo() {
		$this->guard();

		$snapshot = Snapshot::factory();

		$id = isset( $_POST['snapshot_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snapshot_id'] ) ) : '';
		if ( '' === $id ) {
			$latest = $snapshot->latest();
			if ( null === $latest ) {
				wp_send_json_error( [ 'message' => __( 'There is nothing to undo.', 'swiftpress' ) ], 400 );
			}
			$id = $latest['id'];
		}

		$result = $snapshot->restore( $id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}

		wp_send_json_success(
			[
				'restored'    => true,
				'hasSnapshot' => $snapshot->has_snapshot(),
			]
		);
	}

	/**
	 * Encrypt + store / clear the OpenRouter (and optionally PSI) key. Never echoes it.
	 *
	 * @return void
	 */
	public function ajax_save_key() {
		$this->guard();

		$key_store = KeyStore::factory();

		// OpenRouter key. Empty submit = keep existing; sentinel = clear.
		if ( isset( $_POST['openrouter_key'] ) ) {
			$raw = trim( (string) wp_unslash( $_POST['openrouter_key'] ) );

			if ( self::CLEAR_SENTINEL === $raw ) {
				$key_store->clear_key();
			} elseif ( '' !== $raw ) {
				$result = $key_store->set_key( $raw );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
				}
			}
		}

		// Optional PSI key, same conventions.
		if ( isset( $_POST['psi_key'] ) ) {
			$raw_psi = trim( (string) wp_unslash( $_POST['psi_key'] ) );

			if ( self::CLEAR_SENTINEL === $raw_psi ) {
				$key_store->clear_psi_key();
			} elseif ( '' !== $raw_psi ) {
				$result = $key_store->set_psi_key( $raw_psi );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
				}
			}
		}

		// Optional monthly cap.
		if ( isset( $_POST['monthly_cap_usd'] ) && is_numeric( $_POST['monthly_cap_usd'] ) ) {
			$this->save_monthly_cap( (float) $_POST['monthly_cap_usd'] );
		}

		// Return only non-sensitive status.
		wp_send_json_success(
			[
				'hasKey'    => $key_store->has_key(),
				'keySource' => $key_store->source(),
			]
		);
	}

	/**
	 * Lightweight status (never returns the key value).
	 *
	 * @return void
	 */
	public function ajax_status() {
		$this->guard();

		$key_store = KeyStore::factory();
		$budget    = Budget::factory();
		$snapshot  = Snapshot::factory();

		wp_send_json_success(
			[
				'has_key'            => $key_store->has_key(),
				'source'             => $key_store->source(),
				'spend'              => round( $budget->month_to_date(), 4 ),
				'monthly_cap'        => $budget->monthly_cap(),
				'reset_date'         => $budget->reset_date(),
				'has_snapshot'       => $snapshot->has_snapshot(),
				'debounce_remaining' => $budget->debounce_remaining(),
				'decryption_failed'  => $key_store->decryption_failed(),
			]
		);
	}

	/* -----------------------------------------------------------------
	 * Admin notice (H10)
	 * ----------------------------------------------------------------- */

	/**
	 * Show a clear notice when a stored key cannot be decrypted (salt rotation).
	 *
	 * @return void
	 */
	public function maybe_decryption_notice() {
		if ( empty( $_GET['page'] ) || MENU_SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$cap = ( defined( 'SWIFTPRESS_IS_NETWORK' ) && SWIFTPRESS_IS_NETWORK ) ? 'manage_network' : 'manage_options';
		if ( ! current_user_can( $cap ) ) {
			return;
		}

		if ( ! KeyStore::factory()->decryption_failed() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html( KeyStore::decryption_failure_message() )
		);
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------- */

	/**
	 * Decode a changes/metrics payload that may arrive as a JSON string or an array.
	 *
	 * @param mixed $raw Raw POST value.
	 *
	 * @return array
	 */
	private function parse_changes( $raw ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return [];
	}

	/**
	 * Persist the monthly cap into the secrets store (alongside the keys).
	 *
	 * @param float $cap Monthly cap (USD).
	 *
	 * @return void
	 */
	private function save_monthly_cap( $cap ) {
		$cap = max( 0, (float) $cap );

		$is_network = defined( 'SWIFTPRESS_IS_NETWORK' ) && SWIFTPRESS_IS_NETWORK;
		$store      = $is_network ? get_site_option( KeyStore::OPTION, [] ) : get_option( KeyStore::OPTION, [] );
		if ( ! is_array( $store ) ) {
			$store = [];
		}

		$store['monthly_cap_usd'] = $cap;

		if ( $is_network ) {
			update_site_option( KeyStore::OPTION, $store );
		} else {
			update_option( KeyStore::OPTION, $store );
		}
	}
}
