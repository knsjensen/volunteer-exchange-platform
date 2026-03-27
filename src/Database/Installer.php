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
