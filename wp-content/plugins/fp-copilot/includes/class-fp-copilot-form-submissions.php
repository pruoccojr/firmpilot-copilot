<?php
/**
 * Form submission tracking for health reporting.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tracks non-auth form submissions from popular form plugins.
 */
final class FP_Copilot_Form_Submissions {

	/**
	 * Stored stats option.
	 */
	public const OPTION_NAME = 'fp_copilot_form_submission_stats';

	/**
	 * Register submission listeners.
	 */
	public static function register(): void {
		add_action( 'wpcf7_mail_sent', array( self::class, 'handle_contact_form_7' ), 10, 1 );
		add_action( 'wpforms_process_complete', array( self::class, 'handle_wpforms' ), 10, 4 );
		add_action( 'gform_after_submission', array( self::class, 'handle_gravity_forms' ), 10, 2 );
		add_action( 'fluentform/submission_inserted', array( self::class, 'handle_fluent_forms' ), 10, 3 );
		add_action( 'ninja_forms_after_submission', array( self::class, 'handle_ninja_forms' ), 10, 1 );
		add_action( 'forminator_form_after_handle_submit', array( self::class, 'handle_forminator' ), 10, 3 );
		add_action( 'frm_after_create_entry', array( self::class, 'handle_formidable' ), 10, 2 );
	}

