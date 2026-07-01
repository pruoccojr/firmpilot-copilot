<?php
/**
 * Reviews for FirmPilot – shared plugin helpers (wrap, editor, admin hooks, CLI).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether to load minified CSS/JS (off when SCRIPT_DEBUG is true).
 *
 * @return bool
 */
function fp_reviews_use_minified_assets() {
	return ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
}

/**
 * Plugin asset URL; uses `.min.css` / `.min.js` in production.
 *
 * @param string $relative_path Path relative to plugin root (e.g. assets/css/styles.css).
 * @return string
 */
function fp_reviews_asset_url( $relative_path ) {
	$relative_path = ltrim( (string) $relative_path, '/' );
	if ( $relative_path === '' ) {
		return FP_REVIEWS_PLUGIN_URL;
	}
	if ( fp_reviews_use_minified_assets() && preg_match( '/\.(css|js)$/', $relative_path ) ) {
		$relative_path = preg_replace( '/\.(css|js)$/', '.min.$1', $relative_path, 1 );
	}
	return FP_REVIEWS_PLUGIN_URL . $relative_path;
}

/**
 * Opening `<firmpilot>` root element for front-end output.
 *
 * @return string
 */
function fp_reviews_root_open_markup() {
	$theme = apply_filters( 'fp_reviews_root_data_theme', 'light' );
	$theme = is_string( $theme ) && $theme !== '' ? sanitize_key( $theme ) : 'light';

	$attrs = (array) apply_filters(
		'fp_reviews_root_element_attributes',
		array(
			'data-theme' => $theme,
		)
	);

	$parts = array();
	foreach ( $attrs as $name => $value ) {
		$name = is_string( $name ) ? trim( $name ) : '';
		if ( $name === '' ) {
			continue;
		}
		$parts[] = sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
	}

	return '<firmpilot' . implode( '', $parts ) . '>';
}

/**
 * Closing `</firmpilot>` tag.
 *
 * @return string
 */
function fp_reviews_root_close_markup() {
	return '</firmpilot>';
}

/**
 * Wrap complete plugin front-end HTML in a single `<firmpilot>` root.
 *
 * @param string $html Markup fragment.
 * @return string
 */
function fp_reviews_wrap_html( $html ) {
	$html = trim( (string) $html );
	if ( $html === '' ) {
		return '';
	}
	return fp_reviews_root_open_markup() . $html . fp_reviews_root_close_markup();
}

/**
 * Allowed HTML for inline SVG icons.
 *
 * @return array
 */
function fp_reviews_kses_inline_svg_allowed_html() {
	return array(
		'svg'    => array(
			'xmlns'           => true,
			'viewbox'         => true,
			'class'           => true,
			'width'           => true,
			'height'          => true,
			'fill'            => true,
			'aria-hidden'     => true,
			'focusable'       => true,
			'role'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
		),
		'path'   => array(
			'd'               => true,
			'fill'            => true,
			'class'           => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
		),
		'g'      => array(
			'class'     => true,
			'fill'      => true,
			'transform' => true,
		),
		'title'  => array(),
		'desc'   => array(),
		'circle' => array(
			'cx'    => true,
			'cy'    => true,
			'r'     => true,
			'class' => true,
			'fill'  => true,
		),
		'rect'   => array(
			'x'      => true,
			'y'      => true,
			'width'  => true,
			'height' => true,
			'rx'     => true,
			'ry'     => true,
			'class'  => true,
			'fill'   => true,
		),
		'line'   => array(
			'x1'    => true,
			'y1'    => true,
			'x2'    => true,
			'y2'    => true,
			'class' => true,
		),
	);
}

/**
 * Sanitize inline SVG markup.
 *
 * @param string $svg SVG string.
 * @return string
 */
function fp_reviews_kses_inline_svg( $svg ) {
	return wp_kses( (string) $svg, fp_reviews_kses_inline_svg_allowed_html() );
}

/**
 * Default script dependencies for block editor bundles.
 *
 * @return array
 */
function fp_reviews_block_editor_script_dependencies() {
	return array(
		'wp-blocks',
		'wp-element',
		'wp-i18n',
		'wp-block-editor',
		'wp-components',
		'wp-server-side-render',
	);
}

/**
 * Register a block editor script bundle.
 *
 * @param string $handle     Script handle.
 * @param string $src        Script URL.
 * @param string $version    Version string.
 * @param array  $extra_deps Extra script dependencies.
 */
function fp_reviews_register_block_editor_bundle( $handle, $src, $version, array $extra_deps = array() ) {
	$deps = array_merge( fp_reviews_block_editor_script_dependencies(), $extra_deps );
	wp_register_script( $handle, $src, $deps, $version, true );
}

/**
 * Register editor preview styles for ServerSideRender.
 *
 * @param string $handle           Style handle.
 * @param string $render_style_url Style URL.
 * @param string $version          Version string.
 * @param array  $deps             Style dependencies.
 * @return string
 */
function fp_reviews_register_block_editor_preview_style( $handle, $render_style_url, $version, array $deps = array() ) {
	$deps = array_values( array_filter( $deps, 'is_string' ) );
	if ( empty( $deps ) && wp_style_is( 'fp-reviews', 'registered' ) ) {
		$deps[] = 'fp-reviews';
	}
	wp_register_style( $handle, $render_style_url, $deps, $version );
	return $handle;
}

/**
 * Enqueue a block's editor script handle.
 *
 * @param string $block_name Block name.
 * @return bool
 */
