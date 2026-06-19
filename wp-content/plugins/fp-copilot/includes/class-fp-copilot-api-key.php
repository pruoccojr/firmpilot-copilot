<?php
/**
 * Site-specific API key for external integrations.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generates, stores, and validates the FirmPilot Copilot site API key.
 */
final class FP_Copilot_Api_Key {

	/**
	 * Option name for the stored API key.
	 */
	public const OPTION_NAME = 'fp_copilot_api_key';

	/**
	 * Returns the site API key, generating one if needed.
	 */
	public static function get(): string {
		$key = get_option( self::OPTION_NAME, '' );

		if ( ! is_string( $key ) || '' === $key ) {
			$key = self::generate();
			update_option( self::OPTION_NAME, $key, false );
		}

		return $key;
	}

	/**
	 * Validates a provided API key against the stored value.
	 */
	public static function validate( string $key ): bool {
		$stored = get_option( self::OPTION_NAME, '' );

		if ( ! is_string( $stored ) || '' === $stored || '' === $key ) {
			return false;
		}

		return hash_equals( $stored, $key );
	}

	/**
	 * Create a new random API key.
	 */
	private static function generate(): string {
		try {
			$bytes = random_bytes( 32 );
		} catch ( Exception $exception ) {
			$bytes = wp_generate_password( 32, true, true );
			$bytes = hash( 'sha256', $bytes . wp_salt( 'auth' ), true );
		}

		return 'fpk_' . bin2hex( $bytes );
	}
}
