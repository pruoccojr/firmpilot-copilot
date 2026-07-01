<?php
/**
 * Reviews for FirmPilot – theme-friendly render helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render reviews markup for themes and custom code.
 *
 * @param array $atts Renderer options.
 * @return string
 */
function fp_reviews_render( array $atts ) {
	return FP_Reviews_Render::render_reviews( $atts );
}
