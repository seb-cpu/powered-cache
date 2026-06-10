<?php
/**
 * Cost controls: monthly spend cap, per-action token ceilings, debounce, usage ledger.
 *
 * @package SwiftPress
 */

namespace SwiftPress\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Budget
 */
class Budget {

	/**
	 * Usage ledger option (shared with PageSpeed for PSI counts).
	 *
	 * @var string
	 */
	const USAGE_OPTION = 'swiftpress_ai_usage';

	/**
	 * Debounce transient key.
	 *
	 * @var string
	 */
	const DEBOUNCE_TRANSIENT = 'swiftpress_ai_debounce';

	/**
	 * Default monthly cap (USD).
	 *
	 * @var float
	 */
	const DEFAULT_CAP_USD = 2.00;

	/**
	 * Default debounce window (seconds).
	 *
	 * @var int
	 */
	const DEFAULT_DEBOUNCE = 60;

	/**
	 * Prompt token ceiling (clamp).
	 *
	 * @var int
	 */
	const INPUT_CEILING = 6000;

	/**
	 * Output token ceiling (max_tokens).
	 *
	 * @var int
	 */
	const OUTPUT_CEILING = 1500;

	/**
	 * Per-model price table: [in_per_million_usd, out_per_million_usd].
	 *
	 * @var array<string,array{0:float,1:float}>
	 */
	const PRICES = [
		'google/gemini-2.5-flash-lite' => [ 0.10, 0.40 ],
		'google/gemini-2.5-flash'      => [ 0.30, 2.50 ],
	];

	/**
	 * Fallback price when a model is unknown (conservative-ish).
	 *
	 * @var array{0:float,1:float}
	 */
	const FALLBACK_PRICE = [ 1.00, 3.00 ];

	/**
	 * Constructor (placeholder).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return Budget
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
	 * Prompt token ceiling.
	 *
	 * @return int
	 */
	public function input_ceiling() {
		return self::INPUT_CEILING;
	}

	/**
	 * Output token ceiling (max_tokens).
	 *
	 * @return int
	 */
	public function output_ceiling() {
		return self::OUTPUT_CEILING;
	}

	/**
	 * The configured monthly cap (USD).
	 *
	 * @return float
	 */
	public function monthly_cap() {
		$store = $this->secrets();
		if ( isset( $store['monthly_cap_usd'] ) && is_numeric( $store['monthly_cap_usd'] ) ) {
			return (float) $store['monthly_cap_usd'];
		}

		return self::DEFAULT_CAP_USD;
	}

	/**
	 * Read the secrets store (cap lives alongside the keys).
	 *
	 * @return array
	 */
	private function secrets() {
		$store = $this->is_network() ? get_site_option( KeyStore::OPTION, [] ) : get_option( KeyStore::OPTION, [] );

		return is_array( $store ) ? $store : [];
	}

	/**
	 * Read the usage ledger.
	 *
	 * @return array
	 */
	private function usage() {
		$usage = $this->is_network() ? get_site_option( self::USAGE_OPTION, [] ) : get_option( self::USAGE_OPTION, [] );

		return is_array( $usage ) ? $usage : [];
	}

	/**
	 * Persist the usage ledger.
	 *
	 * @param array $usage Usage data.
	 *
	 * @return void
	 */
	private function save_usage( array $usage ) {
		if ( $this->is_network() ) {
			update_site_option( self::USAGE_OPTION, $usage );
		} else {
			update_option( self::USAGE_OPTION, $usage, false );
		}
	}

	/**
	 * Month-to-date spend (USD).
	 *
	 * @return float
	 */
	public function month_to_date() {
		$usage = $this->usage();
		$month = gmdate( 'Y-m' );

		if ( isset( $usage['spend'][ $month ] ) && is_numeric( $usage['spend'][ $month ] ) ) {
			return (float) $usage['spend'][ $month ];
		}

		return 0.0;
	}

	/**
	 * Whether another call is allowed under the cap.
	 *
	 * @return bool
	 */
	public function can_spend() {
		return $this->month_to_date() < $this->monthly_cap();
	}

	/**
	 * The date the monthly cap resets (first day of next month).
	 *
	 * @return string Y-m-d
	 */
	public function reset_date() {
		return gmdate( 'Y-m-01', strtotime( 'first day of next month' ) );
	}

