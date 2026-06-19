<?php
/**
 * Admin REST API routes.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoints for the FirmPilot Copilot admin app.
 */
final class FP_Copilot_Admin_Rest {

	/**
	 * REST namespace.
	 */
	private const NAMESPACE = 'fp-copilot/v1';

	/**
	 * Register routes.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/utilities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_utilities' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/utilities/(?P<id>[a-z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( self::class, 'update_utility' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'id'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'enabled' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_settings' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
	}

	/**
	 * Permission check.
	 */
	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /utilities
	 *
	 * @return WP_REST_Response
	 */
	public static function get_utilities(): WP_REST_Response {
		return rest_ensure_response( FP_Copilot_Admin_Utilities::all() );
	}

	/**
	 * PATCH /utilities/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_utility( WP_REST_Request $request ) {
		$id      = (string) $request->get_param( 'id' );
		$enabled = (bool) $request->get_param( 'enabled' );

		$result = FP_Copilot_Admin_Utilities::set_enabled( $id, $enabled );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$utility = FP_Copilot_Admin_Utilities::get( $id );

		return rest_ensure_response( $utility );
	}

	/**
	 * GET /settings
	 *
	 * @return WP_REST_Response
	 */
	public static function get_settings(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'apiKey'     => FP_Copilot_Api_Key::get(),
				'connection' => FP_Copilot_Remote_Connection::status(),
			)
		);
	}
}
