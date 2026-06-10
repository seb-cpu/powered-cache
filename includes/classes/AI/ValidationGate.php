<?php
/**
 * The deterministic, post-LLM validation gate.
 *
 * Nothing the LLM emits is trusted until it survives this. Runs entirely in PHP
 * with zero model involvement. Drops anything invalid; re-derives `from` from the
 * real settings; resolves requires-chains and conflicts deterministically; downgrades
 * engine-gated changes to suggest-only.
 *
 * @package SwiftPress
 */

namespace SwiftPress\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ValidationGate
 */
class ValidationGate {

	/**
	 * Conservative default cap for any string-type whitelist key that omits max_length (H6).
	 *
	 * @var int
	 */
	const DEFAULT_STRING_MAX_LENGTH = 255;

	/**
	 * Constructor (placeholder).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return ValidationGate
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Filter raw LLM-proposed changes into appliable / suggested / dropped buckets.
	 *
	 * @param array $llm_changes      Raw recommended_changes[] from the LLM (or rules).
	 * @param array $current_settings The real current settings map.
	 *
	 * @return array{appliable:array,suggested:array,dropped:array}
	 */
	public function filter( array $llm_changes, array $current_settings ) {
		$whitelist = Schema::action_whitelist();

		$appliable = [];
		$suggested = [];
		$dropped   = [];

		// First pass: validate each item independently.
		$candidates = [];

		foreach ( $llm_changes as $change ) {
			if ( ! is_array( $change ) || ! isset( $change['setting_key'] ) ) {
				$dropped[] = $this->drop( $change, 'malformed_item' );
				continue;
			}

			$key = (string) $change['setting_key'];

			// 1. Key exists in whitelist?
			if ( ! isset( $whitelist[ $key ] ) ) {
				$dropped[] = $this->drop( $change, 'unknown_setting_key', $this->nearest_key( $key, array_keys( $whitelist ) ) );
				continue;
			}

			$meta = $whitelist[ $key ];

			// 2. `to` is the right type and in range/enum?
			$coerced = $this->coerce_to( $change['to'] ?? null, $meta );
			if ( null === $coerced ) {
				$dropped[] = $this->drop( $change, 'invalid_value' );
				continue;
			}
			$to = $coerced['value'];

			// 3. `from` is the REAL current value (never trust the model).
			$from = array_key_exists( $key, $current_settings ) ? $current_settings[ $key ] : null;

			// no-op?
			if ( $this->equivalent( $from, $to, $meta ) ) {
				$dropped[] = $this->drop( $change, 'no_op' );
				continue;
			}

			$candidates[ $key ] = [
				'setting_key' => $key,
				'from'        => $from,
				'to'          => $to,
				'why'         => isset( $change['why'] ) ? (string) $change['why'] : '',
				'risk'        => isset( $meta['risk'] ) ? $meta['risk'] : ( $change['risk'] ?? 'low' ),
				'confidence'  => isset( $change['confidence'] ) ? (float) $change['confidence'] : 0.5,
				'label'       => isset( $meta['label'] ) ? $meta['label'] : $key,
				'gate_notes'  => [],
				'_meta'       => $meta,
			];
		}

		// 7. Idempotency cap (one per key) is already enforced by keying on $key;
		// if duplicates existed, last-wins. Re-sort by confidence desc for stable output.
		uasort(
			$candidates,
			static function ( $a, $b ) {
				return $b['confidence'] <=> $a['confidence'];
			}
		);

		// 5. Conflict resolution: RUCSS supersedes combine_css.
		$candidates = $this->resolve_conflicts( $candidates, $current_settings, $dropped );

		// 4. Requires-chain resolution + 6. engine gating -> appliable vs suggested.
		foreach ( $candidates as $key => $item ) {
			$meta = $item['_meta'];

			// 6. Engine gating: whitelisted but needs a generator/engine that isn't present.
			$engine = isset( $meta['requires_engine'] ) ? $meta['requires_engine'] : '';
			if ( '' !== $engine && ! $this->engine_available( $engine ) ) {
				$item['gate_notes'][] = sprintf(
					/* translators: %s: engine name. */
					__( 'Requires the %s engine — not yet available; shown as advice only.', 'swiftpress' ),
					$engine
				);
				$suggested[] = $this->finalize( $item );
				continue;
			}

			// 4. Requires prerequisites.
			$requires = isset( $meta['requires'] ) ? (array) $meta['requires'] : [];
			$resolution = $this->resolve_requires( $key, $item, $requires, $candidates, $current_settings );

			if ( 'drop' === $resolution['action'] ) {
				$dropped[] = $this->drop( $item, 'unmet_requirement' );
				continue;
			}

			if ( ! empty( $resolution['auto_added'] ) ) {
				foreach ( $resolution['auto_added'] as $auto ) {
					// Add prerequisite as its own appliable change if not already present.
					if ( ! isset( $candidates[ $auto['setting_key'] ] ) ) {
						$appliable[] = $this->finalize( $auto );
					}
				}
				$item['gate_notes'][] = $resolution['note'];
			}

			$appliable[] = $this->finalize( $item );
		}

		/**
		 * Filters the final appliable diff before it is returned to the UI.
		 *
		 * @hook   swiftpress_ai_recommendations
		 *
		 * @param  {array} $appliable        Appliable changes.
		 * @param  {array} $current_settings Current settings.
		 *
		 * @return {array} New value.
		 * @since  1.0
		 */
		$appliable = apply_filters( 'swiftpress_ai_recommendations', $appliable, $current_settings );

		return [
			'appliable' => array_values( $appliable ),
			'suggested' => array_values( $suggested ),
			'dropped'   => array_values( $dropped ),
		];
	}

