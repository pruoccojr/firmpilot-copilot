<?php
/**
 * External application connection for health pushes.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores and manages a remote monitoring connection.
 */
final class FP_Copilot_Remote_Connection {

	/**
	 * Option key for connection settings.
	 */
	public const OPTION_NAME = 'fp_copilot_remote_connection';

	/**
	 * Cron hook for scheduled health pushes.
	 */
	public const CRON_HOOK = 'fp_copilot_push_health';

	/**
	 * Cron schedule name.
	 */
	public const CRON_SCHEDULE = 'fp_copilot_every_minute';

	/**
	 * Register cron schedule and hook.
	 */
	public static function register(): void {
		add_filter( 'cron_schedules', array( self::class, 'add_cron_schedule' ) );
		add_action( self::CRON_HOOK, array( self::class, 'push_health' ) );
		add_action( 'init', array( self::class, 'ensure_cron_scheduled' ) );
	}

	/**
	 * Ensure cron is scheduled when a connection is active.
	 */
	public static function ensure_cron_scheduled(): void {
		if ( self::is_connected() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::schedule_push();
		}
	}

	/**
	 * Add a one-minute cron interval.
	 *
	 * @param array<string, array<string, int|string>> $schedules Existing schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public static function add_cron_schedule( array $schedules ): array {
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute', 'fp-copilot' ),
		);

		return $schedules;
	}

	/**
	 * Returns the stored connection configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$connection = get_option( self::OPTION_NAME, array() );

		return is_array( $connection ) ? $connection : array();
	}

	/**
	 * Whether a remote connection is active.
	 */
	public static function is_connected(): bool {
		$connection = self::get();

		return ! empty( $connection['active'] ) && ! empty( $connection['endpoint'] );
	}

	/**
	 * Connect an external application endpoint.
	 *
	 * @param string $endpoint Remote ingest URL.
	 * @return true|WP_Error
	 */
	public static function connect( string $endpoint ) {
		$endpoint = esc_url_raw( trim( $endpoint ) );

		if ( '' === $endpoint ) {
			return new WP_Error(
				'fp_copilot_connection_endpoint_required',
				__( 'A remote endpoint URL is required.', 'fp-copilot' ),
				array( 'status' => 400 )
			);
		}

		if ( ! self::is_allowed_endpoint( $endpoint ) ) {
			return new WP_Error(
				'fp_copilot_connection_endpoint_invalid',
				__( 'The remote endpoint URL is not allowed.', 'fp-copilot' ),
				array( 'status' => 400 )
			);
		}

		$connection = array(
			'active'       => true,
			'endpoint'     => $endpoint,
			'connected_at' => gmdate( 'c' ),
			'last_push_at' => null,
			'last_status'  => null,
			'last_error'   => null,
		);

		update_option( self::OPTION_NAME, $connection, false );
		self::schedule_push();

		/**
		 * Fires when an external app connects to this site.
		 *
		 * @param array<string, mixed> $connection Connection settings.
		 */
		do_action( 'fp_copilot_remote_connected', $connection );

		return true;
	}

	/**
	 * Disconnect the external application.
	 *
	 * @return true
	 */
	public static function disconnect(): bool {
		self::unschedule_push();
		delete_option( self::OPTION_NAME );

		/**
		 * Fires when an external app disconnects from this site.
		 */
		do_action( 'fp_copilot_remote_disconnected' );

		return true;
	}

	/**
	 * Return a sanitized connection status payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function status(): array {
		$connection = self::get();

		return array(
			'connected'    => self::is_connected(),
			'endpoint'     => $connection['endpoint'] ?? '',
			'connected_at' => $connection['connected_at'] ?? null,
			'last_push_at' => $connection['last_push_at'] ?? null,
			'last_status'  => $connection['last_status'] ?? null,
			'last_error'   => $connection['last_error'] ?? null,
			'next_push_at' => self::get_next_push_timestamp(),
		);
	}

	/**
	 * Push the current health snapshot to the connected endpoint.
	 */
	public static function push_health(): void {
		if ( ! self::is_connected() ) {
			return;
		}

		$connection = self::get();
		$endpoint   = (string) ( $connection['endpoint'] ?? '' );
		$payload    = FP_Copilot_Health_Data::collect();

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'        => 'application/json; charset=utf-8',
					'Accept'              => 'application/json',
					FP_Copilot_Api_Auth::HEADER_NAME => FP_Copilot_Api_Key::get(),
					'User-Agent'          => 'FirmPilot-Copilot-Monitor/' . FP_COPILOT_VERSION,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		$connection['last_push_at'] = gmdate( 'c' );

		if ( is_wp_error( $response ) ) {
			$connection['last_status'] = 'error';
			$connection['last_error']  = $response->get_error_message();
			update_option( self::OPTION_NAME, $connection, false );

			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			$connection['last_status'] = 'success';
			$connection['last_error']  = null;
		} else {
			$connection['last_status'] = 'error';
			$connection['last_error']  = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Remote endpoint returned HTTP %d.', 'fp-copilot' ),
				$code
			);
		}

