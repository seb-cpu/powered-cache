<?php
/**
 * OpenRouter chat/completions client (server-side only).
 *
 * Sends the structured-output request, parses choices[0].message.content as JSON,
 * records token usage in Budget, and never logs the key (redacted via KeyStore).
 *
 * @package SwiftPress
 */

namespace SwiftPress\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 */
class Client {

	/**
	 * OpenRouter chat completions endpoint.
	 *
	 * @var string
	 */
	const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * Default model (cost/capability sweet spot per landscape).
	 *
	 * @var string
	 */
	const DEFAULT_MODEL = 'google/gemini-2.5-flash-lite';

	/**
	 * Budget collaborator.
	 *
	 * @var Budget
	 */
	private $budget;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->budget = Budget::factory();
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return Client
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Run a structured-output completion.
	 *
	 * @param array  $messages Chat messages.
	 * @param array  $schema   JSON response schema.
	 * @param string $purpose  Purpose tag (for model override filter).
	 *
	 * @return array|\WP_Error Decoded JSON object on success; WP_Error on failure.
	 */
	public function complete( array $messages, array $schema, $purpose = 'diagnostic' ) {
		$key = KeyStore::factory()->get_key();

		if ( empty( $key ) ) {
			return new \WP_Error( 'no_key', __( 'No OpenRouter key available.', 'swiftpress' ) );
		}

		/**
		 * Filters the model used for an AI call.
		 *
		 * @hook   swiftpress_ai_model
		 *
		 * @param  {string} $model   Model id.
		 * @param  {string} $purpose Purpose tag.
		 *
		 * @return {string} New value.
		 * @since  1.0
		 */
		$model = apply_filters( 'swiftpress_ai_model', self::DEFAULT_MODEL, $purpose );

		$body = [
			'model'           => $model,
			'messages'        => $messages,
			'temperature'     => 0.2,
			'max_tokens'      => $this->budget->output_ceiling(),
			'response_format' => [
				'type'        => 'json_schema',
				'json_schema' => [
					'name'   => 'swiftpress_diagnostic',
					'strict' => true,
					'schema' => $schema,
				],
			],
		];

		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'timeout'   => 30,
				'headers'   => [
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url( '/' ),
					'X-Title'       => 'SwiftPress',
				],
				'body'      => wp_json_encode( $body ),
				'sslverify' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->log_redacted( 'OpenRouter request failed: ' . $response->get_error_message() );

			return $response;
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			$this->log_redacted( sprintf( 'OpenRouter HTTP %d: %s', $code, $raw_body ) );

			return new \WP_Error(
				'openrouter_http_error',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'The AI service returned an error (HTTP %d).', 'swiftpress' ),
					$code
				)
			);
		}

		$envelope = json_decode( $raw_body, true );

		if ( ! is_array( $envelope ) || ! isset( $envelope['choices'][0]['message']['content'] ) ) {
			$this->log_redacted( 'OpenRouter malformed envelope: ' . $raw_body );

			return new \WP_Error( 'openrouter_malformed', __( 'The AI service returned an unexpected response.', 'swiftpress' ) );
		}

		// Record usage/spend regardless of content parse outcome.
		$this->record_usage( $envelope, $model );

		$content = (string) $envelope['choices'][0]['message']['content'];
		$parsed  = $this->parse_content( $content );

		if ( null === $parsed ) {
			$this->log_redacted( 'OpenRouter content not valid JSON.' );

			return new \WP_Error( 'openrouter_bad_json', __( 'The AI service did not return valid structured data.', 'swiftpress' ) );
		}

		return $parsed;
	}

	/**
	 * Decode the model content. Tries strict JSON, then a single fenced-JSON extraction.
	 *
	 * @param string $content Raw message content.
	 *
	 * @return array|null
	 */
	private function parse_content( $content ) {
		$content = trim( (string) $content );

		$decoded = json_decode( $content, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// Single fenced-JSON fallback: ```json { ... } ``` or ``` { ... } ```.
		if ( preg_match( '/```(?:json)?\s*(\{.*\})\s*```/s', $content, $m ) ) {
			$decoded = json_decode( $m[1], true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Last resort: first balanced-looking object slice.
		$start = strpos( $content, '{' );
		$end   = strrpos( $content, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$decoded = json_decode( substr( $content, $start, $end - $start + 1 ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}

	/**
	 * Read usage tokens and record spend in Budget.
	 *
	 * @param array  $envelope Decoded response envelope.
	 * @param string $model    Model id used.
	 *
	 * @return void
	 */
	private function record_usage( array $envelope, $model ) {
		$prompt_tokens     = isset( $envelope['usage']['prompt_tokens'] ) ? (int) $envelope['usage']['prompt_tokens'] : 0;
		$completion_tokens = isset( $envelope['usage']['completion_tokens'] ) ? (int) $envelope['usage']['completion_tokens'] : 0;

		// Reasoning/thinking tokens may be billed but not surfaced in the surface counts.
		$reasoning_tokens = 0;
		if ( isset( $envelope['usage']['completion_tokens_details']['reasoning_tokens'] ) ) {
			$reasoning_tokens = (int) $envelope['usage']['completion_tokens_details']['reasoning_tokens'];
		}

		$this->budget->record( $model, $prompt_tokens, $completion_tokens, $reasoning_tokens );
	}

	/**
	 * Log a message with all secret material redacted.
	 *
	 * @param string $message Message.
	 *
	 * @return void
	 */
	private function log_redacted( $message ) {
		\SwiftPress\Utils\log( KeyStore::redact( $message ) );
	}
}
