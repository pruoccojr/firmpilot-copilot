<?php
/**
 * Registered admin utilities.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Utility registry for the admin Utilities screen.
 */
final class FP_Copilot_Admin_Utilities {

	/**
	 * Returns all registered utilities.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		$utilities = array(
			array(
				'id'          => 'safe-svg',
				'name'        => __( 'Safe SVG', 'fp-copilot' ),
				'description' => __( 'Allows sanitized SVG uploads to the media library.', 'fp-copilot' ),
				'enabled'     => (bool) get_option( FP_Copilot_Module_Safe_Svg::OPTION_ENABLED, false ),
			),
		);

		/**
		 * Filter registered admin utilities.
		 *
		 * @param array<int, array<string, mixed>> $utilities Utility definitions.
		 */
		return apply_filters( 'fp_copilot_utilities', $utilities );
	}

	/**
	 * Find a utility by ID.
	 *
	 * @param string $id Utility ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		foreach ( self::all() as $utility ) {
			if ( ( $utility['id'] ?? '' ) === $id ) {
				return $utility;
			}
		}

		return null;
	}

	/**
	 * Update a utility enabled state.
	 *
	 * @param string $id      Utility ID.
	 * @param bool   $enabled Whether the utility is enabled.
	 * @return true|WP_Error
	 */
	public static function set_enabled( string $id, bool $enabled ) {
		$option_key = self::option_key_for( $id );

		if ( null === $option_key ) {
			return new WP_Error(
				'fp_copilot_utility_unknown',
				__( 'Unknown utility.', 'fp-copilot' ),
				array( 'status' => 404 )
			);
		}

		update_option( $option_key, $enabled );

		/**
		 * Fires after a utility enabled state changes.
		 *
		 * @param string $id      Utility ID.
		 * @param bool   $enabled New enabled state.
		 */
		do_action( 'fp_copilot_utility_toggled', $id, $enabled );

		return true;
	}

	/**
	 * Map a utility ID to its option key.
	 */
	private static function option_key_for( string $id ): ?string {
		$map = array(
			'safe-svg' => FP_Copilot_Module_Safe_Svg::OPTION_ENABLED,
		);

		/**
		 * Filter the option key for a utility.
		 *
		 * @param string|null $option_key Option key, or null if unknown.
		 * @param string      $id         Utility ID.
		 */
		$option_key = apply_filters( 'fp_copilot_utility_option_key', $map[ $id ] ?? null, $id );

		return is_string( $option_key ) && '' !== $option_key ? $option_key : null;
	}
}
