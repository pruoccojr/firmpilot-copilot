<?php
/**
 * Reviews for FirmPilot – uninstall. Runs when the plugin is deleted (not deactivated).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_fp_reviews_%'" );
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_fp_reviews_%'" );
