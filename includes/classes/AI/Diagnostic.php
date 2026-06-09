<?php
/**
 * Pipeline orchestration: metrics -> prompt -> LLM -> gate -> cache, with graceful
 * degradation to RulesFallback (no key / LLM down / over budget).
 *
 * @package SwiftPress
 */

namespace SwiftPress\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Diagnostic
 */
class Diagnostic {

	/**
	 * Transient cache key prefix.
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'swiftpress_ai_diag_';

	/**
	 * Constructor (placeholder).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return Diagnostic
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Run the full diagnostic pipeline.
	 *
	 * @param bool $force Bypass the response cache.
	 *
	 * @return array Normalized payload {summary, overall_assessment, findings[],
	 *               recommended_changes:{appliable,suggested,dropped}, meta}.
	 */
	public function run( $force = false ) {
		$settings             = \SwiftPress\Utils\get_settings();
		$whitelisted_settings = $this->whitelisted_subset( $settings );
		$environment          = $this->environment();

		$urls = $this->target_urls();
		$url  = ! empty( $urls[0] ) ? $urls[0] : home_url( '/' );

		// --- PSI (ground truth) ---
		$metrics    = PageSpeed::factory()->audit( $url, 'mobile' );
		$psi_failed = is_wp_error( $metrics );
		if ( $psi_failed ) {
			\SwiftPress\Utils\log( 'AI diagnostic PSI failed: ' . $metrics->get_error_message() . ' — falling back to local scan.' );

			// Fallback: scan the homepage ourselves (loopback-pinned, bypasses an
			// edge WAF) so the diagnostic still has real data without PageSpeed.
			$local = PageSpeed::factory()->local_scan( $url );
			if ( ! is_wp_error( $local ) ) {
				$metrics    = $local;
				$psi_failed = false;
			} else {
				$metrics = [
					'url'           => $url,
					'strategy'      => 'mobile',
					'source'        => 'none',
					'perf_score'    => null,
					'field'         => [],
					'lab'           => [],
					'opportunities' => [],
				];
			}
		}

		$model = $this->current_model();

		// --- Response cache (only meaningful when PSI gave real data) ---
		$cache_key = self::CACHE_PREFIX . md5( wp_json_encode( [ $metrics, $whitelisted_settings, $model ] ) );
		if ( ! $force && ! $psi_failed ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				$cached['meta']['cached'] = true;

				return $cached;
			}
		}

		$key_store = KeyStore::factory();
		$budget    = Budget::factory();

		$degraded = '';
		$raw      = null;

		if ( ! $key_store->has_key() ) {
			$degraded = 'no_key';
		} elseif ( ! $budget->can_spend() ) {
			$degraded = 'over_budget';
		} else {
			$messages = Schema::build_messages( $metrics, $whitelisted_settings, $environment );
			$result   = Client::factory()->complete( $messages, Schema::response_schema(), 'diagnostic' );

			if ( is_wp_error( $result ) ) {
				$degraded = 'llm_unavailable';
			} else {
				$raw = $this->normalize_llm( $result );
			}
		}

		// --- Fallback if no LLM payload ---
		if ( null === $raw ) {
			$raw = RulesFallback::factory()->evaluate( $metrics, $settings );
		}

		// --- Gate the recommended changes deterministically ---
		$llm_changes = isset( $raw['recommended_changes'] ) && is_array( $raw['recommended_changes'] ) ? $raw['recommended_changes'] : [];
		$gated       = ValidationGate::factory()->filter( $llm_changes, $settings );

		$payload = [
			'summary'             => isset( $raw['summary'] ) ? (string) $raw['summary'] : '',
			'overall_assessment'  => isset( $raw['overall_assessment'] ) ? (string) $raw['overall_assessment'] : 'needs_work',
			'findings'            => isset( $raw['findings'] ) && is_array( $raw['findings'] ) ? $raw['findings'] : [],
			'recommended_changes' => [
				'appliable' => $gated['appliable'],
				'suggested' => $gated['suggested'],
			],
			'metrics'             => $metrics,
			'meta'                => [
				'cached'            => false,
				'degraded'         => '' !== $degraded ? $degraded : false,
				'model'            => $model,
				'url'              => $metrics['url'],
				'perf_score'       => $metrics['perf_score'],
				'psi_failed'       => $psi_failed,
				'metrics_source'   => isset( $metrics['source'] ) ? $metrics['source'] : 'pagespeed',
				'month_to_date'    => round( $budget->month_to_date(), 4 ),
				'monthly_cap'      => $budget->monthly_cap(),
				'dropped'          => $gated['dropped'], // debug-only.
			],
		];

		// Cache only successful, non-degraded, PSI-backed LLM results.
		if ( ! $psi_failed && '' === $degraded ) {
			set_transient( $cache_key, $payload, DAY_IN_SECONDS );
		}

