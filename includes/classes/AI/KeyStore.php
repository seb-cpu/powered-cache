<?php
/**
 * Secret key storage for the AI module.
 *
 * Resolution order for the OpenRouter key:
 *   1. Constant SWIFTPRESS_OPENROUTER_KEY (wp-config.php) — never in DB.
 *   2. Encrypted option swiftpress_ai_secrets['openrouter_key_cipher'].
 *   3. None — callers degrade to RulesFallback.
 *
 * The key lives in its OWN option (never SETTING_OPTION) so it is structurally
 * impossible for it to ride the config-file or export path. Encryption uses
 * sodium_crypto_secretbox keyed off a wp-config salt (decryption secret on the
 * filesystem, ciphertext in the DB).
 *
 * @package SwiftPress
 */

namespace SwiftPress\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KeyStore
 */
class KeyStore {

	/**
	 * Dedicated option name. NEVER SETTING_OPTION.
	 *
	 * @var string
	 */
	const OPTION = 'swiftpress_ai_secrets';

	/**
	 * Canonical list of secret keys that must never leak (export / config file / logs).
	 *
	 * H1/H3: single source of truth. Both strip lists in core pull from this via the
	 * `swiftpress_secret_strip_keys` filter that AI::setup() registers.
	 *
	 * @var string[]
	 */
	const SECRET_KEYS = [ 'openrouter_key_cipher', 'psi_key_cipher' ];

	/**
	 * Loose OpenRouter key shape: sk-... with at least 20 trailing chars.
	 *
	 * @var string
	 */
	const KEY_REGEX = '/^sk-[A-Za-z0-9_\-]{20,}$/';

	/**
	 * Constructor (placeholder, mirrors codebase style).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return KeyStore
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
	 * Read the raw secrets store array.
	 *
	 * @return array
	 */
	private function store() {
		$store = $this->is_network()
			? get_site_option( self::OPTION, [] )
			: get_option( self::OPTION, [] );

		return is_array( $store ) ? $store : [];
	}

	/**
	 * Persist the secrets store array.
	 *
	 * @param array $store Store data.
	 *
	 * @return bool
	 */
	private function save_store( array $store ) {
		if ( $this->is_network() ) {
			return update_site_option( self::OPTION, $store );
		}

		return update_option( self::OPTION, $store, false );
	}

	/* -----------------------------------------------------------------
	 * OpenRouter key
	 * ----------------------------------------------------------------- */

	/**
	 * Resolve the plaintext OpenRouter key. Server-side only — never returned to a response.
	 *
	 * @return string|null
	 */
	public function get_key() {
		if ( defined( 'SWIFTPRESS_OPENROUTER_KEY' ) && SWIFTPRESS_OPENROUTER_KEY ) {
			return (string) SWIFTPRESS_OPENROUTER_KEY;
		}

		$store  = $this->store();
		$cipher = isset( $store['openrouter_key_cipher'] ) ? (string) $store['openrouter_key_cipher'] : '';

		return '' !== $cipher ? $this->decrypt( $cipher ) : null;
	}

	/**
	 * Whether a usable key is present (constant or decryptable option).
	 *
	 * @return bool
	 */
	public function has_key() {
		return null !== $this->get_key();
	}

	/**
	 * Where the key comes from.
	 *
	 * @return string 'constant'|'option'|'none'
	 */
	public function source() {
		if ( defined( 'SWIFTPRESS_OPENROUTER_KEY' ) && SWIFTPRESS_OPENROUTER_KEY ) {
			return 'constant';
		}

		$store = $this->store();
		if ( ! empty( $store['openrouter_key_cipher'] ) ) {
			return 'option';
		}

		return 'none';
	}

	/**
	 * Whether the stored cipher exists but fails to decrypt (salt rotation / tamper).
	 *
	 * Used to surface the H10 admin notice.
	 *
	 * @return bool
	 */
	public function decryption_failed() {
		// Constant takes precedence; if it is set there is nothing to fail.
		if ( defined( 'SWIFTPRESS_OPENROUTER_KEY' ) && SWIFTPRESS_OPENROUTER_KEY ) {
			return false;
		}

		$store = $this->store();
		if ( empty( $store['openrouter_key_cipher'] ) ) {
			return false;
		}

		return null === $this->decrypt( (string) $store['openrouter_key_cipher'] );
	}

