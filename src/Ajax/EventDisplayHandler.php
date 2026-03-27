<?php
/**
 * Event Display AJAX handler
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
 * Event Display AJAX handler
 * 
 * Handles AJAX requests for retrieving agreement count and leaderboard data
 * 
 * @package VolunteerExchangePlatform\Ajax
 */
class EventDisplayHandler {
    
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
        add_action('wp_ajax_vep_get_agreement_count', array($this, 'get_agreement_count'));
    }
    
    /**
     * Get agreement count and leaderboard via AJAX
     *
     * Returns total agreement count and top participants list for display
     *
     * @return void
     */
    public function get_agreement_count() {
        check_ajax_referer('vep_admin_nonce', 'nonce');

        // Get event ID from request
        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        
        if (!$event_id) {
            wp_send_json_error(array('message' => 'No event ID provided'));
            return;
        }
        $count = $this->event_service->count_agreements_for_event($event_id);
        $leaderboard = $this->event_service->get_event_leaderboard($event_id, 10);
        
        wp_send_json_success(array(
            'count' => intval($count),
            'leaderboard' => $leaderboard
        ));
    }
}
