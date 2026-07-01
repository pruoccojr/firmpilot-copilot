<?php
/**
 * Reviews for FirmPilot – main plugin class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FP_Reviews_Plugin {

	public static function init() {
		load_plugin_textdomain(
			FP_Reviews_Config::TEXT_DOMAIN,
			false,
			dirname( plugin_basename( FP_REVIEWS_PLUGIN_FILE ) ) . '/languages'
		);

		require_once FP_REVIEWS_PLUGIN_DIR . 'includes/functions.php';
		require_once FP_REVIEWS_PLUGIN_DIR . 'includes/inline-svg.php';
		require_once FP_REVIEWS_PLUGIN_DIR . 'admin/class-assets.php';
		require_once FP_REVIEWS_PLUGIN_DIR . 'admin/class-settings.php';

		FP_Reviews_Assets_Registry::instance();
		FP_Reviews_Settings::instance();

		fp_reviews_register_cli_status_command( 'firmpilot-reviews', 'Reviews for FirmPilot', FP_REVIEWS_VERSION );

		require_once FP_REVIEWS_PLUGIN_DIR . 'includes/class-loader.php';
		FP_Reviews_Loader::load();

		add_action( 'wp_head', array( 'FP_Reviews_Schema', 'maybe_print_single_review_head' ), 20 );
	}
}
