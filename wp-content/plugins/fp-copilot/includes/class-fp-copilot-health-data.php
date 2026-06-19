<?php
/**
 * Site health and uptime metrics.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collects health and uptime data for REST responses and remote pushes.
 */
final class FP_Copilot_Health_Data {

	/**
	 * Collect the current site health snapshot.
	 *
	 * @return array<string, mixed>
	 */
	public static function collect(): array {
		$modules = array();

		if ( function_exists( 'fp_copilot' ) ) {
			foreach ( fp_copilot()->modules()->all() as $id => $module ) {
				$modules[ $id ] = array(
					'name'        => $module->name(),
					'description' => $module->description(),
				);
			}
		}

		$uptime   = self::check_uptime();
		$database = self::check_database();
		$ssl      = self::check_ssl();
		$overall  = self::resolve_overall_status( $uptime, $database, $ssl );

		$data = array(
			'status'    => $overall,
			'timestamp' => gmdate( 'c' ),
			'site'      => array(
				'url'   => home_url( '/' ),
				'name'  => get_bloginfo( 'name' ),
				'admin' => admin_url(),
				'logo'  => self::get_site_logo_url(),
				'icon'  => self::get_site_icon_url(),
			),
			'plugin'    => array(
				'slug'    => 'fp-copilot',
				'version' => FP_COPILOT_VERSION,
			),
			'environment' => array(
				'wordpress' => get_bloginfo( 'version' ),
				'php'       => PHP_VERSION,
				'locale'    => determine_locale(),
				'timezone'  => wp_timezone_string(),
			),
			'uptime'    => $uptime,
			'database'  => $database,
			'ssl'       => $ssl,
			'forms'     => FP_Copilot_Form_Submissions::get_stats(),
			'modules'   => $modules,
		);

		/**
		 * Filter the health payload before it is returned or pushed remotely.
		 *
		 * @param array<string, mixed> $data Health snapshot.
		 */
		return apply_filters( 'fp_copilot_health_data', $data );
	}

	/**
	 * Returns the custom site logo URL from the active theme.
	 */
	private static function get_site_logo_url(): ?string {
		$logo_id = (int) get_theme_mod( 'custom_logo' );

		if ( $logo_id <= 0 ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $logo_id, 'full' );

		return is_string( $url ) && '' !== $url ? $url : null;
	}

	/**
	 * Returns the site icon (favicon) URL.
	 */
	private static function get_site_icon_url(): ?string {
		if ( ! function_exists( 'get_site_icon_url' ) ) {
			return null;
		}

		$url = get_site_icon_url();

		return is_string( $url ) && '' !== $url ? $url : null;
	}

