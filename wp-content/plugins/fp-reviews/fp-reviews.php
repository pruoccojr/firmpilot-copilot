<?php

/**
 * Plugin Name: FirmPilot Reviews
 * Description: Display customer reviews in grid or carousel layouts. Add social proof to build trust and drive conversions. Optimized for performance and SEO.
 * Plugin URI: https://firmpilot.com/
 * Author: Paul Ruocco
 * Author URI: https://firmpilot.com/
 * Version: 1.0.0
 * License: GPL v2 or later
 * Text Domain: fp-reviews
 * Domain Path: /languages
 *
 * @package FP_Reviews
 */

if (! defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/config.php';
FP_Reviews_Config::define_runtime_constants(__FILE__);

register_activation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);

add_action(
	'plugins_loaded',
	function () {
		require_once FP_REVIEWS_PLUGIN_DIR . 'includes/class-plugin.php';
		FP_Reviews_Plugin::init();
	},
	20
);
