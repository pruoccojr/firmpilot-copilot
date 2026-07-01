<?php
/**
 * Reviews for FirmPilot – block→renderer mapping and Shortcode UI metadata.
 * Defaults always come from {@see FP_Reviews_Render::default_options()}.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FP_Reviews_Control_Schema {

	/**
	 * @return array<string, string>
	 */
	private static function get_block_to_renderer_attribute_map() {
		return array(
			'category'               => 'category',
			'limit'                  => 'limit',
			'orderby'                => 'orderby',
			'order'                  => 'order',
			'title_element'          => 'title_element',
			'grid_style'             => 'grid_style',
			'column_gap'             => 'column_gap',
			'columns_desktop'        => 'columns_desktop',
			'columns_tablet'         => 'columns_tablet',
			'columns_mobile'         => 'columns_mobile',
			'imageShape'             => 'image_shape',
			'imagePosition'          => 'image_position',
			'imageSizeRem'           => 'image_size_rem',
			'carouselAutoplayMs'     => 'carousel_autoplay_ms',
			'carouselPauseOnHover'   => 'carousel_pause_on_hover',
			'carouselShowArrows'     => 'carousel_show_arrows',
			'carouselShowDots'       => 'carousel_show_dots',
			'showUserImage'          => 'show_user_image',
			'showRating'             => 'show_rating',
			'show_company'           => 'show_company',
			'show_job_title'         => 'show_job_title',
			'show_location'          => 'show_location',
			'hidePlaceholderImages'  => 'hide_placeholder_images',
		);
	}

	/**
	 * @param array $attributes Block attributes from `render_callback`.
	 * @return array
	 */
	public static function map_block_attributes_to_renderer_atts( array $attributes ) {
		$defaults = FP_Reviews_Render::default_options();
		$out      = $defaults;
		foreach ( self::get_block_to_renderer_attribute_map() as $block_key => $render_key ) {
			if ( ! array_key_exists( $block_key, $attributes ) ) {
				continue;
			}
			$raw      = $attributes[ $block_key ];
			$template = $defaults[ $render_key ];
			if ( is_bool( $template ) ) {
				$out[ $render_key ] = (bool) $raw;
			} elseif ( 'image_size_rem' === $render_key ) {
				$out[ $render_key ] = (float) $raw;
			} elseif ( is_int( $template ) ) {
				if ( 'limit' === $render_key && ( $raw === '' || $raw === '-1' || $raw === null ) ) {
					$out['limit'] = -1;
				} else {
					$out[ $render_key ] = (int) $raw;
				}
			} else {
				$out[ $render_key ] = (string) $raw;
			}
		}
		if ( array_key_exists( 'order', $attributes ) ) {
			$out['order'] = strtoupper( (string) $attributes['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		}
		$layout = isset( $attributes['layoutDisplay'] ) ? (string) $attributes['layoutDisplay'] : '';
		if ( $layout === '' || ! in_array( $layout, array( 'grid', 'carousel' ), true ) ) {
			$out['display'] = ! empty( $attributes['display_as_carousel'] ) ? 'carousel' : 'grid';
		} else {
			$out['display'] = $layout;
		}
		if ( isset( $attributes['grid_style'] ) && 'standard' === (string) $attributes['grid_style'] ) {
			$out['grid_style'] = 'grid';
		}
		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_shortcode_defaults_strings() {
		$defaults = FP_Reviews_Render::default_options();
		$out      = array();
		foreach ( $defaults as $key => $value ) {
			if ( 'category_slugs' === $key ) {
				continue;
			}
			if ( is_bool( $value ) ) {
				$out[ $key ] = $value ? '1' : '0';
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$out[ $key ] = (string) $value;
			} else {
				$out[ $key ] = (string) $value;
			}
		}
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_builder_field_ui_definitions() {
		return array(
			array(
				'id'    => 'category',
				'label' => __( 'Category slugs', 'firmpilot-reviews' ),
				'type'  => 'text',
				'help'  => __( 'Comma-separated category slugs, or leave empty for all.', 'firmpilot-reviews' ),
			),
			array(
				'id'    => 'limit',
				'label' => __( 'Limit', 'firmpilot-reviews' ),
				'type'  => 'text',
				'help'  => __( 'Maximum reviews (-1 for no limit).', 'firmpilot-reviews' ),
			),
			array(
				'id'      => 'orderby',
				'label'   => __( 'Order by', 'firmpilot-reviews' ),
				'type'    => 'select',
				'options' => array(
					'sort_order' => __( 'Sort order', 'firmpilot-reviews' ),
					'title'      => __( 'Title', 'firmpilot-reviews' ),
					'date'       => __( 'Date', 'firmpilot-reviews' ),
				),
			),
			array(
				'id'      => 'order',
				'label'   => __( 'Order', 'firmpilot-reviews' ),
				'type'    => 'select',
				'options' => array(
					'ASC'  => __( 'Ascending', 'firmpilot-reviews' ),
					'DESC' => __( 'Descending', 'firmpilot-reviews' ),
				),
			),
			array(
				'id'      => 'display',
				'label'   => __( 'Layout', 'firmpilot-reviews' ),
				'type'    => 'select',
				'options' => array(
					'grid'     => __( 'Grid', 'firmpilot-reviews' ),
					'carousel' => __( 'Carousel', 'firmpilot-reviews' ),
				),
			),
			array(
				'id'      => 'title_element',
				'label'   => __( 'Title element', 'firmpilot-reviews' ),
				'type'    => 'select',
				'options' => array(
					'h2'  => 'h2',
					'h3'  => 'h3',
					'h4'  => 'h4',
					'h5'  => 'h5',
					'h6'  => 'h6',
					'div' => 'div',
					'p'   => 'p',
				),
			),
			array(
				'id'      => 'grid_style',
				'label'   => __( 'Grid style', 'firmpilot-reviews' ),
				'type'    => 'select',
				'options' => array(
					'grid'    => __( 'Equal height', 'firmpilot-reviews' ),
					'masonry' => __( 'Masonry', 'firmpilot-reviews' ),
				),
			),
			array(
				'id'    => 'columns_desktop',
				'label' => __( 'Columns (desktop)', 'firmpilot-reviews' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'columns_tablet',
				'label' => __( 'Columns (tablet)', 'firmpilot-reviews' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'columns_mobile',
				'label' => __( 'Columns (mobile)', 'firmpilot-reviews' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'column_gap',
				'label' => __( 'Column gap', 'firmpilot-reviews' ),
				'type'  => 'text',
			),
			array(
				'id'      => 'image_shape',
				'label'   => __( 'Image shape', 'firmpilot-reviews' ),
				'type'    => 'select',
				'options' => array(
					'circle'  => __( 'Circle', 'firmpilot-reviews' ),
					'rounded' => __( 'Rounded', 'firmpilot-reviews' ),
					'square'  => __( 'Square', 'firmpilot-reviews' ),
				),
			),
			array(
				'id'      => 'image_position',
				'label'   => __( 'Image position', 'firmpilot-reviews' ),
				'type'    => 'select',
				'options' => array(
					'top'  => __( 'Top', 'firmpilot-reviews' ),
					'left' => __( 'Left', 'firmpilot-reviews' ),
				),
			),
			array(
				'id'    => 'image_size_rem',
				'label' => __( 'Image size (rem)', 'firmpilot-reviews' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'carousel_autoplay_ms',
				'label' => __( 'Carousel autoplay (ms, 0 = off)', 'firmpilot-reviews' ),
				'type'  => 'text',
			),
			array(
				'id'    => 'carousel_pause_on_hover',
				'label' => __( 'Pause carousel on hover', 'firmpilot-reviews' ),
				'type'  => 'checkbox',
			),
			array(
				'id'    => 'carousel_show_arrows',
				'label' => __( 'Show carousel arrows', 'firmpilot-reviews' ),
				'type'  => 'checkbox',
			),
			array(
				'id'    => 'carousel_show_dots',
				'label' => __( 'Show carousel dots', 'firmpilot-reviews' ),
				'type'  => 'checkbox',
			),
			array(
				'id'    => 'hide_placeholder_images',
				'label' => __( 'Hide placeholder images', 'firmpilot-reviews' ),
				'type'  => 'checkbox',
			),
			array(
				'id'    => 'show_user_image',
				'label' => __( 'Show reviewer image', 'firmpilot-reviews' ),
				'type'  => 'checkbox',
			),
			array(
				'id'    => 'show_rating',
				'label' => __( 'Show rating', 'firmpilot-reviews' ),
				'type'  => 'checkbox',
			),
			array(
				'id'    => 'show_company',
				'label' => __( 'Show company', 'firmpilot-reviews' ),
				'type'  => 'checkbox',
			),
			array(
				'id'    => 'show_job_title',
				'label' => __( 'Show job title', 'firmpilot-reviews' ),
				'type'  => 'checkbox',
			),
			array(
				'id'    => 'show_location',
				'label' => __( 'Show location', 'firmpilot-reviews' ),
				'type'  => 'checkbox',
			),
		);
	}

	/**
	 * @param array $fields Field UI definitions.
	 * @return array
	 */
	private static function merge_builder_field_defaults( array $fields ) {
		$defaults = FP_Reviews_Render::default_options();
		foreach ( $fields as &$field ) {
			$id = isset( $field['id'] ) ? (string) $field['id'] : '';
			if ( $id === '' || ! array_key_exists( $id, $defaults ) ) {
				continue;
			}
			$v = $defaults[ $id ];
			if ( ( $field['type'] ?? '' ) === 'checkbox' ) {
				$field['default'] = (bool) $v;
			} elseif ( is_bool( $v ) ) {
				$field['default'] = $v ? '1' : '0';
			} elseif ( is_int( $v ) || is_float( $v ) ) {
				$field['default'] = (string) $v;
			} else {
				$field['default'] = (string) $v;
			}
		}
		unset( $field );
		return $fields;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_builder_fields() {
		return self::merge_builder_field_defaults( self::get_builder_field_ui_definitions() );
	}
}