function fp_reviews_enqueue_block_editor_script( $block_name ) {
	if ( ! class_exists( 'WP_Block_Type_Registry', false ) ) {
		return false;
	}
	$block = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
	if ( ! $block || empty( $block->editor_script ) ) {
		return false;
	}
	$handle = $block->editor_script;
	if ( is_array( $handle ) ) {
		$handle = $handle[0];
	}
	wp_enqueue_script( $handle );
	return true;
}

/**
 * Localize data on a block editor script.
 *
 * @param string $block_name  Block name.
 * @param string $object_name JS object name.
 * @param array  $data        Data to pass.
 * @return bool
 */
function fp_reviews_localize_block_editor_script( $block_name, $object_name, array $data ) {
	if ( ! class_exists( 'WP_Block_Type_Registry', false ) ) {
		return false;
	}
	$block = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
	if ( ! $block || empty( $block->editor_script ) ) {
		return false;
	}
	$handle = $block->editor_script;
	if ( is_array( $handle ) ) {
		$handle = $handle[0];
	}
	wp_localize_script( $handle, $object_name, $data );
	return true;
}

/**
 * Taxonomy options for block editor controls.
 *
 * @param string $taxonomy Taxonomy slug.
 * @return array
 */
function fp_reviews_get_editor_term_options( $taxonomy ) {
	$taxonomy = sanitize_key( (string) $taxonomy );
	if ( $taxonomy === '' ) {
		return array();
	}

	$cache_key = 'fp_reviews_editor_term_options_' . $taxonomy;
	$options   = wp_cache_get( $cache_key, 'fp_reviews' );
	if ( is_array( $options ) ) {
		return $options;
	}

	$options = array();
	$terms   = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$options[] = array(
				'value' => $term->slug,
				'label' => $term->name,
			);
		}
	}

	wp_cache_set( $cache_key, $options, 'fp_reviews', HOUR_IN_SECONDS );
	return $options;
}

/**
 * Register WP-CLI status command when CLI is available.
 *
 * @param string $command_slug Command slug.
 * @param string $label        Plugin label.
 * @param string $version      Plugin version.
 */
function fp_reviews_register_cli_status_command( $command_slug, $label, $version ) {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
		return;
	}
	if ( ! is_string( $command_slug ) || $command_slug === '' ) {
		return;
	}
	$command = new class( (string) $label, (string) $version ) {
		private $label;
		private $version;

		public function __construct( $label, $version ) {
			$this->label   = $label;
			$this->version = $version;
		}

		public function status() {
			\WP_CLI::line( 'Plugin: ' . $this->label );
			\WP_CLI::line( 'Version: ' . $this->version );
			\WP_CLI::success( 'Status check completed.' );
		}
	};

	if ( ! \WP_CLI::has_command( $command_slug ) ) {
		\WP_CLI::add_command( $command_slug, $command );
	}
}

/**
 * Register sortable list AJAX hooks on an admin instance.
 *
 * @param object $instance    Admin class instance.
 * @param string $save_action AJAX action suffix.
 */
function fp_reviews_register_sortable_list_ajax_hooks( $instance, $save_action ) {
	if ( ! is_object( $instance ) || ! is_string( $save_action ) || $save_action === '' ) {
		return;
	}
	add_action( 'wp_ajax_' . $save_action, array( $instance, 'ajax_save_order' ) );
}

/**
 * Register list thumbnail AJAX hooks on an admin instance.
 *
 * @param object $instance      Admin class instance.
 * @param string $get_action    Get thumbnail action.
 * @param string $remove_action Remove thumbnail action.
 */
function fp_reviews_register_list_thumb_ajax_hooks( $instance, $get_action, $remove_action ) {
	if ( ! is_object( $instance ) ) {
		return;
	}
	if ( is_string( $get_action ) && $get_action !== '' ) {
		add_action( 'wp_ajax_' . $get_action, array( $instance, 'ajax_get_thumbnail' ) );
	}
	if ( is_string( $remove_action ) && $remove_action !== '' ) {
		add_action( 'wp_ajax_' . $remove_action, array( $instance, 'ajax_remove_thumbnail' ) );
	}
}

/**
 * Default empty-state icon SVG.
 *
 * @return string
 */
function fp_reviews_empty_state_icon_html() {
	static $icon = null;
	if ( null === $icon ) {
		$icon = '<svg class="fp-empty-state__icon" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960"><path d="m603.85-160 60 60H252.31Q222-100 201-121q-21-21-21-51.31v-615.38Q180-818 201-839q21-21 51.31-21h338.46L780-633.08v460.77q0 14.62-6.77 27.46-6.77 12.85-18.31 21.92L554.61-322q-17 11.38-35.29 16.69Q501.03-300 480-300q-57.75 0-98.87-41.13Q340-382.25 340-440q0-57.75 41.04-98.87Q422.08-580 480-580q57.92 0 98.96 41.13Q620-497.75 620-440q0 21.85-5.5 40.12-5.5 18.26-16.5 34.49l122 124.01V-612L562-800H252.31q-4.62 0-8.46 3.85-3.85 3.84-3.85 8.46v615.38q0 4.62 3.85 8.46 3.84 3.85 8.46 3.85h351.54ZM536.5-383.5Q560-407 560-440t-23.5-56.5Q513-520 480-520t-56.5 23.5Q400-473 400-440t23.5 56.5Q447-360 480-360t56.5-23.5ZM480-450Zm0 0Z"/></svg>';
	}
	return $icon;
}