	/**
	 * Return form submission stats for the health API.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_stats(): array {
		$stats    = self::get_stored_stats();
		$plugins  = self::detect_form_plugins();
		$totals   = self::calculate_totals( $stats );
		$sources  = self::summarize_sources( $stats );

		return array(
			'tracking_since'  => $stats['tracking_since'] ?? null,
			'plugins'         => $plugins,
			'totals'          => $totals,
			'last_submission' => $stats['last_submission'] ?? null,
			'by_source'       => $sources,
		);
	}

	/**
	 * Contact Form 7 submission handler.
	 *
	 * @param WPCF7_ContactForm $contact_form Contact form object.
	 */
	public static function handle_contact_form_7( $contact_form ): void {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) ) {
			return;
		}

		self::record_submission(
			'contact-form-7',
			(string) $contact_form->id(),
			(string) $contact_form->title(),
			(string) $contact_form->name()
		);
	}

	/**
	 * WPForms submission handler.
	 *
	 * @param array<string, mixed> $fields    Submitted fields.
	 * @param array<string, mixed> $entry     Entry data.
	 * @param array<string, mixed> $form_data Form configuration.
	 */
	public static function handle_wpforms( $fields, $entry, $form_data, $entry_id ): void {
		unset( $fields, $entry, $entry_id );

		if ( ! is_array( $form_data ) ) {
			return;
		}

		self::record_submission(
			'wpforms',
			(string) ( $form_data['id'] ?? '' ),
			(string) ( $form_data['settings']['form_title'] ?? __( 'WPForm', 'fp-copilot' ) ),
			(string) ( $form_data['settings']['form_title'] ?? '' )
		);
	}

	/**
	 * Gravity Forms submission handler.
	 *
	 * @param array<string, mixed> $entry Submitted entry.
	 * @param array<string, mixed> $form  Form configuration.
	 */
	public static function handle_gravity_forms( $entry, $form ): void {
		if ( ! is_array( $entry ) || ! is_array( $form ) ) {
			return;
		}

		self::record_submission(
			'gravity-forms',
			(string) ( $form['id'] ?? '' ),
			(string) ( $form['title'] ?? __( 'Gravity Form', 'fp-copilot' ) ),
			(string) ( $form['title'] ?? '' )
		);
	}

	/**
	 * Fluent Forms submission handler.
	 *
	 * @param int                  $entry_id Entry ID.
	 * @param array<string, mixed> $form_data Form data.
	 * @param object               $form     Form object.
	 */
	public static function handle_fluent_forms( $entry_id, $form_data, $form ): void {
		unset( $entry_id, $form_data );

		if ( ! is_object( $form ) ) {
			return;
		}

		self::record_submission(
			'fluent-forms',
			(string) ( $form->id ?? '' ),
			(string) ( $form->title ?? __( 'Fluent Form', 'fp-copilot' ) ),
			(string) ( $form->title ?? '' )
		);
	}

	/**
	 * Ninja Forms submission handler.
	 *
	 * @param array<string, mixed> $data Submission data.
	 */
	public static function handle_ninja_forms( $data ): void {
		if ( ! is_array( $data ) ) {
			return;
		}

		$form_id = (string) ( $data['form_id'] ?? '' );

		self::record_submission(
			'ninja-forms',
			$form_id,
			self::get_ninja_form_title( $form_id ),
			''
		);
	}

	/**
	 * Forminator submission handler.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $response Response data.
	 * @param object               $form_object Form object.
	 */
	public static function handle_forminator( $form_id, $response, $form_object ): void {
		unset( $response );

		$title = is_object( $form_object ) && isset( $form_object->settings['formName'] )
			? (string) $form_object->settings['formName']
			: __( 'Forminator Form', 'fp-copilot' );

		self::record_submission(
			'forminator',
			(string) $form_id,
			$title,
			''
		);
	}

	/**
	 * Formidable Forms submission handler.
	 *
	 * @param int                  $entry_id Entry ID.
	 * @param int|string           $form_id  Form ID.
	 */
	public static function handle_formidable( $entry_id, $form_id ): void {
		unset( $entry_id );

		$title = __( 'Formidable Form', 'fp-copilot' );

		if ( class_exists( 'FrmForm' ) ) {
			$form = FrmForm::getOne( (int) $form_id );
			if ( $form && ! empty( $form->name ) ) {
				$title = (string) $form->name;
			}
		}

		self::record_submission(
			'formidable',
			(string) $form_id,
			$title,
			''
		);
	}

	/**
	 * Record a non-auth form submission.
	 */
	private static function record_submission( string $source, string $form_id, string $form_title, string $form_slug = '' ): void {
		if ( self::should_exclude( $source, $form_id, $form_title, $form_slug ) ) {
			return;
		}

		$stats = self::get_stored_stats();
		$date  = gmdate( 'Y-m-d' );
		$key   = self::form_key( $form_id, $form_title );

		if ( empty( $stats['tracking_since'] ) ) {
			$stats['tracking_since'] = gmdate( 'c' );
		}

		$stats['total'] = (int) ( $stats['total'] ?? 0 ) + 1;

		if ( ! isset( $stats['daily'][ $date ] ) ) {
			$stats['daily'][ $date ] = 0;
		}
		++$stats['daily'][ $date ];

		if ( ! isset( $stats['sources'][ $source ] ) ) {
			$stats['sources'][ $source ] = array(
				'total' => 0,
				'forms' => array(),
			);
		}

		++$stats['sources'][ $source ]['total'];

		if ( ! isset( $stats['sources'][ $source ]['forms'][ $key ] ) ) {
			$stats['sources'][ $source ]['forms'][ $key ] = array(
				'id'    => $form_id,
				'title' => $form_title,
				'total' => 0,
			);
		}

		++$stats['sources'][ $source ]['forms'][ $key ]['total'];

		$stats['last_submission'] = array(
			'at'     => gmdate( 'c' ),
			'source' => $source,
			'form'   => array(
				'id'    => $form_id,
				'title' => $form_title,
			),
		);

		$stats['daily'] = self::prune_daily_counts( $stats['daily'] ?? array() );

		update_option( self::OPTION_NAME, $stats, false );

		/**
		 * Fires after a non-auth form submission is recorded.
		 *
		 * @param string               $source     Form plugin source slug.
		 * @param string               $form_id    Form ID.
		 * @param string               $form_title Form title.
		 * @param array<string, mixed> $stats      Updated stats snapshot.
		 */
		do_action( 'fp_copilot_form_submission_recorded', $source, $form_id, $form_title, $stats );
	}

	/**
	 * Whether a submission should be excluded from reporting.
	 */
	private static function should_exclude( string $source, string $form_id, string $form_title, string $form_slug ): bool {
		$haystack = strtolower( implode( ' ', array_filter( array( $source, $form_id, $form_title, $form_slug ) ) ) );
		$needles  = array(
			'login',
			'log-in',
			'log in',
			'sign-in',
			'signin',
			'sign in',
			'register',
			'registration',
			'signup',
			'sign-up',
			'password',
			'reset-password',
			'lost-password',
			'forgot-password',
			'authenticate',
			'authentication',
			'auth',
			'2fa',
			'mfa',
			'otp',
			'user-login',
			'wp-login',
		);

		foreach ( $needles as $needle ) {
			if ( str_contains( $haystack, $needle ) ) {
				return true;
			}
		}

		/**
		 * Filter whether a form submission should be excluded from health reporting.
		 *
		 * @param bool   $exclude     Whether to exclude the submission.
		 * @param string $source      Form plugin source slug.
		 * @param string $form_id     Form ID.
		 * @param string $form_title  Form title.
		 * @param string $form_slug   Form slug, if available.
		 */
		return (bool) apply_filters( 'fp_copilot_exclude_form_submission', false, $source, $form_id, $form_title, $form_slug );
	}

	/**
	 * Detect installed form plugins.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function detect_form_plugins(): array {
		$plugins = array();

		$candidates = array(
			'contact-form-7' => array(
				'active' => defined( 'WPCF7_VERSION' ),
				'name'   => 'Contact Form 7',
			),
			'wpforms'        => array(
				'active' => function_exists( 'wpforms' ),
				'name'   => 'WPForms',
			),
			'gravity-forms'  => array(
				'active' => class_exists( 'GFForms' ),
				'name'   => 'Gravity Forms',
			),
			'fluent-forms'   => array(
				'active' => defined( 'FLUENTFORM_VERSION' ),
				'name'   => 'Fluent Forms',
			),
			'ninja-forms'    => array(
				'active' => class_exists( 'Ninja_Forms' ),
				'name'   => 'Ninja Forms',
			),
			'forminator'     => array(
				'active' => class_exists( 'Forminator' ),
				'name'   => 'Forminator',
			),
			'formidable'     => array(
				'active' => class_exists( 'FrmForm' ),
				'name'   => 'Formidable Forms',
			),
		);

		foreach ( $candidates as $slug => $plugin ) {
			if ( ! empty( $plugin['active'] ) ) {
				$plugins[ $slug ] = $plugin;
			}
		}

		return $plugins;
	}

	/**
	 * Calculate aggregate totals from stored stats.
	 *
	 * @param array<string, mixed> $stats Stored stats.
	 * @return array<string, int>
	 */
	private static function calculate_totals( array $stats ): array {
		$daily = is_array( $stats['daily'] ?? null ) ? $stats['daily'] : array();

		return array(
			'all_time'      => (int) ( $stats['total'] ?? 0 ),
			'last_24_hours' => (int) ( $daily[ gmdate( 'Y-m-d' ) ] ?? 0 ),
			'last_7_days'   => self::sum_daily_counts( $daily, 7 ),
			'last_30_days'  => self::sum_daily_counts( $daily, 30 ),
		);
	}

	/**
	 * Summarize per-source stats for API output.
	 *
	 * @param array<string, mixed> $stats Stored stats.
	 * @return array<string, mixed>
	 */
	private static function summarize_sources( array $stats ): array {
		$output  = array();
		$sources = is_array( $stats['sources'] ?? null ) ? $stats['sources'] : array();

		foreach ( $sources as $source => $source_stats ) {
			$forms = array();

			foreach ( (array) ( $source_stats['forms'] ?? array() ) as $form_stats ) {
				$forms[] = array(
					'id'    => (string) ( $form_stats['id'] ?? '' ),
					'title' => (string) ( $form_stats['title'] ?? '' ),
					'total' => (int) ( $form_stats['total'] ?? 0 ),
				);
			}

			usort(
				$forms,
				static function ( $left, $right ) {
					return ( $right['total'] ?? 0 ) <=> ( $left['total'] ?? 0 );
				}
			);

			$output[ $source ] = array(
				'total' => (int) ( $source_stats['total'] ?? 0 ),
				'forms' => $forms,
			);
		}

		return $output;
	}

	/**
	 * Sum daily counts for the most recent number of days.
	 *
	 * @param array<string, int> $daily Daily counts keyed by Y-m-d.
	 */
	private static function sum_daily_counts( array $daily, int $days ): int {
		$total = 0;

		for ( $offset = 0; $offset < $days; $offset++ ) {
			$date = gmdate( 'Y-m-d', time() - ( $offset * DAY_IN_SECONDS ) );
			$total += (int) ( $daily[ $date ] ?? 0 );
		}

		return $total;
	}

	/**
	 * Keep only the most recent daily buckets.
	 *
	 * @param array<string, int> $daily Daily counts.
	 * @return array<string, int>
	 */
	private static function prune_daily_counts( array $daily ): array {
		krsort( $daily );

		return array_slice( $daily, 0, 30, true );
	}

	/**
	 * Build a stable per-form stats key.
	 */
	private static function form_key( string $form_id, string $form_title ): string {
		if ( '' !== $form_id ) {
			return sanitize_key( $form_id );
		}

		return sanitize_key( $form_title );
	}

	/**
	 * Get stored stats with defaults.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_stored_stats(): array {
		$stats = get_option( self::OPTION_NAME, array() );

		return is_array( $stats ) ? $stats : array();
	}

	/**
	 * Resolve a Ninja Forms title from its ID.
	 */
	private static function get_ninja_form_title( string $form_id ): string {
		if ( '' === $form_id || ! class_exists( 'Ninja_Forms' ) ) {
			return __( 'Ninja Form', 'fp-copilot' );
		}

		$form = Ninja_Forms()->form( (int) $form_id )->get();

		if ( $form && method_exists( $form, 'get_setting' ) ) {
			$title = $form->get_setting( 'title' );
			if ( is_string( $title ) && '' !== $title ) {
				return $title;
			}
		}

		return __( 'Ninja Form', 'fp-copilot' );
	}
}
