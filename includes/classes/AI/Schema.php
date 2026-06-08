<?php
/**
 * The action whitelist (closed vocabulary of AI-tunable setting keys), the strict
 * JSON response schema the LLM must fill, and the prompt/message builder.
 *
 * The whitelist is the single source of truth: ValidationGate validates against it
 * and the response schema's `setting_key` enum is derived from it.
 *
 * @package SwiftPress
 */

namespace SwiftPress\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema
 */
class Schema {

	/**
	 * Constructor (placeholder).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return Schema
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * The action whitelist: only real, AI-tunable setting keys.
	 *
	 * Each entry: type, allowed range/enum, requires (prerequisite keys), conflicts,
	 * risk, label (human meaning), and — for string types — max_length (H6).
	 *
	 * Sourced 1:1 from Utils\get_settings() defaults and the coercions in
	 * sanitize_options(). Booleans/scalars/enums only; no free-text exclusion keys.
	 *
	 * @return array<string,array>
	 */
	public static function action_whitelist() {
		$whitelist = [
			'enable_page_cache'                => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Page caching', 'swiftpress' ),
			],
			'gzip_compression'                 => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Gzip compression', 'swiftpress' ),
			],
			'cache_mobile'                     => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Cache for mobile devices', 'swiftpress' ),
			],
			'cache_timeout'                    => [
				'type'  => 'int',
				'min'   => 60,
				'max'   => 43200,
				'risk'  => 'low',
				'label' => __( 'Cache lifespan (minutes)', 'swiftpress' ),
			],
			'minify_css'                       => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Minify CSS', 'swiftpress' ),
			],
			'combine_css'                      => [
				'type'      => 'bool',
				'risk'      => 'medium',
				'conflicts' => [ 'remove_unused_css' ],
				'label'     => __( 'Combine CSS files', 'swiftpress' ),
			],
			'critical_css'                     => [
				'type'           => 'bool',
				'risk'           => 'medium',
				'requires_engine' => 'critical_css',
				'label'          => __( 'Critical CSS', 'swiftpress' ),
			],
			'remove_unused_css'                => [
				'type'           => 'bool',
				'risk'           => 'medium',
				'conflicts'      => [ 'combine_css' ],
				'requires_engine' => 'ucss',
				'label'          => __( 'Remove unused CSS', 'swiftpress' ),
			],
			'minify_js'                        => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Minify JavaScript', 'swiftpress' ),
			],
			'combine_js'                       => [
				'type'  => 'bool',
				'risk'  => 'medium',
				'label' => __( 'Combine JavaScript files', 'swiftpress' ),
			],
			'js_defer'                         => [
				'type'  => 'bool',
				'risk'  => 'medium',
				'label' => __( 'Defer JavaScript', 'swiftpress' ),
			],
			'js_delay'                         => [
				'type'  => 'bool',
				'risk'  => 'high',
				'label' => __( 'Delay JavaScript execution', 'swiftpress' ),
			],
			'js_delay_timeout'                 => [
				'type'     => 'int',
				'min'      => 0,
				'max'      => 10000,
				'risk'     => 'low',
				'requires' => [ 'js_delay' ],
				'label'    => __( 'JavaScript delay timeout (ms)', 'swiftpress' ),
			],
			'enable_font_optimization'         => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Font optimization', 'swiftpress' ),
			],
			'self_host_google_fonts'           => [
				'type'     => 'bool',
				'risk'     => 'low',
				'requires' => [ 'enable_font_optimization' ],
				'label'    => __( 'Self-host Google Fonts', 'swiftpress' ),
			],
			'font_preload'                     => [
				'type'     => 'bool',
				'risk'     => 'low',
				'requires' => [ 'enable_font_optimization' ],
				'label'    => __( 'Preload fonts', 'swiftpress' ),
			],
			'font_display_swap'                => [
				'type'     => 'bool',
				'risk'     => 'low',
				'requires' => [ 'enable_font_optimization' ],
				'label'    => __( 'Font display: swap', 'swiftpress' ),
			],
			'enable_cache_preload'             => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Cache preloading', 'swiftpress' ),
			],
			'enable_sitemap_preload'           => [
				'type'     => 'bool',
				'risk'     => 'low',
				'requires' => [ 'enable_cache_preload' ],
				'label'    => __( 'Sitemap-based preloading', 'swiftpress' ),
			],
			'prefetch_links'                   => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Prefetch links on hover', 'swiftpress' ),
			],
			'enable_lcp_optimization'          => [
				'type'  => 'bool',
				'risk'  => 'medium',
				'label' => __( 'LCP image optimization', 'swiftpress' ),
			],
			'enable_image_optimization'        => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Image optimization', 'swiftpress' ),
			],
			'image_optimizer_preferred_format' => [
				'type'     => 'enum',
				'enum'     => [ '', 'webp', 'avif' ],
				'risk'     => 'low',
				'requires' => [ 'enable_image_optimization' ],
				'label'    => __( 'Preferred image format', 'swiftpress' ),
			],
			'add_missing_image_dimensions'     => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Add missing image dimensions', 'swiftpress' ),
			],
			'disable_emoji_scripts'            => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Disable emoji scripts', 'swiftpress' ),
			],
			'disable_wp_embeds'                => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Disable WordPress embeds', 'swiftpress' ),
			],
			'minify_html'                      => [
				'type'  => 'bool',
				'risk'  => 'low',
				'label' => __( 'Minify HTML', 'swiftpress' ),
			],
		];

		/**
		 * Filters the AI action whitelist (append-only in spirit — Pro gating may
		 * restrict or extend). Any string-type key a third party adds MUST include a
		 * `max_length` (H6); the gate enforces a conservative default otherwise.
		 *
		 * @hook   swiftpress_ai_action_whitelist
		 *
		 * @param  {array} $whitelist The action whitelist.
		 *
		 * @return {array} New value.
		 * @since  1.0
		 */
		return apply_filters( 'swiftpress_ai_action_whitelist', $whitelist );
	}

	/**
	 * The list of whitelisted setting keys (the schema enum).
	 *
	 * @return string[]
	 */
	public static function whitelist_keys() {
		return array_keys( self::action_whitelist() );
	}

	/**
	 * The strict JSON response schema for OpenRouter structured output.
	 *
	 * @return array
	 */
	public static function response_schema() {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'summary', 'findings', 'recommended_changes' ],
			'properties'           => [
				'summary'            => [
					'type'        => 'string',
					'description' => '2-4 plain-language sentences for a non-technical site owner: what is slow and why.',
				],
				'overall_assessment' => [
					'type' => 'string',
					'enum' => [ 'good', 'needs_work', 'poor' ],
				],
				'findings'           => [
					'type'     => 'array',
					'maxItems' => 8,
					'items'    => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'issue', 'evidence_metric', 'severity', 'plain_explanation' ],
						'properties'           => [
							'issue'             => [ 'type' => 'string' ],
							'evidence_metric'   => [
								'type'        => 'string',
								'description' => "Name+value of the metric proving it, e.g. 'LCP 4.1s', 'unused CSS 180KB'.",
							],
							'severity'          => [
								'type' => 'string',
								'enum' => [ 'critical', 'high', 'medium', 'low' ],
							],
							'plain_explanation' => [ 'type' => 'string' ],
						],
					],
				],
				'recommended_changes' => [
					'type'     => 'array',
					'maxItems' => 12,
					'items'    => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'setting_key', 'to', 'why', 'risk', 'confidence' ],
						'properties'           => [
							'setting_key' => [
								'type' => 'string',
								'enum' => self::whitelist_keys(),
							],
							'from'        => [
								'description' => "model's guess of current value; IGNORED — PHP overrides from real settings",
								'type'        => [ 'boolean', 'integer', 'string', 'null' ],
							],
							'to'          => [
								'type' => [ 'boolean', 'integer', 'string' ],
							],
							'why'         => [
								'type'        => 'string',
								'description' => 'one sentence tying this change to a finding metric',
							],
							'risk'        => [
								'type' => 'string',
								'enum' => [ 'low', 'medium', 'high' ],
							],
							'confidence'  => [
								'type'    => 'number',
								'minimum' => 0,
								'maximum' => 1,
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Build the chat messages (system + user) for the diagnostic call.
	 *
	 * @param array $metrics     Normalized PSI metrics.
	 * @param array $settings    Whitelisted current settings subset.
	 * @param array $environment Environment facts (server, php, woocommerce...).
	 *
	 * @return array[] OpenRouter `messages` array.
	 */
	public static function build_messages( array $metrics, array $settings, array $environment ) {
		$system = self::system_prompt();

		$user_payload = wp_json_encode(
			[
				'metrics'          => $metrics,
				'current_settings' => $settings,
				'environment'      => $environment,
			]
		);

		$messages = [
			[
				'role'    => 'system',
				'content' => $system,
			],
			[
				'role'    => 'user',
				'content' => 'Analyze this site and return JSON matching the schema. Data: ' . $user_payload,
			],
		];

		/**
		 * Filters the AI chat messages before sending.
		 *
		 * Note: never hook this to trigger a diagnostic run automatically — calls must
		 * remain admin-initiated and on-demand.
		 *
		 * @hook   swiftpress_ai_system_prompt
		 *
		 * @param  {array} $messages The chat messages.
		 * @param  {array} $context  Context (metrics, settings, environment).
		 *
		 * @return {array} New value.
		 * @since  1.0
		 */
		return apply_filters( 'swiftpress_ai_system_prompt', $messages, compact( 'metrics', 'settings', 'environment' ) );
	}

	/**
	 * Build the system prompt: role, hard rules, and the inlined whitelist.
	 *
	 * @return string
	 */
	private static function system_prompt() {
		$lines   = [];
		$lines[] = 'You are a WordPress performance expert configuring the SwiftPress cache plugin.';
		$lines[] = 'Hard rules:';
		$lines[] = '- You may ONLY recommend changes whose setting_key is in the whitelist below.';
		$lines[] = '- Your output MUST match the provided JSON schema exactly.';
		$lines[] = '- The "from" field will be ignored; never rely on it. PHP re-derives the current value.';
		$lines[] = '- Never recommend disabling page cache (enable_page_cache=false).';
		$lines[] = '- Explain everything in plain language for a non-technical site owner.';
		$lines[] = '- Tie every recommended change to a specific metric in the findings.';
		$lines[] = '';
		$lines[] = 'Whitelist (setting_key — meaning [risk]):';

		foreach ( self::action_whitelist() as $key => $meta ) {
			$label = isset( $meta['label'] ) ? $meta['label'] : $key;
			$risk  = isset( $meta['risk'] ) ? $meta['risk'] : 'low';
			$type  = isset( $meta['type'] ) ? $meta['type'] : 'bool';

			$range = '';
			if ( 'int' === $type && isset( $meta['min'], $meta['max'] ) ) {
				$range = sprintf( ' (int %d-%d)', (int) $meta['min'], (int) $meta['max'] );
			} elseif ( 'enum' === $type && ! empty( $meta['enum'] ) ) {
				$range = ' (one of: ' . implode( ', ', array_map( 'strval', $meta['enum'] ) ) . ')';
			}

			$lines[] = sprintf( '- %s — %s [%s]%s', $key, $label, $risk, $range );
		}

		return implode( "\n", $lines );
	}
}