	/**
	 * Perform an HTTP self-check against the site home URL.
	 *
	 * @return array<string, mixed>
	 */
	private static function check_uptime(): array {
		$url     = self::resolve_uptime_check_url();
		$started = microtime( true );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'user-agent'  => 'FirmPilot-Copilot-Monitor/' . FP_COPILOT_VERSION,
				'headers'     => array(
					'Cache-Control' => 'no-cache',
				),
			)
		);

		$elapsed_ms = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$error_code    = $response->get_error_code();

			return array(
				'status'       => 'down',
				'response'     => self::format_uptime_response( 'down', 0, $error_message, $error_code ),
				'response_ms'  => $elapsed_ms,
				'http_code'    => 0,
				'checked_url'  => $url,
				'public_url'   => home_url( '/' ),
				'error'        => $error_message,
				'error_code'   => $error_code,
			);
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$status = ( $code >= 200 && $code < 500 ) ? 'up' : 'down';
		$reason = wp_remote_retrieve_response_message( $response );

		return array(
			'status'      => $status,
			'response'    => self::format_uptime_response( $status, $code, is_string( $reason ) ? $reason : '' ),
			'response_ms' => $elapsed_ms,
			'http_code'   => $code,
			'checked_url' => $url,
			'public_url'  => home_url( '/' ),
		);
	}

	/**
	 * Build a human-readable uptime response summary.
	 */
	private static function format_uptime_response( string $status, int $http_code = 0, string $message = '', string $error_code = '' ): string {
		if ( 'up' === $status ) {
			return 'OK';
		}

		$parts = array();

		if ( $http_code > 0 ) {
			$parts[] = 'HTTP ' . $http_code;
		}

		if ( '' !== $error_code ) {
			$parts[] = $error_code;
		}

		if ( '' !== $message ) {
			$parts[] = $message;
		}

		if ( empty( $parts ) ) {
			return __( 'Site is unreachable.', 'fp-copilot' );
		}

		return implode( ': ', $parts );
	}

	/**
	 * Resolve the URL used for internal uptime checks.
	 *
	 * WordPress may be configured with a host-mapped URL (for example
	 * http://localhost:8080/) that is reachable in the browser but not from
	 * inside a container, where the web server listens on port 80.
	 */
	private static function resolve_uptime_check_url(): string {
		$url = home_url( '/' );

		/**
		 * Filter the URL used for uptime self-checks.
		 *
		 * @param string $url Default home URL.
		 */
		$url = (string) apply_filters( 'fp_copilot_uptime_check_url', $url );

		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) ) {
			return $url;
		}

		$host = strtolower( (string) $parts['host'] );
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === ( $parts['scheme'] ?? '' ) ? 443 : 80 );

		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) && 80 !== $port && 443 !== $port ) {
			$path    = $parts['path'] ?? '/';
			$scheme  = 'https' === ( $parts['scheme'] ?? '' ) ? 'https' : 'http';
			$internal_port = 'https' === $scheme ? 443 : 80;

			if ( 443 === $internal_port || 80 === $internal_port ) {
				return $scheme . '://127.0.0.1' . $path;
			}
		}

		return $url;
	}

	/**
	 * Verify database connectivity.
	 *
	 * @return array<string, mixed>
	 */
	private static function check_database(): array {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return array(
				'status' => 'down',
				'error'  => 'Database object unavailable.',
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( 'SELECT 1' );

		if ( '1' !== (string) $result ) {
			return array(
				'status' => 'down',
				'error'  => $wpdb->last_error ? $wpdb->last_error : 'Database query failed.',
			);
		}

		return array(
			'status' => 'up',
		);
	}

	/**
	 * Inspect the public site URL TLS certificate.
	 *
	 * @return array<string, mixed>
	 */
	private static function check_ssl(): array {
		$url   = home_url( '/' );
		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) ) {
			$error = __( 'Unable to parse the site URL.', 'fp-copilot' );

			return array(
				'enabled'     => false,
				'status'      => 'error',
				'response'    => self::format_ssl_response( 'error', $error ),
				'checked_url' => $url,
				'error'       => $error,
			);
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? 'http' ) );
		$host   = strtolower( (string) $parts['host'] );
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );

		if ( 'https' !== $scheme ) {
			$message = __( 'HTTPS is not configured for this site.', 'fp-copilot' );

			return array(
				'enabled'         => false,
				'status'          => 'not_configured',
				'response'        => self::format_ssl_response( 'not_configured', $message ),
				'checked_url'     => $url,
				'hostname'        => $host,
				'expiration_date' => null,
			);
		}

		if ( ! function_exists( 'openssl_x509_parse' ) ) {
			$error = __( 'The OpenSSL extension is not available.', 'fp-copilot' );

			return array(
				'enabled'     => true,
				'status'      => 'error',
				'response'    => self::format_ssl_response( 'error', $error ),
				'checked_url' => $url,
				'hostname'    => $host,
				'port'        => $port,
				'error'       => $error,
			);
		}

		$context = stream_context_create(
			array(
				'ssl' => array(
					'capture_peer_cert' => true,
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'SNI_enabled'       => true,
					'peer_name'         => $host,
				),
			)
		);

		$socket = @stream_socket_client(
			sprintf( 'ssl://%s:%d', $host, $port ),
			$errno,
			$errstr,
			15,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( false === $socket ) {
			$error = '' !== $errstr ? $errstr : __( 'Unable to connect to the TLS endpoint.', 'fp-copilot' );

			return array(
				'enabled'     => true,
				'status'      => 'unreachable',
				'response'    => self::format_ssl_response( 'unreachable', $error, $errno ),
				'checked_url' => $url,
				'hostname'    => $host,
				'port'        => $port,
				'error'       => $error,
				'error_code'  => $errno,
			);
		}

		fclose( $socket );

		$params = stream_context_get_params( $context );
		$cert   = $params['options']['ssl']['peer_certificate'] ?? null;

		if ( ! $cert ) {
			$error = __( 'No certificate was returned by the server.', 'fp-copilot' );

			return array(
				'enabled'     => true,
				'status'      => 'error',
				'response'    => self::format_ssl_response( 'error', $error ),
				'checked_url' => $url,
				'hostname'    => $host,
				'port'        => $port,
				'error'       => $error,
			);
		}

		$parsed = openssl_x509_parse( $cert );

		if ( ! is_array( $parsed ) ) {
			$error = __( 'Unable to parse the TLS certificate.', 'fp-copilot' );

			return array(
				'enabled'     => true,
				'status'      => 'error',
				'response'    => self::format_ssl_response( 'error', $error ),
				'checked_url' => $url,
				'hostname'    => $host,
				'port'        => $port,
				'error'       => $error,
			);
		}

		$valid_from_time = isset( $parsed['validFrom_time_t'] ) ? (int) $parsed['validFrom_time_t'] : 0;
		$valid_to_time   = isset( $parsed['validTo_time_t'] ) ? (int) $parsed['validTo_time_t'] : 0;
		$now             = time();
		$days_remaining  = $valid_to_time > 0 ? (int) floor( ( $valid_to_time - $now ) / DAY_IN_SECONDS ) : null;

		/**
		 * Filter the number of days before expiry that marks a certificate as expiring soon.
		 *
		 * @param int $days Days threshold.
		 */
		$warning_days = (int) apply_filters( 'fp_copilot_ssl_expiry_warning_days', 30 );

		if ( $valid_to_time > 0 && $now > $valid_to_time ) {
			$status = 'expired';
		} elseif ( null !== $days_remaining && $days_remaining <= $warning_days ) {
			$status = 'expiring_soon';
		} else {
			$status = 'valid';
		}

		$issuer  = self::format_x509_name( $parsed['issuer'] ?? array() );
		$subject = self::format_x509_name( $parsed['subject'] ?? array() );
		$expiration_date = self::format_cert_date( $valid_to_time );

		return array(
			'enabled'          => true,
			'status'           => $status,
			'response'         => self::format_ssl_response( $status, '', 0, $days_remaining, $expiration_date ),
			'checked_url'      => $url,
			'hostname'         => $host,
			'port'             => $port,
			'valid_from'       => self::format_cert_datetime( $valid_from_time ),
			'expires_at'       => self::format_cert_datetime( $valid_to_time ),
			'expiration_date'  => $expiration_date,
			'days_remaining'   => $days_remaining,
			'issuer'           => $issuer,
			'subject'          => $subject,
			'is_self_signed'   => $issuer === $subject && '' !== $issuer,
		);
	}

	/**
	 * Build a human-readable SSL response summary.
	 */
	private static function format_ssl_response( string $status, string $message = '', int $error_code = 0, ?int $days_remaining = null, ?string $expiration_date = null ): string {
		if ( in_array( $status, array( 'valid', 'expiring_soon' ), true ) ) {
			return 'OK';
		}

		$parts = array();

		if ( 'not_configured' === $status ) {
			$parts[] = __( 'SSL not configured', 'fp-copilot' );
		} elseif ( 'expired' === $status ) {
			$parts[] = __( 'Certificate expired', 'fp-copilot' );
			if ( $expiration_date ) {
				$parts[] = $expiration_date;
			}
		} elseif ( 'unreachable' === $status ) {
			$parts[] = __( 'TLS endpoint unreachable', 'fp-copilot' );
		} else {
			$parts[] = __( 'SSL error', 'fp-copilot' );
		}

		if ( $error_code > 0 ) {
			$parts[] = 'errno ' . $error_code;
		}

		if ( '' !== $message ) {
			$parts[] = $message;
		}

		return implode( ': ', array_filter( $parts ) );
	}

	/**
	 * Format a certificate timestamp as ISO 8601.
	 */
	private static function format_cert_datetime( int $timestamp ): ?string {
		return $timestamp > 0 ? gmdate( 'c', $timestamp ) : null;
	}

	/**
	 * Format a certificate expiration as a date (Y-m-d).
	 */
	private static function format_cert_date( int $timestamp ): ?string {
		return $timestamp > 0 ? gmdate( 'Y-m-d', $timestamp ) : null;
	}

	/**
	 * Resolve the top-level health status.
	 *
	 * @param array<string, mixed> $uptime   Uptime check result.
	 * @param array<string, mixed> $database Database check result.
	 * @param array<string, mixed> $ssl      SSL check result.
	 */
	private static function resolve_overall_status( array $uptime, array $database, array $ssl ): string {
		$healthy = ( $uptime['status'] ?? 'down' ) === 'up'
			&& ( $database['status'] ?? 'down' ) === 'up';

		if ( ! $healthy ) {
			return 'degraded';
		}

		if ( ! empty( $ssl['enabled'] ) && in_array( $ssl['status'] ?? '', array( 'expired', 'unreachable', 'error' ), true ) ) {
			return 'degraded';
		}

		return 'healthy';
	}

	/**
	 * Format an X.509 issuer or subject for display.
	 *
	 * @param array<string, mixed> $name Certificate name parts.
	 */
	private static function format_x509_name( array $name ): string {
		if ( ! empty( $name['CN'] ) ) {
			return (string) $name['CN'];
		}

		if ( ! empty( $name['O'] ) ) {
			return (string) $name['O'];
		}

		$parts = array();

		foreach ( array( 'CN', 'O', 'OU' ) as $key ) {
			if ( ! empty( $name[ $key ] ) ) {
				$parts[] = (string) $name[ $key ];
			}
		}

		return implode( ', ', $parts );
	}
}
