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
use VolunteerExchangePlatform\Services\ParticipantService;

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
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * Constructor.
     *
     * @param EventService|null       $event_service Event service instance.
     * @param CompetitionService|null $competition_service Competition service instance.
     * @param ParticipantService|null $participant_service Participant service instance.
     * @return void
     */
    public function __construct( ?EventService $event_service = null, ?CompetitionService $competition_service = null, ?ParticipantService $participant_service = null ) {
        $this->event_service = $event_service ?: new EventService();
        $this->competition_service = $competition_service ?: new CompetitionService();
        $this->participant_service = $participant_service ?: new ParticipantService();
        add_action('wp_ajax_vep_get_agreement_count', array($this, 'get_agreement_count'));
        add_action('wp_ajax_vep_get_event_statistics', array($this, 'get_event_statistics'));
        add_action('wp_ajax_vep_get_event_competitions', array($this, 'get_event_competitions'));
        add_action('wp_ajax_vep_activate_competition_winners', array($this, 'activate_competition_winners'));
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

    /**
     * Get post-event statistics for the fullscreen display via AJAX.
     *
     * @return void
     */
    public function get_event_statistics() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        $stats = $this->event_service->get_display_statistics( $event_id );

        if ( null === $stats ) {
            wp_send_json_error( array( 'message' => 'Could not load event statistics' ) );
            return;
        }

        wp_send_json_success( $stats );
    }

    /**
     * Get competitions for the fullscreen display via AJAX.
     *
     * @return void
     */
    public function get_event_competitions() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        $competitions = $this->competition_service->get_competitions_for_event( $event_id );

        // Enrich each competition with a resolved winner_name field.
        foreach ( $competitions as $competition ) {
            $competition->winner_name = '';
            if ( isset( $competition->winner_input_type ) && 'text' === $competition->winner_input_type && ! empty( $competition->winner_text ) ) {
                $competition->winner_name = (string) $competition->winner_text;
            } elseif ( ! empty( $competition->winner_id ) ) {
                $participant = $this->participant_service->get_by_id( (int) $competition->winner_id );
                if ( $participant && ! empty( $participant->organization_name ) ) {
                    $competition->winner_name = (string) $participant->organization_name;
                }
            }
        }

        wp_send_json_success( array(
            'competitions' => $competitions
        ) );
    }

    /**
     * Activate competition winners when event time expires.
     *
     * Automatically selects winners for all competitions in the event.
     *
     * @return void
     */
    public function activate_competition_winners() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        $this->competition_service->auto_select_winners_for_event( $event_id );

        wp_send_json_success( array(
            'message' => 'Competition winners activated successfully'
        ) );
    }
}
