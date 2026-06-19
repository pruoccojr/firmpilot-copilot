<?php
/**
 * Health module bootstrap.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-module.php';

return new FP_Copilot_Module_Health( __DIR__, plugin_dir_url( __FILE__ ) );
