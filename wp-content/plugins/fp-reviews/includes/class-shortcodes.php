<?php
/**
 * Reviews for FirmPilot – shortcode registration and Shortcode Builder admin UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FP_Reviews_Shortcodes {

	const TAG = 'firmpilot_reviews';

	public function __construct() {
		add_shortcode( self::TAG, array( $this, 'render_shortcode' ) );
		add_filter( 'fp_reviews_shortcode_builder_definitions', array( $this, 'register_shortcode_builder' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_shortcode_builder' ) );
	}

	/**
	 * @param array $definitions Slug => panel config.
	 * @return array
	 */
	public function register_shortcode_builder( $definitions ) {
		if ( ! is_array( $definitions ) ) {
			$definitions = array();
		}
		$definitions['reviews'] = array(
			'label'           => __( 'Reviews for FirmPilot', 'firmpilot-reviews' ),
			'shortcode_tag'   => self::TAG,
			'render_callback' => array( $this, 'render_builder_panel' ),
		);
		return $definitions;
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_shortcode_builder( $hook_suffix ) {
		if ( 'fp_review_page_firmpilot-reviews-settings' !== $hook_suffix ) {
			return;
		}
		wp_register_script(
			'fp-reviews-shortcode-builder',
			fp_reviews_asset_url( 'includes/js/shortcode-builder.js' ),
			array(),
			FP_REVIEWS_VERSION,
			true
		);
		wp_script_add_data( 'fp-reviews-shortcode-builder', 'strategy', 'defer' );
		wp_enqueue_script( 'fp-reviews-shortcode-builder' );
		wp_localize_script(
			'fp-reviews-shortcode-builder',
			'fpReviewsShortcodeBuilder',
			array(
				'tag'            => self::TAG,
				'copyDone'       => __( 'Copied.', 'firmpilot-reviews' ),
				'copyManual'     => __( 'Select and copy manually.', 'firmpilot-reviews' ),
				'generatePrompt' => __( 'Choose options and click Generate.', 'firmpilot-reviews' ),
			)
		);
	}

	public function render_builder_panel() {
		$fields  = FP_Reviews_Control_Schema::get_builder_fields();
		$example = '[' . self::TAG . ']';
		echo '<p class="description">';
		esc_html_e( 'Choose options below, then copy the generated shortcode into any post, page, or classic widget.', 'firmpilot-reviews' );
		echo '</p>';
		echo '<p><code>' . esc_html( $example ) . '</code> — ';
		esc_html_e( 'empty options use plugin defaults.', 'firmpilot-reviews' );
		echo '</p>';

		echo '<form class="fp-reviews-shortcode-builder fp-reviews-shortcode-builder--reviews" id="fp-reviews-shortcode-reviews" onsubmit="return false;">';
		echo '<table class="form-table" role="presentation">';
		foreach ( $fields as $field ) {
			$id    = 'fp-sc-reviews-' . $field['id'];
			$name  = $field['id'];
			$label = isset( $field['label'] ) ? $field['label'] : $name;
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';

			if ( ( $field['type'] ?? '' ) === 'checkbox' ) {
				echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" data-fp-shortcode-field="' . esc_attr( $name ) . '" data-fp-boolean="1" value="1" ' . checked( ! empty( $field['default'] ), true, false ) . '> ';
				if ( ! empty( $field['help'] ) ) {
					echo esc_html( $field['help'] );
				}
				echo '</label>';
			} elseif ( ( $field['type'] ?? '' ) === 'select' && ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				echo '<select id="' . esc_attr( $id ) . '" data-fp-shortcode-field="' . esc_attr( $name ) . '">';
				foreach ( $field['options'] as $val => $opt_label ) {
					echo '<option value="' . esc_attr( (string) $val ) . '" ' . selected( (string) $val, (string) ( $field['default'] ?? '' ), false ) . '>' . esc_html( (string) $opt_label ) . '</option>';
				}
				echo '</select>';
				if ( ! empty( $field['help'] ) ) {
					echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
				}
			} else {
				echo '<input class="regular-text" type="text" id="' . esc_attr( $id ) . '" data-fp-shortcode-field="' . esc_attr( $name ) . '" value="' . esc_attr( (string) ( $field['default'] ?? '' ) ) . '">';
				if ( ! empty( $field['help'] ) ) {
					echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
				}
			}
			echo '</td></tr>';
		}
		echo '</table>';
		echo '<p><button type="button" class="button button-primary" id="fp-reviews-shortcode-reviews-generate">';
		esc_html_e( 'Generate shortcode', 'firmpilot-reviews' );
		echo '</button></p>';
		echo '<p><label for="fp-reviews-shortcode-reviews-output"><strong>';
		esc_html_e( 'Generated shortcode', 'firmpilot-reviews' );
		echo '</strong></label></p>';
		echo '<textarea id="fp-reviews-shortcode-reviews-output" class="large-text code" rows="3" readonly></textarea>';
		echo '<p><button type="button" class="button" id="fp-reviews-shortcode-reviews-copy">';
		esc_html_e( 'Copy to clipboard', 'firmpilot-reviews' );
		echo '</button> <span class="fp-reviews-shortcode-copy-msg" role="status" aria-live="polite" aria-atomic="true" hidden></span></p>';
		echo '</form>';
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$defaults = FP_Reviews_Control_Schema::get_shortcode_defaults_strings();
		$parsed   = shortcode_atts( $defaults, $atts, self::TAG );
		return fp_reviews_render( self::normalize_shortcode_atts( $parsed ) );
	}

	/**
	 * @param array $parsed String values from shortcode_atts.
	 * @return array
	 */
	private static function normalize_shortcode_atts( array $parsed ) {
		$boolean_keys = array();
		foreach ( FP_Reviews_Render::default_options() as $key => $val ) {
			if ( is_bool( $val ) ) {
				$boolean_keys[] = $key;
			}
		}
		$int_keys   = array( 'limit', 'columns_desktop', 'columns_tablet', 'columns_mobile', 'carousel_autoplay_ms' );
		$float_keys = array( 'image_size_rem' );
		$out        = array();
		foreach ( $parsed as $key => $value ) {
			if ( in_array( $key, $boolean_keys, true ) ) {
				$out[ $key ] = in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
				continue;
			}
			if ( in_array( $key, $int_keys, true ) ) {
				$iv = (int) $value;
				if ( 'limit' === $key && $iv < 1 ) {
					$out[ $key ] = -1;
				} else {
					$out[ $key ] = $iv;
				}
				continue;
			}
			if ( in_array( $key, $float_keys, true ) ) {
				$out[ $key ] = (float) $value;
				continue;
			}
			$out[ $key ] = $value;
		}
		return $out;
	}
}
