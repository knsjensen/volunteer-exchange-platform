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
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete all plugin options from WordPress
 */
function volunteer_exchange_platform_delete_options() {
    $options = array(
        'vep_db_version',
        'vep_db_upgrading',
        'vep_display_title',
        'vep_display_mode',
        'vep_display_background_type',
        'vep_display_background_solid_color',
        'vep_display_background_gradient_color_1',
        'vep_display_background_gradient_color_2',
        'vep_display_background_gradient_color_3',
        'vep_display_background_gradient_stop_1',
        'vep_display_background_gradient_stop_2',
        'vep_display_background_gradient_stop_3',
        'vep_display_background_gradient_angle',
        'vep_display_text_color',
        'vep_display_auto_switch_stats',
        'vep_display_closing_text',
        'vep_display_time_up_text',
        'vep_display_hide_buttons',
        'vep_display_show_competitions',
        'vep_email_settings',
        'vep_rewrite_version',
        // Legacy key retained for safe uninstall cleanup.
        'vep_display_countdown_datetime',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }
    
    // Clear all transients
    delete_transient( 'vep_admin_notices' );

    // Remove per-event / per-user transients and scheduled events.
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct cleanup of plugin transients during uninstall.
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_vep\_%' OR option_name LIKE '\_transient\_timeout\_vep\_%'"
    );
    // Per-event display presentation state and screen presence options.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct cleanup of plugin options during uninstall.
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'vep\_display\_pstate\_%' OR option_name LIKE 'vep\_display\_presence\_%'"
    );
    foreach ( array( 'vep_process_transactional_email_queue', 'vep_send_participant_update_reminders', 'vep_email_cleanup_run' ) as $hook ) {
        wp_clear_scheduled_hook( $hook );
    }
}

/**
 * Drop all plugin database tables
 */
function volunteer_exchange_platform_drop_tables() {
    global $wpdb;

    $tables = array(
        $wpdb->prefix . 'vep_competition_winners',
        $wpdb->prefix . 'vep_competitions',
        $wpdb->prefix . 'vep_participant_reminders',
        $wpdb->prefix . 'vep_transactional_emails',
        $wpdb->prefix . 'vep_participant_tags',
        $wpdb->prefix . 'vep_agreements',
        $wpdb->prefix . 'vep_participants',
        $wpdb->prefix . 'vep_we_offer_tags',
        $wpdb->prefix . 'vep_participant_types',
        $wpdb->prefix . 'vep_events',
    );

    // Drop each table
    foreach ( $tables as $table ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DROP TABLE is required during uninstall; table names are controlled.
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }
}

/**
 * Execute uninstall actions
 */
if ( is_multisite() ) {
    // For multisite installations, delete options for all sites
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct multisite blog lookup is required during uninstall.
    $volunteer_exchange_platform_blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

    foreach ( $volunteer_exchange_platform_blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        volunteer_exchange_platform_delete_options();
        volunteer_exchange_platform_drop_tables();
        restore_current_blog();
    }
} else {
    // For single site installations
    volunteer_exchange_platform_delete_options();
    volunteer_exchange_platform_drop_tables();
}