	/**
	 * Validate, encrypt and store the OpenRouter key.
	 *
	 * @param string $plain Raw key as typed by the admin.
	 *
	 * @return true|\WP_Error
	 */
	public function set_key( $plain ) {
		$plain = trim( (string) $plain );

		if ( '' === $plain ) {
			return new \WP_Error( 'empty_key', __( 'No key provided.', 'swiftpress' ) );
		}

		if ( ! preg_match( self::KEY_REGEX, $plain ) ) {
			return new \WP_Error(
				'invalid_key_format',
				__( 'That does not look like an OpenRouter API key. It should start with "sk-".', 'swiftpress' )
			);
		}

		if ( ! $this->encryption_available() ) {
			return new \WP_Error(
				'no_encryption',
				__( 'This site cannot securely store the key. Please define SWIFTPRESS_OPENROUTER_KEY in wp-config.php instead.', 'swiftpress' )
			);
		}

		$cipher = $this->encrypt( $plain );
		if ( null === $cipher ) {
			return new \WP_Error(
				'encrypt_failed',
				__( 'Could not encrypt the key. Please define SWIFTPRESS_OPENROUTER_KEY in wp-config.php instead.', 'swiftpress' )
			);
		}

		$store                          = $this->store();
		$store['openrouter_key_cipher'] = $cipher;
		$store['openrouter_key_set_at'] = time();
		$this->save_store( $store );

		return true;
	}

	/**
	 * Remove the stored OpenRouter key.
	 *
	 * @return bool
	 */
	public function clear_key() {
		$store = $this->store();
		unset( $store['openrouter_key_cipher'], $store['openrouter_key_set_at'] );

		return $this->save_store( $store );
	}

	/* -----------------------------------------------------------------
	 * PSI key (H3 — same encryption, same canonical secret set)
	 * ----------------------------------------------------------------- */

	/**
	 * Resolve the plaintext PSI key (constant override > encrypted option > null).
	 *
	 * @return string|null
	 */
	public function get_psi_key() {
		if ( defined( 'SWIFTPRESS_PSI_KEY' ) && SWIFTPRESS_PSI_KEY ) {
			return (string) SWIFTPRESS_PSI_KEY;
		}

		$store  = $this->store();
		$cipher = isset( $store['psi_key_cipher'] ) ? (string) $store['psi_key_cipher'] : '';

		return '' !== $cipher ? $this->decrypt( $cipher ) : null;
	}

	/**
	 * Validate, encrypt and store the PSI key. PSI keys are free-form Google API keys.
	 *
	 * @param string $plain Raw key.
	 *
	 * @return true|\WP_Error
	 */
	public function set_psi_key( $plain ) {
		$plain = trim( (string) $plain );

		if ( '' === $plain ) {
			return new \WP_Error( 'empty_key', __( 'No key provided.', 'swiftpress' ) );
		}

		// Google API keys are typically ~39 chars of [A-Za-z0-9_-]; keep it loose but bounded.
		if ( ! preg_match( '/^[A-Za-z0-9_\-]{20,120}$/', $plain ) ) {
			return new \WP_Error( 'invalid_key_format', __( 'That does not look like a valid PageSpeed Insights API key.', 'swiftpress' ) );
		}

		if ( ! $this->encryption_available() ) {
			return new \WP_Error(
				'no_encryption',
				__( 'This site cannot securely store the key.', 'swiftpress' )
			);
		}

		$cipher = $this->encrypt( $plain );
		if ( null === $cipher ) {
			return new \WP_Error( 'encrypt_failed', __( 'Could not encrypt the key.', 'swiftpress' ) );
		}

		$store                   = $this->store();
		$store['psi_key_cipher'] = $cipher;
		$this->save_store( $store );

		return true;
	}

	/**
	 * Remove the stored PSI key.
	 *
	 * @return bool
	 */
	public function clear_psi_key() {
		$store = $this->store();
		unset( $store['psi_key_cipher'] );

		return $this->save_store( $store );
	}

	/* -----------------------------------------------------------------
	 * Encryption primitives
	 * ----------------------------------------------------------------- */

