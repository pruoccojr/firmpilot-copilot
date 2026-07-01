<?php
/**
 * Reviews block (`firmpilot-reviews/reviews`) – registration and server render wiring.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register editor script bundle and block type for the reviews block.
function fp_reviews_block_reviews_register( $plugin_dir, $plugin_url, $version, $editor_script_handle, $editor_preview_style_handle, $render_callback, array $editor_extra_deps = array() ) {
	fp_reviews_register_block_editor_bundle(
		$editor_script_handle,
		fp_reviews_asset_url( 'gutenberg/blocks/reviews/edit.js' ),
		$version,
		$editor_extra_deps
	);
	register_block_type(
		$plugin_dir . 'gutenberg/blocks/reviews',
		array(
			'render_callback' => $render_callback,
			'editor_script'   => $editor_script_handle,
			'editor_style'    => $editor_preview_style_handle,
			'style'           => 'fp-reviews',
		)
	);
}
