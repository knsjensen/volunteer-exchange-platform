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
        add_action('wp_ajax_vep_display_report_state', array($this, 'display_report_state'));
        add_action('wp_ajax_vep_display_get_state', array($this, 'display_get_state'));
        add_action('wp_ajax_vep_display_send_command', array($this, 'display_send_command'));
        add_action('wp_ajax_vep_display_poll_command', array($this, 'display_poll_command'));
        add_action('wp_ajax_vep_display_clear_state', array($this, 'display_clear_state'));
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

    /**
     * Display reports its current view state.
     *
     * @return void
     */
    public function display_report_state() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        $allowed_views = array( 'countdown', 'statistics', 'competitions', 'winner', 'closing' );
        $view = isset( $_POST['view'] ) ? sanitize_key( wp_unslash( $_POST['view'] ) ) : '';
        if ( ! in_array( $view, $allowed_views, true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid view' ) );
            return;
        }

        $competitions = array();
        if ( isset( $_POST['competitions'] ) ) {
            $raw = json_decode( wp_unslash( $_POST['competitions'] ), true );
            if ( is_array( $raw ) ) {
                foreach ( $raw as $item ) {
                    if ( isset( $item['title'], $item['index'] ) ) {
                        $competitions[] = array(
                            'title' => sanitize_text_field( $item['title'] ),
                            'index' => absint( $item['index'] ),
                        );
                    }
                }
            }
        }

        $state = array(
            'view'          => $view,
            'winner_index'  => isset( $_POST['winner_index'] ) ? absint( wp_unslash( $_POST['winner_index'] ) ) : 0,
            'total_winners' => isset( $_POST['total_winners'] ) ? absint( wp_unslash( $_POST['total_winners'] ) ) : 0,
            'auto_switch'   => isset( $_POST['auto_switch'] ) && '1' === $_POST['auto_switch'],
            'competitions'  => $competitions,
            'ts'            => time(),
        );

        // TTL 30 seconds — refreshed every 2 s by command polling; acts as heartbeat.
        set_transient( 'vep_display_state_' . $event_id, $state, 30 );

        wp_send_json_success();
    }

    /**
     * Admin page polls for the current display state.
     *
     * @return void
     */
    public function display_get_state() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        $state = get_transient( 'vep_display_state_' . $event_id );
        if ( false === $state ) {
            wp_send_json_success( array( 'active' => false ) );
            return;
        }

        wp_send_json_success( array_merge( $state, array( 'active' => true ) ) );
    }

    /**
     * Admin nav box sends a navigation command to the display.
     *
     * @return void
     */
    public function display_send_command() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        $allowed_actions = array( 'showCountdown', 'showStatistics', 'showCompetitions', 'showWinner', 'showClosing' );
        $action = isset( $_POST['nav_action'] ) ? sanitize_text_field( wp_unslash( $_POST['nav_action'] ) ) : '';
        if ( ! in_array( $action, $allowed_actions, true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid action' ) );
            return;
        }

        $command = array(
            'action'       => $action,
            'winner_index' => isset( $_POST['winner_index'] ) ? absint( wp_unslash( $_POST['winner_index'] ) ) : 0,
            'timestamp'    => time(),
        );

        // TTL long enough for all displays (polling every 1 s) to receive the command.
        set_transient( 'vep_display_command_' . $event_id, $command, 10 );

        wp_send_json_success();
    }

    /**
     * Display polls for a pending navigation command (and clears it).
     *
     * Also acts as a heartbeat to keep the state transient alive.
     *
     * @return void
     */
    public function display_poll_command() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        // Refresh state TTL (heartbeat).
        $state = get_transient( 'vep_display_state_' . $event_id );
        if ( false !== $state ) {
            set_transient( 'vep_display_state_' . $event_id, $state, 30 );
        }

        $command = get_transient( 'vep_display_command_' . $event_id );
        if ( false === $command ) {
            wp_send_json_success( array( 'command' => null ) );
            return;
        }

        // Do NOT delete the transient — let TTL expire it so all open displays
        // (multiple screens) can each receive and act on the same command.
        wp_send_json_success( array( 'command' => $command ) );
    }

    /**
     * Display signals that it has closed — removes the state transient immediately
     * so the admin nav box hides on its next poll.
     *
     * @return void
     */
    public function display_clear_state() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        delete_transient( 'vep_display_state_'   . $event_id );
        delete_transient( 'vep_display_command_' . $event_id );

        wp_send_json_success();
    }
}
