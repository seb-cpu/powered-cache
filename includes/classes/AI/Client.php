<?php
/**
 * OpenRouter chat/completions client (server-side only).
 *
 * Sends a structured-output request and is resilient to models that reject
 * strict json_schema (e.g. Gemini Flash-Lite on OpenRouter): it falls back to
 * json_object, then to no response_format, parsing the JSON out of the content
 * either way. Records token usage in Budget; never logs the key.
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
	 * OpenRouter key-info endpoint (used for live key validation).
	 *
	 * @var string
	 */
	const KEY_ENDPOINT = 'https://openrouter.ai/api/v1/key';

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
	 * Live-validate an OpenRouter key by querying the key-info endpoint.
	 *
	 * @param string $key Plaintext key.
	 *
	 * @return true|\WP_Error
	 */
	public function validate_key( $key ) {
		$key = trim( (string) $key );
		if ( '' === $key ) {
			return new \WP_Error( 'empty_key', __( 'No key provided.', 'swiftpress' ) );
		}

		$response = wp_remote_get(
			self::KEY_ENDPOINT,
			[
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => [ 'Authorization' => 'Bearer ' . $key ],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			return true;
		}
		if ( 401 === $code ) {
			return new \WP_Error( 'invalid_key', __( 'That OpenRouter key was rejected (invalid or revoked).', 'swiftpress' ) );
		}

		return new \WP_Error(
			'validate_failed',
			sprintf(
				/* translators: %d: HTTP status code. */
				__( 'Could not validate the key (HTTP %d). Please try again.', 'swiftpress' ),
				$code
			)
		);
	}

	/**
	 * Run a structured-output completion, resilient to response_format support.
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

		// Try the strictest structured-output first, then progressively looser
		// formats — some OpenRouter models reject strict json_schema with a 4xx.
		$formats = [
			[
				'type'        => 'json_schema',
				'json_schema' => [
					'name'   => 'swiftpress_diagnostic',
					'strict' => true,
					'schema' => $schema,
				],
			],
			[ 'type' => 'json_object' ],
			null,
		];

		$last_error = null;

		foreach ( $formats as $attempt => $format ) {
			$result = $this->request( $model, $key, $messages, $format );

			if ( is_wp_error( $result ) ) {
				$last_error = $result;
				continue; // transport error — try the next (cheaper) shape.
			}

			list( $code, $raw_body ) = $result;

			if ( 200 !== $code ) {
				$this->log_redacted( sprintf( 'OpenRouter HTTP %d (format attempt %d): %s', $code, $attempt, $raw_body ) );
				$last_error = new \WP_Error(
					'openrouter_http_error',
					sprintf(
						/* translators: %d: HTTP status code. */
						__( 'The AI service returned an error (HTTP %d).', 'swiftpress' ),
						$code
					)
				);
				// Only a format/parameter complaint is worth retrying with a looser shape.
				if ( in_array( $code, [ 400, 404, 415, 422 ], true ) ) {
					continue;
				}
				return $last_error; // 401/402/429/5xx won't be fixed by changing the format.
			}

			$envelope = json_decode( $raw_body, true );
			if ( ! is_array( $envelope ) || ! isset( $envelope['choices'][0]['message']['content'] ) ) {
				$this->log_redacted( 'OpenRouter malformed envelope: ' . $raw_body );
				$last_error = new \WP_Error( 'openrouter_malformed', __( 'The AI service returned an unexpected response.', 'swiftpress' ) );
				continue;
			}

			$this->record_usage( $envelope, $model );

			$parsed = $this->parse_content( (string) $envelope['choices'][0]['message']['content'] );
			if ( null === $parsed ) {
				$this->log_redacted( 'OpenRouter content not valid JSON (format attempt ' . $attempt . ').' );
				$last_error = new \WP_Error( 'openrouter_bad_json', __( 'The AI service did not return valid structured data.', 'swiftpress' ) );
				continue;
			}

			return $parsed;
		}

		return $last_error ? $last_error : new \WP_Error( 'openrouter_failed', __( 'The AI service could not complete the request.', 'swiftpress' ) );
	}

	/**
	 * Perform one POST to OpenRouter.
	 *
	 * @param string     $model           Model id.
	 * @param string     $key             Plaintext key.
	 * @param array      $messages        Messages.
	 * @param array|null $response_format  response_format payload, or null to omit.
	 *
	 * @return array|\WP_Error [int $code, string $body] or WP_Error on transport failure.
	 */
	private function request( $model, $key, array $messages, $response_format ) {
		$body = [
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => 0.2,
			'max_tokens'  => $this->budget->output_ceiling(),
		];
		if ( ! empty( $response_format ) ) {
			$body['response_format'] = $response_format;
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'timeout'   => 30,
				'headers'   => [
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url( '/' ),
					'X-Title'       => 'AICache',
				],
				'body'      => wp_json_encode( $body ),
				'sslverify' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->log_redacted( 'OpenRouter request failed: ' . $response->get_error_message() );
			return $response;
		}

		return [ (int) wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_body( $response ) ];
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