		update_option( self::OPTION_NAME, $connection, false );

		/**
		 * Fires after a health payload is pushed to the remote endpoint.
		 *
		 * @param array<string, mixed>                   $payload    Health payload.
		 * @param array<string, mixed>|WP_Error          $response   HTTP response or error.
		 * @param array<string, mixed>                   $connection Connection settings.
		 */
		do_action( 'fp_copilot_health_pushed', $payload, $response, $connection );
	}

	/**
	 * Send an immediate deactivation ping to the connected endpoint.
	 */
	public static function push_deactivation_ping(): void {
		if ( ! self::is_connected() ) {
			return;
		}

		$connection = self::get();
		$endpoint   = (string) ( $connection['endpoint'] ?? '' );

		if ( '' === $endpoint ) {
			return;
		}

		$payload = self::build_deactivation_payload( $connection );

		wp_remote_post(
			$endpoint,
			array(
				'timeout'  => 15,
				'blocking' => true,
				'headers'  => array(
					'Content-Type'                   => 'application/json; charset=utf-8',
					'Accept'                         => 'application/json',
					FP_Copilot_Api_Auth::HEADER_NAME => FP_Copilot_Api_Key::get(),
					'User-Agent'                     => 'FirmPilot-Copilot-Monitor/' . FP_COPILOT_VERSION,
				),
				'body'     => wp_json_encode( $payload ),
			)
		);

		/**
		 * Fires after a deactivation ping is sent to the remote endpoint.
		 *
		 * @param array<string, mixed> $payload    Deactivation payload.
		 * @param array<string, mixed> $connection Connection settings.
		 */
		do_action( 'fp_copilot_deactivation_ping_sent', $payload, $connection );
	}

	/**
	 * Build the payload sent when the plugin is deactivated.
	 *
	 * @param array<string, mixed> $connection Active connection settings.
	 * @return array<string, mixed>
	 */
	private static function build_deactivation_payload( array $connection ): array {
		$payload = array(
			'status'    => 'deactivated',
			'event'     => 'plugin_deactivated',
			'timestamp' => gmdate( 'c' ),
			'site'      => array(
				'url'  => home_url( '/' ),
				'name' => get_bloginfo( 'name' ),
			),
			'plugin'    => array(
				'slug'           => 'fp-copilot',
				'version'        => FP_COPILOT_VERSION,
				'active'         => false,
				'deactivated_at' => gmdate( 'c' ),
			),
			'connection' => array(
				'was_connected' => true,
				'endpoint'      => $connection['endpoint'] ?? '',
				'connected_at'  => $connection['connected_at'] ?? null,
				'last_push_at'  => $connection['last_push_at'] ?? null,
			),
			'message'   => __( 'FirmPilot Copilot has been deactivated on this site.', 'fp-copilot' ),
		);

		/**
		 * Filter the deactivation ping payload.
		 *
		 * @param array<string, mixed> $payload    Deactivation payload.
		 * @param array<string, mixed> $connection Connection settings.
		 */
		return apply_filters( 'fp_copilot_deactivation_payload', $payload, $connection );
	}

	/**
	 * Handle plugin deactivation: notify remote app, then clean up.
	 */
	public static function handle_plugin_deactivation(): void {
		self::push_deactivation_ping();
		self::disconnect();
	}

	/**
	 * Schedule recurring health pushes.
	 */
	public static function schedule_push(): void {
		self::unschedule_push();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * Stop scheduled health pushes.
	 */
	public static function unschedule_push(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Unix timestamp for the next scheduled push, if any.
	 */
	private static function get_next_push_timestamp(): ?int {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		return $timestamp ? (int) $timestamp : null;
	}

	/**
	 * Validate a remote endpoint URL.
	 */
	private static function is_allowed_endpoint( string $endpoint ): bool {
		$parts = wp_parse_url( $endpoint );

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		$scheme = strtolower( (string) $parts['scheme'] );

		if ( ! in_array( $scheme, array( 'https', 'http' ), true ) ) {
			return false;
		}

		/**
		 * Filter whether a remote monitoring endpoint URL is allowed.
		 *
		 * @param bool   $allowed  Whether the endpoint is allowed.
		 * @param string $endpoint Endpoint URL.
		 */
		$allowed = apply_filters( 'fp_copilot_connection_endpoint_allowed', 'https' === $scheme, $endpoint );

		if ( ! $allowed ) {
			return false;
		}

		if ( function_exists( 'wp_http_validate_url' ) ) {
			return (bool) wp_http_validate_url( $endpoint );
		}

		return true;
	}
}
