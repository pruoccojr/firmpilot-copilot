<?php
/**
 * REST API key authentication.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Authenticates external requests with the site API key.
 */
final class FP_Copilot_Api_Auth {

	/**
	 * HTTP header carrying the site API key.
	 */
	public const HEADER_NAME = 'X-FP-Copilot-Key';

	/**
	 * Extract an API key from the current request.
	 */
	/**
	 * Extract an API key from a REST request or the current HTTP request.
	 *
	 * @param WP_REST_Request|null $request Optional REST request.
	 */
	public static function get_request_key( ?WP_REST_Request $request = null ): string {
		if ( $request instanceof WP_REST_Request ) {
			$key = $request->get_header( self::HEADER_NAME );

			if ( is_string( $key ) && '' !== trim( $key ) ) {
				return trim( $key );
			}

			$authorization = $request->get_header( 'authorization' );

			if ( is_string( $authorization ) && preg_match( '/^\s*Bearer\s+(\S+)\s*$/i', $authorization, $matches ) ) {
				return $matches[1];
			}
		}

		$header = 'HTTP_' . str_replace( '-', '_', strtoupper( self::HEADER_NAME ) );

		if ( isset( $_SERVER[ $header ] ) ) {
			return trim( (string) wp_unslash( $_SERVER[ $header ] ) );
		}

		$authorization = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? (string) wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) : '';

		if ( '' === $authorization && function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();

			if ( is_array( $headers ) ) {
				foreach ( $headers as $name => $value ) {
					if ( strcasecmp( (string) $name, 'Authorization' ) === 0 ) {
						$authorization = (string) $value;
						break;
					}
					if ( strcasecmp( (string) $name, self::HEADER_NAME ) === 0 ) {
						return trim( (string) $value );
					}
				}
			}
		}

		if ( preg_match( '/^\s*Bearer\s+(\S+)\s*$/i', $authorization, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Whether the current request presents a valid site API key.
	 *
	 * @param WP_REST_Request|null $request Optional REST request.
	 */
	public static function validate_request( ?WP_REST_Request $request = null ): bool {
		$key = self::get_request_key( $request );

		if ( '' === $key ) {
			return false;
		}

		return FP_Copilot_Api_Key::validate( $key );
	}

	/**
	 * REST permission callback for API-key-protected routes.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public static function rest_permission( WP_REST_Request $request ): bool {
		return self::validate_request( $request );
	}

	/**
	 * REST permission callback allowing admins or a valid API key.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public static function rest_permission_admin_or_key( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || self::validate_request( $request );
	}
}
