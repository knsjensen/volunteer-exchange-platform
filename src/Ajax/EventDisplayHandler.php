<?php
/**
 * Event Display AJAX handler
 *
 * @package VEP
 * @subpackage Ajax
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Services\CompetitionService;
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
     * @var CompetitionService
     */
    private $competition_service;

    /**
     * Constructor.
     *
     * @param EventService|null       $event_service Event service instance.
     * @param CompetitionService|null $competition_service Competition service instance.
     * @return void
     */
    public function __construct( ?EventService $event_service = null, ?CompetitionService $competition_service = null ) {
        $this->event_service = $event_service ?: new EventService();
        $this->competition_service = $competition_service ?: new CompetitionService();
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

        $display_mode = isset( $_POST['display_mode'] ) ? sanitize_key( wp_unslash( $_POST['display_mode'] ) ) : 'leaderboard';
        if ( ! in_array( $display_mode, array( 'leaderboard', 'recent_agreements', 'none' ), true ) ) {
            $display_mode = 'leaderboard';
        }

        $this->competition_service->auto_select_winners_for_event( $event_id );

        $count = $this->event_service->count_agreements_for_event($event_id);
        $leaderboard = $this->event_service->get_event_leaderboard($event_id, 10);
        $recent_agreements = $this->event_service->get_recent_agreements($event_id, 4);
        
        wp_send_json_success(array(
            'count' => intval($count),
            'display_mode' => $display_mode,
            'leaderboard' => $leaderboard,
            'recent_agreements' => $recent_agreements
        ));
    }
}
