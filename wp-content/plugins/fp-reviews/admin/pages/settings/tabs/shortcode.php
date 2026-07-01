<?php
/**
 * FirmPilot Reviews – Shortcode Builder tab.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$definitions = apply_filters( 'fp_reviews_shortcode_builder_definitions', array() );

if ( empty( $definitions ) || ! is_array( $definitions ) ) {
	echo '<p class="description">';
	esc_html_e( 'No shortcode builder is registered yet.', 'firmpilot-reviews' );
	echo '</p>';
	return;
}

$sub = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : '';
if ( $sub === '' || ! isset( $definitions[ $sub ] ) ) {
	$sub = array_key_first( $definitions );
}

echo '<p class="description">';
esc_html_e( 'Build a shortcode with the same options as the block, then paste it into the editor or a classic widget.', 'firmpilot-reviews' );
echo '</p>';

if ( isset( $definitions[ $sub ]['render_callback'] ) && is_callable( $definitions[ $sub ]['render_callback'] ) ) {
	call_user_func( $definitions[ $sub ]['render_callback'] );
}
