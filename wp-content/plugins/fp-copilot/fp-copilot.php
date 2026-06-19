<?php
/**
 * Plugin Name:       FirmPilot Copilot
 * Plugin URI:        https://github.com/firmpilot/fp-copilot
 * Description:       Copilot tooling and integrations for FirmPilot WordPress sites.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            FirmPilot
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fp-copilot
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

define( 'FP_COPILOT_VERSION', '1.0.0' );
define( 'FP_COPILOT_PLUGIN_FILE', __FILE__ );
define( 'FP_COPILOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FP_COPILOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FP_COPILOT_PLUGIN_DIR . 'includes/interface-fp-copilot-module.php';
require_once FP_COPILOT_PLUGIN_DIR . 'includes/abstract-fp-copilot-module.php';
require_once FP_COPILOT_PLUGIN_DIR . 'includes/class-fp-copilot-module-loader.php';
require_once FP_COPILOT_PLUGIN_DIR . 'includes/class-fp-copilot-api-key.php';
require_once FP_COPILOT_PLUGIN_DIR . 'includes/class-fp-copilot-api-auth.php';
require_once FP_COPILOT_PLUGIN_DIR . 'includes/class-fp-copilot-health-data.php';
require_once FP_COPILOT_PLUGIN_DIR . 'includes/class-fp-copilot-form-submissions.php';
require_once FP_COPILOT_PLUGIN_DIR . 'includes/class-fp-copilot-remote-connection.php';
require_once FP_COPILOT_PLUGIN_DIR . 'includes/class-fp-copilot.php';
require_once FP_COPILOT_PLUGIN_DIR . 'includes/hooks.php';

FP_Copilot_Form_Submissions::register();

register_deactivation_hook( FP_COPILOT_PLUGIN_FILE, array( 'FP_Copilot_Remote_Connection', 'handle_plugin_deactivation' ) );

/**
 * Returns the main plugin instance.
 *
 * @return FP_Copilot
 */
function fp_copilot(): FP_Copilot {
	return FP_Copilot::instance();
}

fp_copilot();
