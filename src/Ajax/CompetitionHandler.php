<?php
/**
 * Competition AJAX Handler
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
 * Competition AJAX Handler class
 *
 * Handles AJAX requests for competition operations.
 *
 * @package VolunteerExchangePlatform\Ajax
 */
class CompetitionHandler {
    
    /**
     * Competition service
     *
     * @var CompetitionService
     */
    private $competition_service;
    
    /**
     * Event service
     *
     * @var EventService
     */
    private $event_service;

    /**
     * Validate AJAX nonce for competition actions.
     *
     * @return bool
     */
    private function validate_ajax_nonce() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( '' === $nonce ) {
            return false;
        }

        return (bool) ( wp_verify_nonce( $nonce, 'vep_competitions_nonce' ) || wp_verify_nonce( $nonce, 'vep_admin_nonce' ) );
    }

    /**
     * Constructor
     *
     * @param CompetitionService|null $competition_service Competition service
     * @param EventService|null       $event_service Event service
     */
    public function __construct(
        ?CompetitionService $competition_service = null,
        ?EventService $event_service = null
    ) {
        $this->competition_service = $competition_service ?: new CompetitionService();
        $this->event_service = $event_service ?: new EventService();

        add_action( 'wp_ajax_vep_reorder_competitions', array( $this, 'reorder_competitions_ajax' ) );
        add_action( 'wp_ajax_vep_set_competition_winner', array( $this, 'set_winner_ajax' ) );
        add_action( 'wp_ajax_vep_toggle_competition_active', array( $this, 'toggle_active_ajax' ) );
        add_action( 'wp_ajax_vep_delete_competition', array( $this, 'delete_competition_ajax' ) );
    }

    /**
     * Reorder competitions via AJAX
     *
     * @return void
     */
    public function reorder_competitions_ajax() {
        if ( ! $this->validate_ajax_nonce() ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'volunteer-exchange-platform' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'volunteer-exchange-platform' ) ) );
        }

        $order = isset( $_POST['order'] ) ? wp_unslash( (array) $_POST['order'] ) : array();

        if ( empty( $order ) ) {
            wp_send_json_error( array( 'message' => __( 'No order data provided', 'volunteer-exchange-platform' ) ) );
        }

        $order_map = array();
        foreach ( $order as $index => $competition_id ) {
            $order_map[ (int) $competition_id ] = (int) $index + 1;
        }

        $result = $this->competition_service->reorder_competitions( $order_map );

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Competitions reordered successfully', 'volunteer-exchange-platform' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to reorder competitions', 'volunteer-exchange-platform' ) ) );
        }
    }

    /**
     * Set competition winner via AJAX
     *
     * @return void
     */
    public function set_winner_ajax() {
        if ( ! $this->validate_ajax_nonce() ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'volunteer-exchange-platform' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'volunteer-exchange-platform' ) ) );
        }

        $competition_id = isset( $_POST['competition_id'] ) ? (int) $_POST['competition_id'] : 0;
        $winner_id = isset( $_POST['winner_id'] ) ? sanitize_text_field( wp_unslash( $_POST['winner_id'] ) ) : '';

        if ( ! $competition_id ) {
            wp_send_json_error( array( 'message' => __( 'Competition ID is required', 'volunteer-exchange-platform' ) ) );
        }

        // Handle "auto" or specific winner_id
        $winner_id_int = null;
        if ( 'auto' === $winner_id ) {
            // Will be handled by auto-select logic
            $winner_id_int = null;
        } elseif ( $winner_id ) {
            $winner_id_int = (int) $winner_id;
        }

        $result = $this->competition_service->set_winner( $competition_id, $winner_id_int );

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Winner set successfully', 'volunteer-exchange-platform' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to set winner', 'volunteer-exchange-platform' ) ) );
        }
    }

    /**
     * Toggle competition active status via AJAX
     *
     * @return void
     */
    public function toggle_active_ajax() {
        if ( ! $this->validate_ajax_nonce() ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'volunteer-exchange-platform' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'volunteer-exchange-platform' ) ) );
        }

        $competition_id = isset( $_POST['competition_id'] ) ? (int) $_POST['competition_id'] : 0;
        $action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';

        if ( ! $competition_id || ! in_array( $action, array( 'activate', 'deactivate' ), true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'volunteer-exchange-platform' ) ) );
        }

        $result = 'activate' === $action ? 
            $this->competition_service->set_active( $competition_id ) :
            $this->competition_service->set_inactive( $competition_id );

        if ( false !== $result ) {
            wp_send_json_success( array( 
                'message' => __( 'Competition status updated', 'volunteer-exchange-platform' ),
                'action' => $action,
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to update competition status', 'volunteer-exchange-platform' ) ) );
        }
    }

    /**
     * Delete competition via AJAX
     *
     * @return void
     */
    public function delete_competition_ajax() {
        if ( ! $this->validate_ajax_nonce() ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'volunteer-exchange-platform' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'volunteer-exchange-platform' ) ) );
        }

        $competition_id = isset( $_POST['competition_id'] ) ? (int) $_POST['competition_id'] : 0;

        if ( ! $competition_id ) {
            wp_send_json_error( array( 'message' => __( 'Competition ID is required', 'volunteer-exchange-platform' ) ) );
        }

        if ( ! $this->competition_service->can_delete_competition( $competition_id ) ) {
            wp_send_json_error( array( 'message' => __( 'This competition cannot be deleted', 'volunteer-exchange-platform' ) ) );
        }

        $result = $this->competition_service->delete_competition( $competition_id );

        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Competition deleted successfully', 'volunteer-exchange-platform' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete competition', 'volunteer-exchange-platform' ) ) );
        }
    }
}
