<?php
/**
 * Reviews for FirmPilot – settings page (shortcode builder).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FP_Reviews_Settings {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_options_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );
	}

	public function register_options_page() {
		add_submenu_page(
			'edit.php?post_type=' . FP_Reviews_Post_Type::POST_TYPE,
			__( 'Shortcode Builder', 'firmpilot-reviews' ),
			__( 'Shortcode Builder', 'firmpilot-reviews' ),
			'edit_posts',
			'firmpilot-reviews-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function enqueue_settings_assets( $hook_suffix ) {
		if ( 'fp_review_page_firmpilot-reviews-settings' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'fp-reviews-admin-base' );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Reviews Shortcode Builder', 'firmpilot-reviews' ) . '</h1>';
		require FP_REVIEWS_PLUGIN_DIR . 'admin/pages/settings/tabs/shortcode.php';
		echo '</div>';
	}
}
