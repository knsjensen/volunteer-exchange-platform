<?php
/**
 * Database installer class
 *
 * @package VEP
 * @subpackage Database
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Database;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database installer class
 * 
 * Handles database table creation and schema upgrades
 * 
 * @package VolunteerExchangePlatform\Database
 */
class Installer {
    
    /**
     * Install database tables
     *
     * Creates all necessary database tables for the plugin
     *
     * @return void
     */
    public function install() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create vep_events table
        $table_events = $wpdb->prefix . 'vep_events';
        $sql_events = "CREATE TABLE $table_events (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            is_active tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql_events);
        
        // Create vep_participant_types table
        $table_types = $wpdb->prefix . 'vep_participant_types';
        $sql_types = "CREATE TABLE $table_types (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            icon varchar(255) DEFAULT '',
            color varchar(7) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_types);
        
        // Create vep_we_offer_tags table
        $table_tags = $wpdb->prefix . 'vep_we_offer_tags';
        $sql_tags = "CREATE TABLE $table_tags (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            icon varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_tags);
        
        // Create vep_participants table
        $table_participants = $wpdb->prefix . 'vep_participants';
        $sql_participants = "CREATE TABLE $table_participants (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id bigint(20) UNSIGNED NOT NULL,
            participant_number int(11) DEFAULT NULL,
            organization_name varchar(255) NOT NULL,
            description text,
            expected_participants_count int(11) DEFAULT NULL,
            expected_participants_names text,
            participant_type_id bigint(20) UNSIGNED NOT NULL,
            contact_person_name varchar(255) NOT NULL,
            contact_email varchar(255),
            contact_phone varchar(50),
            logo_url varchar(500),
            no_logo tinyint(1) DEFAULT 0,
            link varchar(500) DEFAULT '',
            no_link tinyint(1) DEFAULT 0,
            no_expected_count tinyint(1) DEFAULT 0,
            no_expected_names tinyint(1) DEFAULT 0,
            randon_key varchar(36) DEFAULT NULL,
            is_approved tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY participant_type_id (participant_type_id),
            KEY is_approved (is_approved),
            KEY participant_number (participant_number)
        ) $charset_collate;";
        dbDelta($sql_participants);
        
        // Create vep_participant_tags table (many-to-many relationship)
        $table_participant_tags = $wpdb->prefix . 'vep_participant_tags';
        $sql_participant_tags = "CREATE TABLE $table_participant_tags (
            participant_id bigint(20) UNSIGNED NOT NULL,
            tag_id bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY (participant_id, tag_id),
            KEY participant_id (participant_id),
            KEY tag_id (tag_id)
        ) $charset_collate;";
        dbDelta($sql_participant_tags);
        
        // Create vep_agreements table
        $table_agreements = $wpdb->prefix . 'vep_agreements';
        $sql_agreements = "CREATE TABLE $table_agreements (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id bigint(20) UNSIGNED NOT NULL,
            participant1_id bigint(20) UNSIGNED NOT NULL,
            participant2_id bigint(20) UNSIGNED NOT NULL,
            initiator_id bigint(20) UNSIGNED NOT NULL,
            description text,
            status varchar(50) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY participant1_id (participant1_id),
            KEY participant2_id (participant2_id),
            KEY initiator_id (initiator_id)
        ) $charset_collate;";
        dbDelta($sql_agreements);

        // Create vep_transactional_emails table
        $table_transactional_emails = $wpdb->prefix . 'vep_transactional_emails';
        $sql_transactional_emails = "CREATE TABLE $table_transactional_emails (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 3,
            scheduled_at datetime NOT NULL,
            payload longtext NOT NULL,
            provider_message_id varchar(255) DEFAULT NULL,
            last_error text,
            lock_token varchar(64) DEFAULT NULL,
            locked_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status_scheduled (status, scheduled_at),
            KEY lock_token (lock_token)
        ) $charset_collate;";
        dbDelta($sql_transactional_emails);

        // Create vep_participant_reminders table
        $table_participant_reminders = $wpdb->prefix . 'vep_participant_reminders';
        $sql_participant_reminders = "CREATE TABLE $table_participant_reminders (
            participant_id bigint(20) UNSIGNED NOT NULL,
            reminder_type varchar(20) NOT NULL,
            sent_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (participant_id, reminder_type),
            KEY reminder_type (reminder_type),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        dbDelta($sql_participant_reminders);

        // Create vep_competitions table (global — not tied to a specific event)
        $table_competitions = $wpdb->prefix . 'vep_competitions';
        $sql_competitions = "CREATE TABLE $table_competitions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1,
            winner_input_type varchar(20) NOT NULL DEFAULT 'dropdown',
            sort_order int(11) DEFAULT 0,
            custom_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        dbDelta($sql_competitions);

        // Create vep_competition_winners table (per-event winner data)
        $table_competition_winners = $wpdb->prefix . 'vep_competition_winners';
        $sql_competition_winners = "CREATE TABLE $table_competition_winners (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id bigint(20) UNSIGNED NOT NULL,
            competition_id bigint(20) UNSIGNED NOT NULL,
            winner_id bigint(20) UNSIGNED DEFAULT NULL,
            winner_text varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY event_competition (event_id, competition_id),
            KEY event_id (event_id),
            KEY competition_id (competition_id)
        ) $charset_collate;";
        dbDelta($sql_competition_winners);

        // Update version
        update_option('vep_db_version', VEP_VERSION);
    }

    /**
     * Ensure schema upgrades are applied for existing installs
     *
     * Runs lightweight checks and ALTERs only when needed
     *
     * @return void
     */
    public function maybe_upgrade() {
        global $wpdb;

        $table_participants = $wpdb->prefix . 'vep_participants';

        // If participants table doesn't exist yet, nothing to upgrade.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct schema inspection query is required during install/upgrade.
        $table_exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_participants
        ));
        if ($table_exists !== $table_participants) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
        $has_expected_count = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
            "SHOW COLUMNS FROM $table_participants LIKE %s",
            'expected_participants_count'
        ));
        if (!$has_expected_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
            $wpdb->query("ALTER TABLE $table_participants ADD COLUMN expected_participants_count int(11) DEFAULT NULL");
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
        $has_expected_names = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
            "SHOW COLUMNS FROM $table_participants LIKE %s",
            'expected_participants_names'
        ));
        if (!$has_expected_names) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
            $wpdb->query("ALTER TABLE $table_participants ADD COLUMN expected_participants_names text");
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
        $has_link = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
            "SHOW COLUMNS FROM $table_participants LIKE %s",
            'link'
        ));
        if (!$has_link) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
            $wpdb->query("ALTER TABLE $table_participants ADD COLUMN link varchar(500) DEFAULT ''");
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
        $has_no_logo = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
            "SHOW COLUMNS FROM $table_participants LIKE %s",
            'no_logo'
        ));
        if (!$has_no_logo) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
            $wpdb->query("ALTER TABLE $table_participants ADD COLUMN no_logo tinyint(1) DEFAULT 0");
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
        $has_no_link = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
            "SHOW COLUMNS FROM $table_participants LIKE %s",
            'no_link'
        ));
        if (!$has_no_link) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
            $wpdb->query("ALTER TABLE $table_participants ADD COLUMN no_link tinyint(1) DEFAULT 0");
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
        $has_no_expected_count = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
            "SHOW COLUMNS FROM $table_participants LIKE %s",
            'no_expected_count'
        ));
        if (!$has_no_expected_count) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
            $wpdb->query("ALTER TABLE $table_participants ADD COLUMN no_expected_count tinyint(1) DEFAULT 0");
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
        $has_no_expected_names = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
            "SHOW COLUMNS FROM $table_participants LIKE %s",
            'no_expected_names'
        ));
        if (!$has_no_expected_names) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
            $wpdb->query("ALTER TABLE $table_participants ADD COLUMN no_expected_names tinyint(1) DEFAULT 0");
        }

        $has_randon_key = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
            "SHOW COLUMNS FROM $table_participants LIKE %s",
            'randon_key'
        ));
        if (!$has_randon_key) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
            $wpdb->query("ALTER TABLE $table_participants ADD COLUMN randon_key varchar(36) DEFAULT NULL");

            // Populate GUID-like keys for pre-existing participants after adding the new column.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct migration update query is required; interpolated table name is controlled from $wpdb->prefix.
            $wpdb->query("UPDATE $table_participants SET randon_key = UUID() WHERE randon_key IS NULL OR randon_key = ''");
        }

        $table_types = $wpdb->prefix . 'vep_participant_types';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct schema inspection query is required during install/upgrade.
        $types_exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_types
        ));
        if ($types_exists === $table_types) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
            $has_type_icon = $wpdb->get_var($wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
                "SHOW COLUMNS FROM $table_types LIKE %s",
                'icon'
            ));
            if (!$has_type_icon) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
                $wpdb->query("ALTER TABLE $table_types ADD COLUMN icon varchar(255) DEFAULT ''");
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
            $has_type_color = $wpdb->get_var($wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
                "SHOW COLUMNS FROM $table_types LIKE %s",
                'color'
            ));
            if (!$has_type_color) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
                $wpdb->query("ALTER TABLE $table_types ADD COLUMN color varchar(7) DEFAULT ''");
            }
        }

        $table_tags = $wpdb->prefix . 'vep_we_offer_tags';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct schema inspection query is required during install/upgrade.
        $tags_exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_tags
        ));
        if ($tags_exists === $table_tags) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
            $has_tag_icon = $wpdb->get_var($wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
                "SHOW COLUMNS FROM $table_tags LIKE %s",
                'icon'
            ));
            if (!$has_tag_icon) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
                $wpdb->query("ALTER TABLE $table_tags ADD COLUMN icon varchar(255) DEFAULT ''");
            }
        }

        $table_transactional_emails = $wpdb->prefix . 'vep_transactional_emails';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct schema inspection query is required during install/upgrade.
        $emails_exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_transactional_emails
        ));
        if ($emails_exists !== $table_transactional_emails) {
            $charset_collate = $wpdb->get_charset_collate();
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $sql_transactional_emails = "CREATE TABLE $table_transactional_emails (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                status varchar(20) NOT NULL DEFAULT 'pending',
                attempts int(11) NOT NULL DEFAULT 0,
                max_attempts int(11) NOT NULL DEFAULT 3,
                scheduled_at datetime NOT NULL,
                payload longtext NOT NULL,
                provider_message_id varchar(255) DEFAULT NULL,
                last_error text,
                lock_token varchar(64) DEFAULT NULL,
                locked_at datetime DEFAULT NULL,
                sent_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY status_scheduled (status, scheduled_at),
                KEY lock_token (lock_token)
            ) $charset_collate;";

            dbDelta($sql_transactional_emails);
        }

        $table_participant_reminders = $wpdb->prefix . 'vep_participant_reminders';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct schema inspection query is required during install/upgrade.
        $reminders_exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_participant_reminders
        ));
        if ($reminders_exists !== $table_participant_reminders) {
            $charset_collate = $wpdb->get_charset_collate();
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $sql_participant_reminders = "CREATE TABLE $table_participant_reminders (
                participant_id bigint(20) UNSIGNED NOT NULL,
                reminder_type varchar(20) NOT NULL,
                sent_at datetime NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (participant_id, reminder_type),
                KEY reminder_type (reminder_type),
                KEY sent_at (sent_at)
            ) $charset_collate;";

            dbDelta($sql_participant_reminders);
        }

        $table_competitions = $wpdb->prefix . 'vep_competitions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct schema inspection query is required during install/upgrade.
        $competitions_exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_competitions
        ));
        if ($competitions_exists !== $table_competitions) {
            $charset_collate = $wpdb->get_charset_collate();
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            // Fresh install: use new schema without event_id / winner columns.
            $sql_competitions = "CREATE TABLE $table_competitions (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                type varchar(50) NOT NULL,
                title varchar(255) NOT NULL,
                description text,
                is_active tinyint(1) DEFAULT 1,
                winner_input_type varchar(20) NOT NULL DEFAULT 'dropdown',
                sort_order int(11) DEFAULT 0,
                custom_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY type (type),
                KEY is_active (is_active),
                KEY sort_order (sort_order)
            ) $charset_collate;";

            dbDelta($sql_competitions);
        } else {
            // Existing install: add description / winner_input_type columns if missing
            // (legacy upgrades from very early versions).

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
            $has_competition_description = $wpdb->get_var($wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
                "SHOW COLUMNS FROM $table_competitions LIKE %s",
                'description'
            ));

            if ( ! $has_competition_description ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
                $wpdb->query("ALTER TABLE $table_competitions ADD COLUMN description text AFTER title");
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection query is required; interpolated table name is controlled from $wpdb->prefix.
            $has_winner_input_type = $wpdb->get_var($wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
                "SHOW COLUMNS FROM $table_competitions LIKE %s",
                'winner_input_type'
            ));
            if ( ! $has_winner_input_type ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema migration query is required; interpolated table name is controlled from $wpdb->prefix.
                $wpdb->query("ALTER TABLE $table_competitions ADD COLUMN winner_input_type varchar(20) NOT NULL DEFAULT 'dropdown'");
            }
        }

        // -----------------------------------------------------------------------
        // Migration: split per-event winner data into vep_competition_winners
        // -----------------------------------------------------------------------

        $table_competition_winners = $wpdb->prefix . 'vep_competition_winners';

        // Step 1: Create vep_competition_winners table if it does not exist yet.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct schema inspection query is required during install/upgrade.
        $winners_table_exists = $wpdb->get_var($wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $table_competition_winners
        ));
        if ($winners_table_exists !== $table_competition_winners) {
            $charset_collate = $wpdb->get_charset_collate();
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $sql_competition_winners = "CREATE TABLE $table_competition_winners (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                event_id bigint(20) UNSIGNED NOT NULL,
                competition_id bigint(20) UNSIGNED NOT NULL,
                winner_id bigint(20) UNSIGNED DEFAULT NULL,
                winner_text varchar(255) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY event_competition (event_id, competition_id),
                KEY event_id (event_id),
                KEY competition_id (competition_id)
            ) $charset_collate;";

            dbDelta($sql_competition_winners);
        }

        // Step 2: If vep_competitions still has event_id column, migrate winner
        // data into vep_competition_winners and deduplicate competition rows.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection required; table name is from $wpdb->prefix.
        $has_event_id_col = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name controlled.
            "SHOW COLUMNS FROM $table_competitions LIKE %s",
            'event_id'
        ));

        if ($has_event_id_col) {

            // 2a. Determine canonical competition ID for every (type, title) pair.
            //     Canonical = the row with the lowest id.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct migration query; table name is from $wpdb->prefix.
            $canonical_rows = $wpdb->get_results(
                "SELECT MIN(id) AS canonical_id, type, title FROM $table_competitions GROUP BY type, title"
            );

            $canonical_map = array(); // "type||title" => canonical_id
            $canonical_ids = array();
            foreach ( $canonical_rows as $row ) {
                $key                  = $row->type . '||' . $row->title;
                $canonical_map[ $key ] = (int) $row->canonical_id;
                $canonical_ids[]      = (int) $row->canonical_id;
            }

            // 2b. Migrate winner data: for each competition that has event_id > 0
            //     and a winner_id or winner_text, insert a row into the winners table
            //     (only if one does not already exist for that event + canonical comp).
            if ( $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "SHOW COLUMNS FROM $table_competitions LIKE %s", 'winner_id' // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            )) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct migration query; table name is from $wpdb->prefix.
                $comps_with_winners = $wpdb->get_results(
                    "SELECT id, event_id, type, title, winner_id, winner_text
                     FROM $table_competitions
                     WHERE event_id > 0
                       AND ( winner_id IS NOT NULL
                             OR ( winner_text IS NOT NULL AND winner_text != '' ) )"
                );

                foreach ( $comps_with_winners as $comp ) {
                    $key          = $comp->type . '||' . $comp->title;
                    $canonical_id = isset( $canonical_map[ $key ] ) ? $canonical_map[ $key ] : (int) $comp->id;
                    $event_id_val = (int) $comp->event_id;

                    if ( $event_id_val <= 0 || $canonical_id <= 0 ) {
                        continue;
                    }

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct migration query; table name is from $wpdb->prefix.
                    $winner_exists = $wpdb->get_var($wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name controlled.
                        "SELECT id FROM $table_competition_winners WHERE event_id = %d AND competition_id = %d LIMIT 1",
                        $event_id_val,
                        $canonical_id
                    ));

                    if ( ! $winner_exists ) {
                        $winner_id_val   = $comp->winner_id ? (int) $comp->winner_id : null;
                        $winner_text_val = ( $comp->winner_text !== null && $comp->winner_text !== '' )
                            ? sanitize_text_field( $comp->winner_text )
                            : null;

                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert for migration.
                        $wpdb->insert(
                            $table_competition_winners,
                            array(
                                'event_id'       => $event_id_val,
                                'competition_id' => $canonical_id,
                                'winner_id'      => $winner_id_val,
                                'winner_text'    => $winner_text_val,
                            ),
                            array( '%d', '%d', '%d', '%s' )
                        );
                    }
                }
            }

            // 2c. Delete non-canonical competition rows (duplicates across events).
            if ( ! empty( $canonical_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $canonical_ids ), '%d' ) );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct migration delete; table name from $wpdb->prefix.
                $wpdb->query(
                    $wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and placeholders are controlled.
                        "DELETE FROM $table_competitions WHERE id NOT IN ($placeholders)",
                        ...$canonical_ids
                    )
                );
            }

            // 2d. Drop obsolete columns: event_id, winner_id, winner_text.
            //     winner_input_type stays on vep_competitions (it is a property of
            //     the competition definition, not of a per-event winner).
            foreach ( array( 'event_id', 'winner_id', 'winner_text' ) as $drop_col ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema inspection; table name from $wpdb->prefix.
                $col_exists = $wpdb->get_var($wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name controlled.
                    "SHOW COLUMNS FROM $table_competitions LIKE %s",
                    $drop_col
                ));
                if ( $col_exists ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct schema change for migration; table name and column are controlled.
                    $wpdb->query( "ALTER TABLE $table_competitions DROP COLUMN $drop_col" );
                }
            }
        }
    }

    /**
     * Seed default participant types and we offer tags
     *
     * Seeds only when tables are empty.
     *
     * @return void
     */
    public function seed_default_data() {
        global $wpdb;

        $types_table = $wpdb->prefix . 'vep_participant_types';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct count query is required during seed initialization; interpolated table name is controlled from $wpdb->prefix.
        $types_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$types_table}");

        if ($types_count === 0) {
            $types = array(
                array(
                    'name'        => 'Forening/frivilligt fællesskab (det sociale område)',
                    'icon'        => 's:fa-users',
                    'description' => 'Netværk og aktiviteter, der styrker socialt fællesskab og hjælper borgere.',
                ),
                array(
                    'name'        => 'Forening/frivilligt fællesskab (Fritid/idræt)',
                    'icon'        => 's:fa-volleyball-ball',
                    'description' => 'Organiserede fritids- og sportsaktiviteter for alle aldre.',
                ),
                array(
                    'name'        => 'Forening/frivilligt fællesskab (Kultur)',
                    'icon'        => 's:fa-theater-masks',
                    'description' => 'Fællesskaber omkring kunst, musik, teater og kulturprojekter.',
                ),
                array(
                    'name'        => 'Forening/frivilligt fællesskab (Klima/miljø)',
                    'icon'        => 's:fa-leaf',
                    'description' => 'Initiativer, der fremmer bæredygtighed og miljøbevidsthed.',
                ),
                array(
                    'name'        => 'Virksomhed',
                    'icon'        => 's:fa-building',
                    'description' => 'Private eller offentlige virksomheder med fokus på udvikling og samarbejde.',
                ),
                array(
                    'name'        => 'Odense Kommune',
                    'icon'        => 's:fa-city',
                    'description' => 'Kommunale aktører, der understøtter borgere og lokale fællesskaber.',
                ),
                array(
                    'name'        => 'Region Syddanmark',
                    'icon'        => 's:fa-landmark',
                    'description' => 'Regionalt niveau med fokus på sundhed, udvikling og offentlige initiativer.',
                ),
            );

            foreach ($types as $type) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct insert is required during seed initialization.
                $wpdb->insert(
                    $types_table,
                    array(
                        'name'        => $type['name'],
                        'icon'        => $type['icon'],
                        'description' => $type['description'],
                    ),
                    array('%s', '%s', '%s')
                );
            }
        }

        $tags_table = $wpdb->prefix . 'vep_we_offer_tags';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct count query is required during seed initialization; interpolated table name is controlled from $wpdb->prefix.
        $tags_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tags_table}");

        if ($tags_count === 0) {
            $tags = array(
                array(
                    'name'        => 'Arrangementer',
                    'icon'        => 's:fa-calendar-day',
                    'description' => 'Planlagte events, møder og aktiviteter, der samler deltagere om et fælles formål.',
                ),
                array(
                    'name'        => 'Faciliteter',
                    'icon'        => 'r:fa-building',
                    'description' => 'Adgang til udstyr, bygninger og praktiske rammer, der understøtter aktiviteter og arbejde.',
                ),
                array(
                    'name'        => 'Lokaler',
                    'icon'        => 's:fa-door-open',
                    'description' => 'Rum og mødefaciliteter, der kan bookes eller anvendes til forskellige formål.',
                ),
                array(
                    'name'        => 'Oplæg',
                    'icon'        => 's:fa-microphone',
                    'description' => 'Faglige præsentationer, foredrag og inspirationsindlæg.',
                ),
                array(
                    'name'        => 'Rådgivning / sparring',
                    'icon'        => 'r:fa-comments',
                    'description' => 'Professionel rådgivning og kvalificeret sparring om idéer og udfordringer.',
                ),
                array(
                    'name'        => 'Sparring på fundraising',
                    'icon'        => 's:fa-handshake',
                    'description' => 'Støtte og vejledning i fundraising, ansøgninger og finansieringsstrategier.',
                ),
                array(
                    'name'        => 'Synlighed / kommunikation',
                    'icon'        => 's:fa-bullhorn',
                    'description' => 'Hjælp til formidling, markedsføring og strategisk kommunikation.',
                ),
                array(
                    'name'        => 'Viden',
                    'icon'        => 's:fa-brain',
                    'description' => 'Deling af indsigt, erfaringer og faglig viden.',
                ),
                array(
                    'name'        => 'Workshops',
                    'icon'        => 's:fa-screwdriver',
                    'description' => 'Praktiske og involverende forløb med fokus på læring og udvikling.',
                ),
            );

            foreach ($tags as $tag) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct insert is required during seed initialization.
                $wpdb->insert(
                    $tags_table,
                    array(
                        'name'        => $tag['name'],
                        'icon'        => $tag['icon'],
                        'description' => $tag['description'],
                    ),
                    array('%s', '%s', '%s')
                );
            }
        }
    }
}