	/**
	 * Estimate the USD cost of a call.
	 *
	 * @param string $model             Model id.
	 * @param int    $prompt_tokens     Prompt tokens.
	 * @param int    $completion_tokens Completion tokens.
	 * @param int    $reasoning_tokens  Reasoning tokens (may be billed but hidden).
	 *
	 * @return float
	 */
	public function estimate_cost( $model, $prompt_tokens, $completion_tokens, $reasoning_tokens = 0 ) {
		$price = isset( self::PRICES[ $model ] ) ? self::PRICES[ $model ] : self::FALLBACK_PRICE;

		$in_cost  = ( (int) $prompt_tokens / 1000000 ) * $price[0];
		$out_cost = ( ( (int) $completion_tokens + (int) $reasoning_tokens ) / 1000000 ) * $price[1];

		return $in_cost + $out_cost;
	}

	/**
	 * Whether cost accounting is approximate for the given model (reasoning tokens).
	 *
	 * @param string $model            Model id.
	 * @param int    $reasoning_tokens Reasoning tokens seen.
	 *
	 * @return bool
	 */
	public function is_estimate_approximate( $model, $reasoning_tokens = 0 ) {
		return $reasoning_tokens > 0 || false !== stripos( (string) $model, 'thinking' );
	}

	/**
	 * Record usage and accumulate spend for the current month.
	 *
	 * @param string $model             Model id.
	 * @param int    $prompt_tokens     Prompt tokens.
	 * @param int    $completion_tokens Completion tokens.
	 * @param int    $reasoning_tokens  Reasoning tokens.
	 *
	 * @return void
	 */
	public function record( $model, $prompt_tokens, $completion_tokens, $reasoning_tokens = 0 ) {
		$cost  = $this->estimate_cost( $model, $prompt_tokens, $completion_tokens, $reasoning_tokens );
		$usage = $this->usage();
		$month = gmdate( 'Y-m' );

		if ( ! isset( $usage['spend'] ) || ! is_array( $usage['spend'] ) ) {
			$usage['spend'] = [];
		}

		$current                  = isset( $usage['spend'][ $month ] ) ? (float) $usage['spend'][ $month ] : 0.0;
		$usage['spend'][ $month ] = round( $current + $cost, 6 );

		if ( ! isset( $usage['calls'] ) || ! is_array( $usage['calls'] ) ) {
			$usage['calls'] = [];
		}
		$usage['calls'][ $month ] = ( isset( $usage['calls'][ $month ] ) ? (int) $usage['calls'][ $month ] : 0 ) + 1;

		// Keep the ledger small: retain the most recent 6 months.
		foreach ( [ 'spend', 'calls' ] as $bucket ) {
			if ( count( $usage[ $bucket ] ) > 6 ) {
				ksort( $usage[ $bucket ] );
				$usage[ $bucket ] = array_slice( $usage[ $bucket ], -6, null, true );
			}
		}

		$this->save_usage( $usage );
	}

	/**
	 * Whether a diagnostic is currently debounced.
	 *
	 * @return bool
	 */
	public function is_debounced() {
		return (bool) get_transient( self::DEBOUNCE_TRANSIENT );
	}

	/**
	 * Seconds remaining on the debounce window (best-effort).
	 *
	 * @return int
	 */
	public function debounce_remaining() {
		// Transients don't expose TTL portably; store the expiry timestamp alongside.
		$expires = (int) get_transient( self::DEBOUNCE_TRANSIENT );
		if ( $expires <= 0 ) {
			return 0;
		}

		$remaining = $expires - time();

		return $remaining > 0 ? $remaining : 0;
	}

	/**
	 * Start the debounce window.
	 *
	 * @return void
	 */
	public function start_debounce() {
		/**
		 * Filters the AI diagnostic debounce window (seconds).
		 *
		 * @hook   swiftpress_ai_debounce_seconds
		 *
		 * @param  {int} Default 60.
		 *
		 * @return {int} New value.
		 * @since  1.0
		 */
		$window = (int) apply_filters( 'swiftpress_ai_debounce_seconds', self::DEFAULT_DEBOUNCE );
		$window = max( 1, $window );

		// Store the expiry timestamp as the value so we can report remaining seconds.
		set_transient( self::DEBOUNCE_TRANSIENT, time() + $window, $window );
	}
}
