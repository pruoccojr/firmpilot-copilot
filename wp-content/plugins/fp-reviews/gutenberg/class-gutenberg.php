<?php
/**
 * Reviews for FirmPilot – Gutenberg block registration and editor assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once FP_REVIEWS_PLUGIN_DIR . 'gutenberg/blocks/reviews/register.php';

class FP_Reviews_Gutenberg {

	const BLOCK_NAME = 'firmpilot-reviews/reviews';

	const EDITOR_SCRIPT_HANDLE = 'fp-reviews-block-editor';

	const EDITOR_PREVIEW_STYLE_HANDLE = 'fp-reviews-block-editor-preview';

	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_document_sidebar' ), 20 );
	}

	public function enqueue_editor_assets() {
		fp_reviews_enqueue_block_editor_script( self::BLOCK_NAME );
		wp_enqueue_style(
			'fp-reviews-block-editor-chrome',
			fp_reviews_asset_url( 'gutenberg/blocks/reviews/editor.css' ),
			array(),
			FP_REVIEWS_VERSION
		);
		$category_options = fp_reviews_get_editor_term_options( FP_Reviews_Post_Type::TAXONOMY );
		fp_reviews_localize_block_editor_script( self::BLOCK_NAME, 'fpReviewsBlock', array( 'categories' => $category_options ) );
	}

	public function enqueue_document_sidebar() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== FP_Reviews_Post_Type::POST_TYPE ) {
			return;
		}
		wp_register_script(
			'fp-reviews-document-sidebar',
			fp_reviews_asset_url( 'gutenberg/post-editor/document-sidebar/index.js' ),
			array( 'wp-element', 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-components', 'wp-data', 'wp-i18n', 'wp-block-editor' ),
			FP_REVIEWS_VERSION,
			true
		);
		wp_script_add_data( 'fp-reviews-document-sidebar', 'strategy', 'defer' );
		wp_enqueue_script( 'fp-reviews-document-sidebar' );
		$style_deps = array();
		if ( wp_style_is( 'fp-editor-document-sidebar', 'registered' ) ) {
			$style_deps[] = 'fp-editor-document-sidebar';
			wp_enqueue_style( 'fp-editor-document-sidebar' );
		}
		wp_enqueue_style(
			'fp-reviews-document-sidebar',
			fp_reviews_asset_url( 'gutenberg/post-editor/document-sidebar/style.css' ),
			$style_deps,
			FP_REVIEWS_VERSION
		);
	}

	public function register_block() {
		$extra_deps = array();
		if ( wp_script_is( 'fp-reviews-carousel', 'registered' ) ) {
			$extra_deps[] = 'fp-reviews-carousel';
		}
		fp_reviews_register_block_editor_preview_style(
			self::EDITOR_PREVIEW_STYLE_HANDLE,
			FP_Reviews_Render::get_style_url(),
			FP_REVIEWS_VERSION,
			array( 'fp-reviews' )
		);
		fp_reviews_block_reviews_register(
			FP_REVIEWS_PLUGIN_DIR,
			FP_REVIEWS_PLUGIN_URL,
			FP_REVIEWS_VERSION,
			self::EDITOR_SCRIPT_HANDLE,
			self::EDITOR_PREVIEW_STYLE_HANDLE,
			array( $this, 'render_reviews' ),
			$extra_deps
		);
	}

	public function render_reviews( $attributes, $content = '', $block = null ) {
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}
		$atts  = FP_Reviews_Control_Schema::map_block_attributes_to_renderer_atts( $attributes );
		$inner = FP_Reviews_Render::render_reviews( $atts );
		if ( $block instanceof WP_Block ) {
			return sprintf(
				'<div %s>%s</div>',
				get_block_wrapper_attributes( array(), $block ),
				$inner
			);
		}

		return $inner;
	}
}
