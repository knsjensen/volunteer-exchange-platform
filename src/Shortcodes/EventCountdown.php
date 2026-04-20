<?php
/**
 * Event countdown shortcode.
 * Usage: [vep_event_countdown]
 *
 * @package VEP
 * @subpackage Shortcodes
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Shortcodes;

use VolunteerExchangePlatform\Services\EventService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EventCountdown {
    /**
     * @var EventService
     */
    private $event_service;

    /**
     * Constructor.
     *
     * @param EventService|null $event_service Event service instance.
     * @return void
     */
    public function __construct( ?EventService $event_service = null ) {
        $this->event_service = $event_service ?: new EventService();
        add_shortcode( 'vep_event_countdown', array( $this, 'render' ) );
    }

    /**
     * Render shortcode output.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'in_progress_text' => __( 'The event is in progress.', 'volunteer-exchange-platform' ),
                'event_over_text'  => __( 'The event is over.', 'volunteer-exchange-platform' ),
                'unit_mode'        => 'months',
            ),
            $atts,
            'vep_event_countdown'
        );

        $unit_mode = strtolower( trim( (string) $atts['unit_mode'] ) );
        if ( ! in_array( $unit_mode, array( 'months', 'days' ), true ) ) {
            $unit_mode = 'months';
        }

        $active_event = $this->event_service->get_active_event();
        if ( ! $active_event ) {
            return '<div class="vep-message vep-error">' . esc_html__( 'No active event at the moment.', 'volunteer-exchange-platform' ) . '</div>';
        }

        $start_timestamp = $this->to_timestamp( $active_event->start_date ?? '' );
        $end_timestamp = $this->to_timestamp( $active_event->end_date ?? '' );

        if ( ! $start_timestamp ) {
            return '';
        }

        ob_start();
        ?>
        <div
            class="vep-event-countdown"
            data-start-timestamp="<?php echo esc_attr( (string) $start_timestamp ); ?>"
            data-end-timestamp="<?php echo esc_attr( (string) $end_timestamp ); ?>"
            data-in-progress-text="<?php echo esc_attr( (string) $atts['in_progress_text'] ); ?>"
            data-event-over-text="<?php echo esc_attr( (string) $atts['event_over_text'] ); ?>"
            data-unit-mode="<?php echo esc_attr( $unit_mode ); ?>"
        >
            <div class="vep-event-countdown-timer" aria-live="polite">
                <div class="vep-event-countdown-unit" data-unit="months">
                    <div class="vep-event-countdown-value">0</div>
                    <div class="vep-event-countdown-label"><?php esc_html_e( 'Months', 'volunteer-exchange-platform' ); ?></div>
                </div>
                <div class="vep-event-countdown-unit" data-unit="days">
                    <div class="vep-event-countdown-value">0</div>
                    <div class="vep-event-countdown-label"><?php esc_html_e( 'Days', 'volunteer-exchange-platform' ); ?></div>
                </div>
                <div class="vep-event-countdown-unit" data-unit="hours">
                    <div class="vep-event-countdown-value">0</div>
                    <div class="vep-event-countdown-label"><?php esc_html_e( 'Hours', 'volunteer-exchange-platform' ); ?></div>
                </div>
                <div class="vep-event-countdown-unit" data-unit="minutes">
                    <div class="vep-event-countdown-value">0</div>
                    <div class="vep-event-countdown-label"><?php esc_html_e( 'Minutes', 'volunteer-exchange-platform' ); ?></div>
                </div>
                <div class="vep-event-countdown-unit" data-unit="seconds">
                    <div class="vep-event-countdown-value">0</div>
                    <div class="vep-event-countdown-label"><?php esc_html_e( 'Seconds', 'volunteer-exchange-platform' ); ?></div>
                </div>
            </div>
            <div class="vep-event-countdown-status" style="display: none;"></div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Convert a local event datetime string to a Unix timestamp using the site timezone.
     *
     * @param string $datetime Event datetime string.
     * @return int
     */
    private function to_timestamp( $datetime ) {
        $datetime = trim( (string) $datetime );
        if ( '' === $datetime ) {
            return 0;
        }

        try {
            $date = new \DateTimeImmutable( $datetime, $this->get_site_timezone() );
            return (int) $date->getTimestamp();
        } catch ( \Exception $exception ) {
            return 0;
        }
    }

    /**
     * Resolve the site's configured timezone.
     *
     * @return \DateTimeZone
     */
    private function get_site_timezone() {
        $timezone_string = (string) get_option( 'timezone_string', '' );
        if ( '' !== $timezone_string ) {
            try {
                return new \DateTimeZone( $timezone_string );
            } catch ( \Exception $exception ) {
                // Fall back to numeric offset below.
            }
        }

        $offset = (float) get_option( 'gmt_offset', 0 );
        $sign = $offset < 0 ? '-' : '+';
        $absolute_offset = abs( $offset );
        $hours = (int) floor( $absolute_offset );
        $minutes = (int) round( ( $absolute_offset - $hours ) * 60 );

        return new \DateTimeZone( sprintf( '%s%02d:%02d', $sign, $hours, $minutes ) );
    }
}