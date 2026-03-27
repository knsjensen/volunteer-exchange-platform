<?php
/**
 * Uninstall handler for Volunteer Exchange Platform plugin
 *
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin settings, options, and database tables.
 *
 * @package Volunteer_Exchange_Platform
 */

// If uninstall.php is not called by WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete all plugin options from WordPress
 */
function volunteer_exchange_platform_delete_options() {
    $options = array(
        'vep_db_version',
    );

    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Clear all transients
    delete_transient('vep_admin_notices');
}

/**
 * Drop all plugin database tables
 */
function volunteer_exchange_platform_drop_tables() {
    global $wpdb;

    $tables = array(
        $wpdb->prefix . 'vep_events',
        $wpdb->prefix . 'vep_participant_types',
        $wpdb->prefix . 'vep_we_offer_tags',
        $wpdb->prefix . 'vep_we_seek_tags',
        $wpdb->prefix . 'vep_participants',
        $wpdb->prefix . 'vep_participant_we_offer',
        $wpdb->prefix . 'vep_participant_we_seek',
        $wpdb->prefix . 'vep_agreements',
        $wpdb->prefix . 'vep_agreement_tags',
    );

    // Drop each table
    foreach ($tables as $table) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DROP TABLE is required during uninstall; table names are controlled.
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

/**
 * Execute uninstall actions
 */
if (is_multisite()) {
    // For multisite installations, delete options for all sites
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct multisite blog lookup is required during uninstall.
    $volunteer_exchange_platform_blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

    foreach ($volunteer_exchange_platform_blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        volunteer_exchange_platform_delete_options();
        volunteer_exchange_platform_drop_tables();
        restore_current_blog();
    }
} else {
    // For single site installations
    volunteer_exchange_platform_delete_options();
    volunteer_exchange_platform_drop_tables();
}