	/**
	 * Coerce a proposed `to` value to the whitelisted type. Returns ['value'=>x] or null.
	 *
	 * @param mixed $value Raw value.
	 * @param array $meta  Whitelist entry.
	 *
	 * @return array|null
	 */
	private function coerce_to( $value, array $meta ) {
		$type = isset( $meta['type'] ) ? $meta['type'] : 'bool';

		switch ( $type ) {
			case 'bool':
				// Mirror sanitize_options() ! empty() semantics, but reject obviously wrong types.
				if ( is_array( $value ) ) {
					return null;
				}
				// Accept true/false, 1/0, "1"/"", "true"/"false".
				if ( is_string( $value ) ) {
					$lower = strtolower( trim( $value ) );
					if ( in_array( $lower, [ 'true', '1', 'yes', 'on' ], true ) ) {
						return [ 'value' => true ];
					}
					if ( in_array( $lower, [ 'false', '0', 'no', 'off', '' ], true ) ) {
						return [ 'value' => false ];
					}
					return null;
				}
				return [ 'value' => (bool) $value ];

			case 'int':
				if ( ! is_numeric( $value ) ) {
					return null;
				}
				$int = (int) $value;
				$min = isset( $meta['min'] ) ? (int) $meta['min'] : PHP_INT_MIN;
				$max = isset( $meta['max'] ) ? (int) $meta['max'] : PHP_INT_MAX;
				if ( $int < $min || $int > $max ) {
					return null;
				}
				return [ 'value' => $int ];

			case 'enum':
				if ( ! is_scalar( $value ) ) {
					return null;
				}
				$allowed = isset( $meta['enum'] ) ? (array) $meta['enum'] : [];
				$str     = (string) $value;
				if ( ! in_array( $str, array_map( 'strval', $allowed ), true ) ) {
					return null;
				}
				return [ 'value' => $str ];

			case 'string':
				if ( ! is_scalar( $value ) ) {
					return null;
				}
				$str = (string) $value;
				$max = isset( $meta['max_length'] ) ? (int) $meta['max_length'] : self::DEFAULT_STRING_MAX_LENGTH; // H6.
				if ( strlen( $str ) > $max ) {
					return null;
				}
				return [ 'value' => $str ];

			default:
				return null;
		}
	}

	/**
	 * Whether two values are equivalent for the given type (no-op detection).
	 *
	 * @param mixed $a    Value A.
	 * @param mixed $b    Value B.
	 * @param array $meta Whitelist entry.
	 *
	 * @return bool
	 */
	private function equivalent( $a, $b, array $meta ) {
		$type = isset( $meta['type'] ) ? $meta['type'] : 'bool';

		if ( 'bool' === $type ) {
			return (bool) $a === (bool) $b;
		}
		if ( 'int' === $type ) {
			return (int) $a === (int) $b;
		}

		return (string) $a === (string) $b;
	}

	/**
	 * Deterministic conflict resolution. RUCSS supersedes combine_css.
	 *
	 * @param array $candidates       Candidate map keyed by setting_key.
	 * @param array $current_settings Current settings.
	 * @param array $dropped          Dropped bucket (by reference).
	 *
	 * @return array Updated candidate map.
	 */
	private function resolve_conflicts( array $candidates, array $current_settings, array &$dropped ) {
		$rucss_on = ( isset( $candidates['remove_unused_css'] ) && true === $candidates['remove_unused_css']['to'] )
			|| ! empty( $current_settings['remove_unused_css'] );

		if ( $rucss_on && isset( $candidates['combine_css'] ) && true === $candidates['combine_css']['to'] ) {
			$dropped[] = $this->drop(
				$candidates['combine_css'],
				'conflict_rucss_supersedes_combine_css'
			);
			unset( $candidates['combine_css'] );
		}

		return $candidates;
	}

