<?php
/**
 * Event live statistics AJAX handler
 *
 * @package VEP
 * @subpackage Ajax
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Services\EventService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event live statistics AJAX handler
 *
 * Serves the admin-only "Eventstatistik" panel on the presentation page.
 *
 * @package VolunteerExchangePlatform\Ajax
 */
class EventStatsHandler {

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
        add_action( 'wp_ajax_vep_event_live_stats', array( $this, 'get_live_stats' ) );
    }

    /**
     * Return all live statistics for an event in one response.
     *
     * @return void
     */
    public function get_live_stats() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'volunteer-exchange-platform' ) ), 403 );
            return;
        }

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        $stats = $this->event_service->get_live_event_stats( $event_id );

        if ( null === $stats ) {
            wp_send_json_error( array( 'message' => 'Could not load event statistics' ) );
            return;
        }

        wp_send_json_success( $stats );
    }
}
