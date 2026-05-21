<?php
/**
 * Central definition of agreement statuses.
 *
 * Use this class everywhere a status value or label is needed so that
 * the full list is defined in exactly one place.
 *
 * @package VEP
 * @subpackage Services
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgreementStatus {

    const AGREED     = 'agreed';
    const STARTED    = 'started';
    const COMPLETED  = 'completed';
    const CANCELLED  = 'cancelled';
    const NEEDS_HELP = 'needs_help';

    /**
     * All valid status keys.
     *
     * @return string[]
     */
    public static function all() {
        return array(
            self::AGREED,
            self::STARTED,
            self::COMPLETED,
            self::CANCELLED,
            self::NEEDS_HELP,
        );
    }

    /**
     * Status key → translated label map (without "All").
     *
     * @return array<string, string>
     */
    public static function labels() {
        return array(
            self::AGREED     => __( 'Agreed', 'volunteer-exchange-platform' ),
            self::STARTED    => __( 'Started', 'volunteer-exchange-platform' ),
            self::COMPLETED  => __( 'Completed', 'volunteer-exchange-platform' ),
            self::CANCELLED  => __( 'Cancelled', 'volunteer-exchange-platform' ),
            self::NEEDS_HELP => __( 'Needs help (FCO contacts)', 'volunteer-exchange-platform' ),
        );
    }

    /**
     * Status key → translated label map including an "All" entry for filters.
     *
     * @return array<string, string>
     */
    public static function labels_with_all() {
        return array( 'all' => __( 'All', 'volunteer-exchange-platform' ) ) + self::labels();
    }

    /**
     * Return the translated label for a single status key.
     *
     * @param string $status Status key.
     * @return string
     */
    public static function label( $status ) {
        $labels = self::labels();
        return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
    }

    /**
     * Return the default status for new agreements.
     *
     * @return string
     */
    public static function default_status() {
        return self::AGREED;
    }
}
