<?php
/**
 * Dependency checks for plugin bootstrap.
 *
 * @package VEP
 * @subpackage Plugin
 */

namespace VolunteerExchangePlatform\Plugin;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Dependencies {
    /**
     * Check if plugin dependencies are met.
     *
     * @return bool True if dependencies are met, false otherwise.
     */
    public static function check() {
        $errors = array();

        if ( version_compare( PHP_VERSION, VEP_MIN_PHP_VERSION, '<' ) ) {
            $errors[] = sprintf(
                /* translators: 1: required PHP version, 2: current PHP version */
                __( 'Volunteer Exchange Platform requires PHP version %1$s or higher. You are running version %2$s.', 'volunteer-exchange-platform' ),
                VEP_MIN_PHP_VERSION,
                PHP_VERSION
            );
        }

        global $wp_version;
        if ( version_compare( $wp_version, VEP_MIN_WP_VERSION, '<' ) ) {
            $errors[] = sprintf(
                /* translators: 1: required WordPress version, 2: current WordPress version */
                __( 'Volunteer Exchange Platform requires WordPress version %1$s or higher. You are running version %2$s.', 'volunteer-exchange-platform' ),
                VEP_MIN_WP_VERSION,
                $wp_version
            );
        }

        $required_extensions = array( 'mysqli', 'json' );
        foreach ( $required_extensions as $extension ) {
            if ( ! extension_loaded( $extension ) ) {
                $errors[] = sprintf(
                    /* translators: %s: PHP extension name */
                    __( 'Volunteer Exchange Platform requires the PHP %s extension to be installed.', 'volunteer-exchange-platform' ),
                    $extension
                );
            }
        }

        if ( ! empty( $errors ) ) {
            if ( is_admin() ) {
                add_action(
                    'admin_notices',
                    function() use ( $errors ) {
                        echo '<div class="error"><p><strong>' . esc_html__( 'Volunteer Exchange Platform Activation Error:', 'volunteer-exchange-platform' ) . '</strong></p><ul>';
                        foreach ( $errors as $error ) {
                            echo '<li>' . esc_html( $error ) . '</li>';
                        }
                        echo '</ul></div>';
                    }
                );
            }
            return false;
        }

        return true;
    }
}
