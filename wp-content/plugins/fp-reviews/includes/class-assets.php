<?php
/**
 * Reviews for FirmPilot – front-end scripts and styles (gated; not global).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FP_Reviews_Assets {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ), 20 );
	}

	/**
	 * @param WP_Post $post Post being viewed.
	 */
	private function singular_needs_reviews_assets( WP_Post $post ) {
		if ( has_block( 'firmpilot-reviews/reviews', $post ) ) {
			return true;
		}
		if ( function_exists( 'has_shortcode' ) && has_shortcode( $post->post_content, 'firmpilot_reviews' ) ) {
			return true;
		}
		return (bool) apply_filters( 'FP_Reviews_singular_needs_assets', false, $post );
	}

	public function enqueue_frontend() {
		if ( ! is_singular() ) {
			return;
		}
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! $this->singular_needs_reviews_assets( $post ) ) {
			return;
		}

		wp_enqueue_style( 'fp-reviews' );

		if ( wp_script_is( 'fp-reviews-carousel', 'registered' ) ) {
			wp_enqueue_script( 'fp-reviews-carousel' );
		}
	}
}
