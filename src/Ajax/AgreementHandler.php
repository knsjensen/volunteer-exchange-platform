<?php
/**
 * Agreement AJAX handler
 *
 * @package VEP
 * @subpackage Ajax
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Services\CompetitionService;
use VolunteerExchangePlatform\Services\EventService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Agreement AJAX handler
 *
 * Handles AJAX requests for creating agreements between participants
 *
 * @package VolunteerExchangePlatform\Ajax
 */
class AgreementHandler {
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
        add_action('wp_ajax_vep_create_agreement', array($this, 'create'));
        add_action('wp_ajax_nopriv_vep_create_agreement', array($this, 'create'));
    }

    /**
     * Create agreement via AJAX
     *
     * Validates request, creates agreement record between two participants
     *
     * @return void
     */
    public function create() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_frontend_nonce' ) ) {
            wp_send_json_error(array('message' => __('Security check failed.', 'volunteer-exchange-platform')));
        }

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        $participant1_id = isset( $_POST['participant1_id'] ) ? absint( wp_unslash( $_POST['participant1_id'] ) ) : 0;
        $participant2_id = isset( $_POST['participant2_id'] ) ? absint( wp_unslash( $_POST['participant2_id'] ) ) : 0;
        $initiator = isset( $_POST['initiator'] ) ? sanitize_key( wp_unslash( $_POST['initiator'] ) ) : '';
        $agreement_description = isset( $_POST['agreement_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['agreement_description'] ) ) : '';

        if ( ! $event_id || ! $participant1_id || ! $participant2_id || '' === $initiator || '' === $agreement_description ) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', 'volunteer-exchange-platform')));
        }

        if ($participant1_id === $participant2_id) {
            wp_send_json_error(array('message' => __('Please select two different participants.', 'volunteer-exchange-platform')));
        }

        if ($initiator === 'participant1') {
            $initiator_id = $participant1_id;
        } elseif ($initiator === 'participant2') {
            $initiator_id = $participant2_id;
        } else {
            wp_send_json_error(array('message' => __('Please select who initiates the agreement.', 'volunteer-exchange-platform')));
        }

        $data = array(
            'event_id' => $event_id,
            'participant1_id' => $participant1_id,
            'participant2_id' => $participant2_id,
            'initiator_id' => $initiator_id,
            'description' => $agreement_description,
            'status' => 'active'
        );

        $inserted = $this->event_service->create_agreement($data);

        if ($inserted === false) {
            wp_send_json_error(array('message' => __('Error creating agreement. Please try again.', 'volunteer-exchange-platform')));
        }

        $this->competition_service->auto_select_winners_for_event( $event_id );

        wp_send_json_success(array(
            'message' => __('Agreement created successfully!', 'volunteer-exchange-platform')
        ));
    }
}