		return $payload;
	}

	/**
	 * Normalize an LLM response to the expected shape.
	 *
	 * Models that don't honour the strict schema (e.g. Gemini Flash-Lite, which
	 * rejects strict json_schema on OpenRouter) frequently use their own field
	 * names — `recommendations`/`setting_value`/`reason` instead of
	 * `recommended_changes`/`to`/`why`, and often omit `summary`. Map the common
	 * variants so the gate and UI still work regardless of the model.
	 *
	 * @param mixed $raw Parsed model output.
	 *
	 * @return array
	 */
	private function normalize_llm( $raw ) {
		if ( ! is_array( $raw ) ) {
			return [ 'summary' => '', 'overall_assessment' => 'needs_work', 'findings' => [], 'recommended_changes' => [] ];
		}

		$changes_in = [];
		foreach ( [ 'recommended_changes', 'recommendations', 'changes', 'actions' ] as $k ) {
			if ( isset( $raw[ $k ] ) && is_array( $raw[ $k ] ) ) {
				$changes_in = $raw[ $k ];
				break;
			}
		}

		$changes = [];
		foreach ( $changes_in as $c ) {
			if ( ! is_array( $c ) || empty( $c['setting_key'] ) ) {
				continue;
			}

			$to = null;
			foreach ( [ 'to', 'setting_value', 'value', 'enabled', 'new_value' ] as $vk ) {
				if ( array_key_exists( $vk, $c ) ) {
					$to = $c[ $vk ];
					break;
				}
			}
			if ( null === $to ) {
				$to = true;
			}

			$why = '';
			foreach ( [ 'why', 'reason', 'description', 'explanation' ] as $wk ) {
				if ( ! empty( $c[ $wk ] ) && is_string( $c[ $wk ] ) ) {
					$why = $c[ $wk ];
					break;
				}
			}

			$changes[] = [
				'setting_key' => (string) $c['setting_key'],
				'to'          => $to,
				'why'         => $why,
				'risk'        => isset( $c['risk'] ) ? (string) $c['risk'] : 'medium',
				'confidence'  => isset( $c['confidence'] ) ? (float) $c['confidence'] : 0.7,
			];
		}

		$findings = ( isset( $raw['findings'] ) && is_array( $raw['findings'] ) ) ? $raw['findings'] : [];

		$summary = '';
		foreach ( [ 'summary', 'overview', 'assessment', 'analysis', 'message' ] as $sk ) {
			if ( ! empty( $raw[ $sk ] ) && is_string( $raw[ $sk ] ) ) {
				$summary = $raw[ $sk ];
				break;
			}
		}
		if ( '' === $summary && $changes ) {
			$summary = sprintf(
				/* translators: %d: number of recommendations. */
				_n( 'I found %d optimization you can apply.', 'I found %d optimizations you can apply.', count( $changes ), 'swiftpress' ),
				count( $changes )
			);
		}

		return [
			'summary'             => $summary,
			'overall_assessment'  => isset( $raw['overall_assessment'] ) ? (string) $raw['overall_assessment'] : 'needs_work',
			'findings'            => $findings,
			'recommended_changes' => $changes,
		];
	}

	/**
	 * The whitelisted subset of settings (what we send to the model).
	 *
	 * @param array $settings Full settings.
	 *
	 * @return array
	 */
	private function whitelisted_subset( array $settings ) {
		$subset = [];
		foreach ( Schema::whitelist_keys() as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$subset[ $key ] = $settings[ $key ];
			}
		}

		return $subset;
	}

	/**
	 * Environment facts (no PII): server type, PHP version, multisite, integrations.
	 *
	 * @return array
	 */
	private function environment() {
		global $is_apache;

		return [
			'server'        => $is_apache ? 'apache' : 'nginx_or_other',
			'php_version'   => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'multisite'     => is_multisite(),
			'woocommerce'   => class_exists( 'WooCommerce' ),
			'object_cache'  => (bool) wp_using_ext_object_cache(),
		];
	}

	/**
	 * URLs to audit.
	 *
	 * @return string[]
	 */
	private function target_urls() {
		/**
		 * Filters the URLs the AI diagnostic audits.
		 *
		 * Note: never hook this to trigger a diagnostic run automatically — calls must
		 * remain admin-initiated and on-demand.
		 *
		 * @hook   swiftpress_ai_psi_urls
		 *
		 * @param  {array} URLs to audit (default: home URL only).
		 *
		 * @return {array} New value.
		 * @since  1.0
		 */
		$urls = apply_filters( 'swiftpress_ai_psi_urls', [ home_url( '/' ) ] );

		return array_values( array_filter( array_map( 'esc_url_raw', (array) $urls ) ) );
	}

	/**
	 * The model that will be used (for cache keying).
	 *
	 * @return string
	 */
	private function current_model() {
		return (string) apply_filters( 'swiftpress_ai_model', Client::DEFAULT_MODEL, 'diagnostic' );
	}

	/**
	 * Invalidate all cached diagnostics. Hooked to swiftpress_settings_saved.
	 *
	 * @return void
	 */
	public function invalidate_cache() {
		global $wpdb;

		// Transients are keyed by hash; clear the family. Direct cleanup is the most
		// reliable way to drop an unknown set of transient keys.
		if ( isset( $wpdb ) ) {
			$like = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		wp_cache_flush();
	}
}
