<?php
/**
 * Plugin activator
 *
 * @package VEP
 * @subpackage Plugin
 */

namespace VolunteerExchangePlatform\Plugin;

use VolunteerExchangePlatform\Database\Installer;
use VolunteerExchangePlatform\Plugin\Dependencies;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {
    /**
     * Run plugin activation tasks.
     *
     * @param bool $network_wide Whether plugin is activated network-wide.
     * @return void
     */
    public static function activate( $network_wide = false ) {
        if ( ! Dependencies::check() ) {
            wp_die(
                '<h1>' . esc_html__( 'Plugin Activation Failed', 'volunteer-exchange-platform' ) . '</h1>' .
                '<p>' . esc_html__( 'Volunteer Exchange Platform could not be activated because the system requirements are not met.', 'volunteer-exchange-platform' ) . '</p>' .
                '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Return to Plugins', 'volunteer-exchange-platform' ) . '</a></p>',
                esc_html__( 'Plugin Activation Error', 'volunteer-exchange-platform' ),
                array( 'back_link' => true )
            );
        }

        try {
            if ( is_multisite() && $network_wide ) {
                self::activate_network_wide();
                return;
            }

            self::activate_for_current_site();
        } catch ( \Throwable $e ) {
            wp_die(
                '<h1>' . esc_html__( 'Plugin Activation Failed', 'volunteer-exchange-platform' ) . '</h1>' .
                '<p>' . esc_html__( 'An unexpected error occurred during activation.', 'volunteer-exchange-platform' ) . '</p>' .
                '<p><code>' . esc_html( $e->getMessage() ) . '</code></p>' .
                '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Return to Plugins', 'volunteer-exchange-platform' ) . '</a></p>',
                esc_html__( 'Plugin Activation Error', 'volunteer-exchange-platform' ),
                array( 'back_link' => true )
            );
        }
    }

    /**
     * Activate plugin for current site.
     *
     * @return void
     */
    private static function activate_for_current_site() {
        $installer = new Installer();
        $installer->install();
        $installer->seed_default_data();

        update_option( 'vep_db_version', defined( 'VEP_DB_VERSION' ) ? VEP_DB_VERSION : '1.0.0' );
        flush_rewrite_rules();
    }

    /**
     * Activate plugin for all sites in network.
     *
     * @return void
     */
    private static function activate_network_wide() {
        $site_ids = get_sites(
            array(
                'fields' => 'ids',
                'number' => 0,
            )
        );

        foreach ( $site_ids as $site_id ) {
            switch_to_blog( (int) $site_id );
            self::activate_for_current_site();
            restore_current_blog();
        }
    }
}
