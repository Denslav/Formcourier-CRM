<?php
/**
 * Credential encryption helper.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encryption.
 */
final class FormCourier_CRM_Encryption {

	private const PREFIX      = 'fbcrm_lite_enc:v1:';
	private const CIPHER      = 'aes-256-gcm';
	private const PLACEHOLDER = '__FORMCOURIER_CRM_SECRET_SAVED__';

	/**
	 * Returns the sensitive option keys.
	 */
	public static function get_sensitive_option_keys(): array {
		return array(
			'hubspot_access_token',
			'pipedrive_api_token',
		);
	}

	/**
	 * Checks whether available.
	 */
	public static function is_available(): bool {
		if ( self::is_test_failure_enabled( 'openssl_unavailable' ) ) {
			return false;
		}

		return function_exists( 'openssl_encrypt' )
			&& function_exists( 'openssl_decrypt' )
			&& function_exists( 'openssl_cipher_iv_length' )
			&& function_exists( 'openssl_get_cipher_methods' )
			&& in_array( self::CIPHER, openssl_get_cipher_methods(), true );
	}

	/**
	 * Returns the placeholder.
	 */
	public static function get_placeholder(): string {
		return self::PLACEHOLDER;
	}

	/**
	 * Checks whether placeholder.
	 *
	 * @param string $value Value.
	 */
	public static function is_placeholder( string $value ): bool {
		return self::PLACEHOLDER === trim( $value );
	}

	/**
	 * Checks whether encrypted.
	 *
	 * @param mixed $value Value.
	 */
	public static function is_encrypted( $value ): bool {
		return is_string( $value ) && 0 === strpos( $value, self::PREFIX );
	}

	/**
	 * Encrypts the supplied value.
	 *
	 * @param string $value Value.
	 */
	public static function encrypt_if_needed( string $value ): string {
		if ( '' === $value || self::is_encrypted( $value ) ) {
			return $value;
		}

		return self::encrypt( $value );
	}

	/**
	 * Encrypts a value and fails closed.
	 *
	 * An empty string is returned whenever secure encryption cannot be
	 * completed. The original plaintext is never returned from this method.
	 *
	 * @param string $value Plaintext value.
	 */
	public static function encrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( self::is_encrypted( $value ) ) {
			return $value;
		}

		if ( ! self::is_available() ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );

		if ( false === $iv_length || $iv_length < 1 ) {
			return '';
		}

		if ( self::is_test_failure_enabled( 'random_bytes' ) ) {
			return '';
		}

		try {
			$iv = random_bytes( $iv_length );
		} catch ( Throwable $e ) {
			return '';
		}

		if ( strlen( $iv ) !== $iv_length ) {
			return '';
		}

		$tag = '';

		$ciphertext = self::is_test_failure_enabled( 'openssl_encrypt' )
			? false
			: openssl_encrypt(
				$value,
				self::CIPHER,
				self::get_key(),
				OPENSSL_RAW_DATA,
				$iv,
				$tag
			);

		if ( false === $ciphertext || '' === $tag ) {
			return '';
		}

		// Base64 transports binary cryptographic values and is not used to obfuscate executable code.
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$payload = array(
			'cipher' => self::CIPHER,
			'iv'     => base64_encode( $iv ),
			'tag'    => base64_encode( $tag ),
			'value'  => base64_encode( $ciphertext ),
		);

		$json = self::is_test_failure_enabled( 'json_encode' )
			? false
			: wp_json_encode( $payload );

		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}

		$encoded_payload = base64_encode( $json );
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		if ( '' === $encoded_payload ) {
			return '';
		}

		return self::PREFIX . $encoded_payload;
	}

	/**
	 * Decrypts the supplied value.
	 *
	 * @param string $stored_value Stored value.
	 */
	public static function decrypt( string $stored_value ): string {
		if ( '' === $stored_value ) {
			return '';
		}

		if ( ! self::is_encrypted( $stored_value ) ) {
			return $stored_value;
		}

		if ( ! self::is_available() ) {
			return '';
		}

		$encoded_payload = substr( $stored_value, strlen( self::PREFIX ) );
		// Base64 decodes the plugin's binary cryptographic envelope and never executable code.
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$json = base64_decode( $encoded_payload, true );

		if ( false === $json || '' === $json ) {
			return '';
		}

		$payload = json_decode( $json, true );

		if ( ! is_array( $payload ) ) {
			return '';
		}

		if ( empty( $payload['iv'] ) || empty( $payload['tag'] ) || empty( $payload['value'] ) ) {
			return '';
		}

		$iv         = base64_decode( (string) $payload['iv'], true );
		$tag        = base64_decode( (string) $payload['tag'], true );
		$ciphertext = base64_decode( (string) $payload['value'], true );
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $iv || false === $tag || false === $ciphertext ) {
			return '';
		}

		$plain_text = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			self::get_key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		return false === $plain_text ? '' : $plain_text;
	}

	/**
	 * Decrypts the supplied value.
	 *
	 * @param array $settings Settings.
	 */
	public static function decrypt_settings( array $settings ): array {
		foreach ( self::get_sensitive_option_keys() as $key ) {
			if ( ! isset( $settings[ $key ] ) || ! is_string( $settings[ $key ] ) ) {
				continue;
			}

			$settings[ $key ] = self::decrypt( $settings[ $key ] );
		}

		return $settings;
	}

	/**
	 * Encrypts the supplied value.
	 *
	 * @param array $settings Settings.
	 */
	public static function encrypt_settings_if_needed( array $settings ): array {
		foreach ( self::get_sensitive_option_keys() as $key ) {
			if ( empty( $settings[ $key ] ) || ! is_string( $settings[ $key ] ) ) {
				continue;
			}

			$settings[ $key ] = self::encrypt_if_needed( $settings[ $key ] );
		}

		return $settings;
	}


	/**
	 * Returns the key.
	 */
	private static function get_key(): string {
		$material = '';

		if ( function_exists( 'wp_salt' ) ) {
			$material .= wp_salt( 'auth' );
			$material .= wp_salt( 'secure_auth' );
			$material .= wp_salt( 'logged_in' );
			$material .= wp_salt( 'nonce' );
		}

		$constants = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
			'DB_NAME',
			'DB_USER',
			'ABSPATH',
		);

		foreach ( $constants as $constant ) {
			if ( defined( $constant ) ) {
				$material .= '|' . constant( $constant );
			}
		}

		if ( '' === $material ) {
			$material = 'formcourier-crm-fallback-key';
		}

		return hash( 'sha256', $material, true );
	}

	/**
	 * Checks whether test failure enabled.
	 *
	 * @param string $operation Operation.
	 */
	private static function is_test_failure_enabled( string $operation ): bool {
		if ( ! self::is_testing_environment() ) {
			return false;
		}

		$failures = $GLOBALS['formcourier_crm_encryption_test_failures'] ?? array();

		return is_array( $failures ) && ! empty( $failures[ $operation ] );
	}

	/**
	 * Checks whether testing environment.
	 */
	private static function is_testing_environment(): bool {
		return defined( 'FORMCOURIER_CRM_TESTING' ) && true === FORMCOURIER_CRM_TESTING;
	}
}
