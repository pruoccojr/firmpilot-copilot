<?php
/**
 * Reviews for FirmPilot – registered styles/scripts and admin asset loading.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FP_Reviews_Assets_Registry {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_assets' ), 0 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_global' ), 0 );
		add_action( 'admin_init', array( $this, 'load_list_thumb' ), 0 );
	}

	public function register_assets() {
		wp_register_style(
			'fp-reviews',
			fp_reviews_asset_url( 'assets/css/styles.css' ),
			array(),
			FP_REVIEWS_VERSION
		);

		wp_register_style(
			'fp-reviews-admin-base',
			fp_reviews_asset_url( 'admin/pages/settings/settings.css' ),
			array(),
			FP_REVIEWS_VERSION
		);

		wp_register_style(
			'fp-wp-admin-list-thumb',
			fp_reviews_asset_url( 'admin/assets/css/list-thumb.css' ),
			array( 'fp-reviews-admin-base' ),
			FP_REVIEWS_VERSION
		);

		wp_register_style(
			'fp-admin-global',
			fp_reviews_asset_url( 'admin/pages/settings/admin-global.css' ),
			array(
				'fp-reviews-admin-base',
				'fp-wp-admin-list-thumb',
			),
			FP_REVIEWS_VERSION
		);

		wp_register_script(
			'fp-reviews-carousel',
			fp_reviews_asset_url( 'assets/js/carousel.js' ),
			array(),
			FP_REVIEWS_VERSION,
			true
		);
		wp_script_add_data( 'fp-reviews-carousel', 'strategy', 'defer' );

		wp_register_script(
			'fp-admin-sortable',
			fp_reviews_asset_url( 'admin/pages/settings/sortable.js' ),
			array(),
			FP_REVIEWS_VERSION,
			true
		);
		wp_script_add_data( 'fp-admin-sortable', 'strategy', 'defer' );
	}

	public function enqueue_admin_global() {
		wp_enqueue_style( 'fp-admin-global' );
	}

	public function load_list_thumb() {
		require_once FP_REVIEWS_PLUGIN_DIR . 'includes/list-thumb.php';
	}
}
