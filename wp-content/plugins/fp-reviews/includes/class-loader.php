<?php
/**
 * Reviews for FirmPilot – bootstrap plugin features.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FP_Reviews_Loader {

	public static function load() {
		$dir = FP_REVIEWS_PLUGIN_DIR;

		require_once $dir . 'includes/class-post-type.php';
		new FP_Reviews_Post_Type();

		require_once $dir . 'includes/class-db.php';
		require_once $dir . 'includes/class-render.php';
		require_once $dir . 'includes/class-schema.php';
		require_once $dir . 'includes/template-tags.php';
		require_once $dir . 'includes/controls/class-control-schema.php';

		require_once $dir . 'includes/class-shortcodes.php';
		new FP_Reviews_Shortcodes();

		require_once $dir . 'includes/class-assets.php';
		new FP_Reviews_Assets();

		require_once $dir . 'gutenberg/class-gutenberg.php';
		new FP_Reviews_Gutenberg();

		if ( is_admin() ) {
			require_once $dir . 'includes/class-admin.php';
			new FP_Reviews_Admin();
		}
	}
}