	/**
	 * Resolve a change's prerequisites.
	 *
	 * @param string $key              Setting key.
	 * @param array  $item             Candidate item.
	 * @param array  $requires         Prerequisite keys.
	 * @param array  $candidates       All candidates (to see same-batch turn-ons).
	 * @param array  $current_settings Current settings.
	 *
	 * @return array{action:string,auto_added?:array,note?:string}
	 */
	private function resolve_requires( $key, array $item, array $requires, array $candidates, array $current_settings ) {
		if ( empty( $requires ) ) {
			return [ 'action' => 'keep' ];
		}

		// Only relevant when we are turning the dependent ON / setting a non-empty value.
		$turning_on = ( true === $item['to'] ) || ( is_string( $item['to'] ) && '' !== $item['to'] ) || ( is_int( $item['to'] ) && $item['to'] > 0 );
		if ( ! $turning_on ) {
			return [ 'action' => 'keep' ];
		}

		$whitelist  = Schema::action_whitelist();
		$auto_added = [];

		foreach ( $requires as $req ) {
			$already_on    = ! empty( $current_settings[ $req ] );
			$on_this_batch = isset( $candidates[ $req ] ) && true === $candidates[ $req ]['to'];

			if ( $already_on || $on_this_batch ) {
				continue;
			}

			// Auto-add only if the prerequisite is itself whitelisted and low-risk boolean.
			if ( isset( $whitelist[ $req ] ) && 'bool' === $whitelist[ $req ]['type'] && 'low' === ( $whitelist[ $req ]['risk'] ?? 'low' ) ) {
				$auto_added[] = [
					'setting_key' => $req,
					'from'        => $current_settings[ $req ] ?? false,
					'to'          => true,
					'why'         => sprintf(
						/* translators: %s: dependent setting label. */
						__( 'Required prerequisite for %s.', 'swiftpress' ),
						$item['label']
					),
					'risk'        => 'low',
					'confidence'  => $item['confidence'],
					'label'       => $whitelist[ $req ]['label'] ?? $req,
					'gate_notes'  => [ __( 'Auto-added as a prerequisite.', 'swiftpress' ) ],
					'_meta'       => $whitelist[ $req ],
				];
			} else {
				// Cannot auto-add a non-low-risk or non-whitelisted prerequisite -> drop dependent.
				return [ 'action' => 'drop' ];
			}
		}

		if ( ! empty( $auto_added ) ) {
			$names = array_map(
				static function ( $a ) {
					return $a['label'];
				},
				$auto_added
			);

			return [
				'action'     => 'keep',
				'auto_added' => $auto_added,
				'note'       => sprintf(
					/* translators: %s: prerequisite labels. */
					__( 'Also enabling required prerequisite(s): %s.', 'swiftpress' ),
					implode( ', ', $names )
				),
			];
		}

		return [ 'action' => 'keep' ];
	}

	/**
	 * Whether a named engine/generator is present. v1 generators are stubs, so these
	 * report false and the dependent change is suggest-only.
	 *
	 * @param string $engine Engine identifier ('critical_css'|'ucss').
	 *
	 * @return bool
	 */
	private function engine_available( $engine ) {
		// The UnusedCSS class ships both the Critical-CSS (safe) and the
		// remove-unused-CSS (aggressive) engines since 1.2; gate on its
		// presence so the AI can actually apply these once they exist.
		$default = in_array( $engine, [ 'ucss', 'critical_css' ], true ) && class_exists( '\SwiftPress\UnusedCSS' );

		/**
		 * Filters whether a named AI-gated optimization engine is available.
		 *
		 * @hook   swiftpress_ai_engine_available
		 *
		 * @param  {bool}   $available Engine presence (class-based detection).
		 * @param  {string} $engine    Engine identifier.
		 *
		 * @return {bool} New value.
		 * @since  1.0
		 */
		return (bool) apply_filters( 'swiftpress_ai_engine_available', $default, $engine );
	}

	/**
	 * Build a dropped record (debug-only; never shown as appliable).
	 *
	 * @param mixed       $change  Original change.
	 * @param string      $reason  Reason code.
	 * @param string|null $nearest Nearest whitelist key (H9 fuzzy match), when relevant.
	 *
	 * @return array
	 */
	private function drop( $change, $reason, $nearest = null ) {
		$record = [
			'setting_key' => is_array( $change ) && isset( $change['setting_key'] ) ? (string) $change['setting_key'] : '',
			'reason'      => $reason,
		];

		if ( null !== $nearest && '' !== $nearest ) {
			$record['nearest'] = $nearest;
		}

		return $record;
	}

	/**
	 * Strip internal fields from a finalized item.
	 *
	 * @param array $item Candidate item.
	 *
	 * @return array
	 */
	private function finalize( array $item ) {
		unset( $item['_meta'] );

		return $item;
	}

	/**
	 * Nearest whitelist key by Levenshtein distance (H9 debug annotation).
	 *
	 * @param string   $key  Invalid key.
	 * @param string[] $keys Whitelist keys.
	 *
	 * @return string|null
	 */
	private function nearest_key( $key, array $keys ) {
		$best      = null;
		$best_dist = PHP_INT_MAX;

		foreach ( $keys as $candidate ) {
			$dist = levenshtein( $key, $candidate );
			if ( $dist < $best_dist ) {
				$best_dist = $dist;
				$best      = $candidate;
			}
		}

		// Only annotate near-misses, not wildly different strings.
		return $best_dist <= 4 ? $best : null;
	}
}
