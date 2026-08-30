<?php
/**
 * Uninstall routine for Simbe AI Website Care.
 *
 * @package Simbe_AI_Website_Care
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Options.
delete_option( 'simbe_care_settings' );

// Transients.
delete_transient( 'simbe_care_wporg_cache' );
delete_transient( 'simbe_care_scan_results' );

// Scheduled events.
wp_clear_scheduled_hook( 'simbe_care_scan' );
