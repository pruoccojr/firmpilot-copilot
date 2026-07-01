<?php
/**
 * Reviews for FirmPilot – configuration and defaults.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FP_Reviews_Config {

	const VERSION     = '1.0.0';
	const TEXT_DOMAIN = 'firmpilot-reviews';

	// Define plugin runtime constants from the root config file.
	public static function define_runtime_constants( $plugin_file ) {
		if ( ! defined( 'FP_REVIEWS_PLUGIN_FILE' ) ) {
			define( 'FP_REVIEWS_PLUGIN_FILE', $plugin_file );
		}
		if ( ! defined( 'FP_REVIEWS_PLUGIN_DIR' ) ) {
			define( 'FP_REVIEWS_PLUGIN_DIR', plugin_dir_path( $plugin_file ) );
		}
		if ( ! defined( 'FP_REVIEWS_PLUGIN_URL' ) ) {
			define( 'FP_REVIEWS_PLUGIN_URL', plugin_dir_url( $plugin_file ) );
		}
		if ( ! defined( 'FP_REVIEWS_VERSION' ) ) {
			define( 'FP_REVIEWS_VERSION', self::VERSION );
		}
	}
}