	/**
	 * Whether encryption can be performed safely.
	 *
	 * H2: requires BOTH sodium AND real key material (dedicated constant or a WP salt).
	 * There is NO hardcoded fallback — if no material exists we refuse to encrypt.
	 *
	 * @return bool
	 */
	public function encryption_available() {
		return function_exists( 'sodium_crypto_secretbox' ) && null !== $this->enc_key();
	}

	/**
	 * Derive the 32-byte encryption key from a dedicated constant or a WP salt.
	 *
	 * H2: returns null (rather than a hardcoded string) when no material is available,
	 * so the caller refuses to store a key derived from public material.
	 *
	 * @return string|null 32 raw bytes, or null when no key material is defined.
	 */
	private function enc_key() {
		$material = null;

		if ( defined( 'SWIFTPRESS_ENCRYPTION_KEY' ) && SWIFTPRESS_ENCRYPTION_KEY ) {
			$material = SWIFTPRESS_ENCRYPTION_KEY;
		} elseif ( defined( 'LOGGED_IN_SALT' ) && LOGGED_IN_SALT && 'put your unique phrase here' !== LOGGED_IN_SALT ) {
			$material = LOGGED_IN_SALT;
		} elseif ( defined( 'AUTH_SALT' ) && AUTH_SALT && 'put your unique phrase here' !== AUTH_SALT ) {
			$material = AUTH_SALT;
		} elseif ( defined( 'SECURE_AUTH_SALT' ) && SECURE_AUTH_SALT && 'put your unique phrase here' !== SECURE_AUTH_SALT ) {
			$material = SECURE_AUTH_SALT;
		} elseif ( defined( 'NONCE_SALT' ) && NONCE_SALT && 'put your unique phrase here' !== NONCE_SALT ) {
			$material = NONCE_SALT;
		}

		if ( null === $material ) {
			return null;
		}

		return hash( 'sha256', 'swiftpress|ai|' . $material, true ); // 32 raw bytes.
	}

	/**
	 * Encrypt a plaintext string. Returns base64( nonce || cipher ) or null on failure.
	 *
	 * @param string $plain Plaintext.
	 *
	 * @return string|null
	 */
	public function encrypt( $plain ) {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return null;
		}

		$key = $this->enc_key();
		if ( null === $key ) {
			return null; // H2: never encrypt with hardcoded/public material.
		}

		try {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( (string) $plain, $nonce, $key );
		} catch ( \Exception $e ) {
			return null;
		}

		return base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored base64( nonce || cipher ) string.
	 *
	 * Returns null on any failure (bad base64, truncated, salt rotated, tamper) so the
	 * caller treats it as "no key" and degrades gracefully.
	 *
	 * @param string $stored Stored ciphertext.
	 *
	 * @return string|null
	 */
	public function decrypt( $stored ) {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return null;
		}

		$key = $this->enc_key();
		if ( null === $key ) {
			return null;
		}

		$raw = base64_decode( (string) $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}

		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		try {
			$plain = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
		} catch ( \Exception $e ) {
			return null;
		}

		return false === $plain ? null : $plain;
	}

	/* -----------------------------------------------------------------
	 * Redaction + admin notice
	 * ----------------------------------------------------------------- */

	/**
	 * Mask any secret material in a string before it can reach a log.
	 *
	 * @param string $text Text potentially containing a key or Authorization header.
	 *
	 * @return string
	 */
	public static function redact( $text ) {
		$text = (string) $text;

		// Mask "Authorization: Bearer sk-..." and bare sk-... tokens.
		$text = preg_replace( '/(Authorization\s*:?\s*Bearer\s+)[A-Za-z0-9_\-]+/i', '$1***redacted***', $text );
		$text = preg_replace( '/sk-[A-Za-z0-9_\-]{6,}/', 'sk-***redacted***', $text );

		return $text;
	}

	/**
	 * Human-readable admin-notice message for a decryption failure (H10).
	 *
	 * @return string
	 */
	public static function decryption_failure_message() {
		return __(
			'Your OpenRouter key could not be decrypted — this usually means WordPress security salts were rotated. Please re-enter your key.',
			'swiftpress'
		);
	}
}
