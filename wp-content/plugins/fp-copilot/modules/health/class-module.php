<?php
/**
 * Site health, diagnostics, and remote monitoring.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes health APIs and pushes health data to connected apps.
 */
final class FP_Copilot_Module_Health extends FP_Copilot_Module_Base {

	/**
	 * REST namespace.
	 */
	private const REST_NAMESPACE = 'fp-copilot/v1';

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'health';
	}

	/**
	 * {@inheritdoc}
	 */
	public function name(): string {
		return __( 'Health', 'fp-copilot' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function description(): string {
		return __( 'Diagnostics, health-check endpoints, and remote health pushes.', 'fp-copilot' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		FP_Copilot_Remote_Connection::register();

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes for external connections.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/connection',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_connection' ),
					'permission_callback' => array( 'FP_Copilot_Api_Auth', 'rest_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_connection' ),
					'permission_callback' => array( 'FP_Copilot_Api_Auth', 'rest_permission' ),
					'args'                => array(
						'endpoint' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'esc_url_raw',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_connection' ),
					'permission_callback' => array( 'FP_Copilot_Api_Auth', 'rest_permission' ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => array( 'FP_Copilot_Api_Auth', 'rest_permission_admin_or_key' ),
			)
		);
	}

	/**
	 * GET /connection
	 *
	 * @return WP_REST_Response
	 */
	public function get_connection(): WP_REST_Response {
		return rest_ensure_response( FP_Copilot_Remote_Connection::status() );
	}

	/**
	 * POST /connection
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_connection( WP_REST_Request $request ) {
		$result = FP_Copilot_Remote_Connection::connect( (string) $request->get_param( 'endpoint' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		FP_Copilot_Remote_Connection::push_health();

		return rest_ensure_response( FP_Copilot_Remote_Connection::status() );
	}

	/**
	 * DELETE /connection
	 *
	 * @return WP_REST_Response
	 */
	public function delete_connection(): WP_REST_Response {
		FP_Copilot_Remote_Connection::disconnect();

		return rest_ensure_response(
			array(
				'connected' => false,
			)
		);
	}

	/**
	 * GET /health
	 *
	 * @return WP_REST_Response
	 */
	public function get_health(): WP_REST_Response {
		return rest_ensure_response( FP_Copilot_Health_Data::collect() );
	}
}
