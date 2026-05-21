<?php
/**
 * AJAX handler for frontend agreement status updates and unsubscribe toggles.
 *
 * @package VEP
 * @subpackage Ajax
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Services\AgreementStatus;
use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FrontendAgreementsHandler {

    /** @var EventService */
    private $event_service;

    /** @var ParticipantService */
    private $participant_service;

    /**
     * Constructor.
     *
     * @param EventService|null       $event_service       Event service.
     * @param ParticipantService|null $participant_service Participant service.
     */
    public function __construct(
        ?EventService $event_service = null,
        ?ParticipantService $participant_service = null
    ) {
        $this->event_service       = $event_service ?: new EventService();
        $this->participant_service = $participant_service ?: new ParticipantService();

        // Both actions run as nopriv (public-facing pages).
        add_action( 'wp_ajax_vep_frontend_toggle_unsubscribe',        array( $this, 'handle_toggle_unsubscribe' ) );
        add_action( 'wp_ajax_nopriv_vep_frontend_toggle_unsubscribe', array( $this, 'handle_toggle_unsubscribe' ) );

        add_action( 'wp_ajax_vep_frontend_update_agreement_status',        array( $this, 'handle_update_agreement_status' ) );
        add_action( 'wp_ajax_nopriv_vep_frontend_update_agreement_status', array( $this, 'handle_update_agreement_status' ) );
    }

    /**
     * Toggle reminder_unsubscribed flag for a participant identified by randon_key.
     *
     * POST params: randon_key, unsubscribe (1|0), _wpnonce
     *
     * @return void
     */
    public function handle_toggle_unsubscribe() {
        check_ajax_referer( 'vep_frontend_nonce', '_wpnonce' );

        $randon_key = isset( $_POST['randon_key'] ) ? sanitize_text_field( (string) $_POST['randon_key'] ) : '';
        if ( '' === $randon_key ) {
            wp_send_json_error( __( 'Invalid request.', 'volunteer-exchange-platform' ), 400 );
        }

        $participant = $this->participant_service->get_by_randon_key( $randon_key );
        if ( ! $participant ) {
            wp_send_json_error( __( 'Participant not found.', 'volunteer-exchange-platform' ), 404 );
        }

        $unsubscribe = isset( $_POST['unsubscribe'] ) ? (int) $_POST['unsubscribe'] : 1;
        $unsubscribe = ( 0 === $unsubscribe ) ? 0 : 1;

        // Resolve type — accept both Danish and English slugs.
        $type_map = array(
            'deltager-paamindelse' => 'reminder',
            'participant-reminder' => 'reminder',
            'aftale-paamindelse'   => 'agreement_overview',
            'agreement-reminder'   => 'agreement_overview',
        );
        $raw_type    = isset( $_POST['type'] ) ? sanitize_key( (string) $_POST['type'] ) : 'deltager-paamindelse';
        $field_type  = isset( $type_map[ $raw_type ] ) ? $type_map[ $raw_type ] : 'reminder';

        if ( 'agreement_overview' === $field_type ) {
            $ok = $this->participant_service->set_agreement_overview_unsubscribed( (int) $participant->id, $unsubscribe );
            if ( ! $ok ) {
                wp_send_json_error( __( 'Could not update preference.', 'volunteer-exchange-platform' ), 500 );
            }
            $message = 1 === $unsubscribe
                ? __( 'You have been unsubscribed from agreement overview emails.', 'volunteer-exchange-platform' )
                : __( 'You have been re-subscribed to agreement overview emails.', 'volunteer-exchange-platform' );
        } else {
            $ok = $this->participant_service->set_reminder_unsubscribed( (int) $participant->id, $unsubscribe );
            if ( ! $ok ) {
                wp_send_json_error( __( 'Could not update preference.', 'volunteer-exchange-platform' ), 500 );
            }
            $message = 1 === $unsubscribe
                ? __( 'You have been unsubscribed from future reminders.', 'volunteer-exchange-platform' )
                : __( 'You have been re-subscribed to reminders.', 'volunteer-exchange-platform' );
        }

        wp_send_json_success( array(
            'message'       => $message,
            'unsubscribed'  => $unsubscribe,
        ) );
    }

    /**
     * Update the status of an agreement, verifying that the requesting participant is a party to it.
     *
     * POST params: randon_key, agreement_id, status, _wpnonce
     *
     * @return void
     */
    public function handle_update_agreement_status() {
        check_ajax_referer( 'vep_frontend_nonce', '_wpnonce' );

        $randon_key    = isset( $_POST['randon_key'] )    ? sanitize_text_field( (string) $_POST['randon_key'] ) : '';
        $agreement_id  = isset( $_POST['agreement_id'] )  ? absint( $_POST['agreement_id'] )                      : 0;
        $status        = isset( $_POST['status'] )        ? sanitize_key( (string) $_POST['status'] )              : '';

        $allowed_statuses = AgreementStatus::all();

        if ( '' === $randon_key || 0 === $agreement_id || ! in_array( $status, $allowed_statuses, true ) ) {
            wp_send_json_error( __( 'Invalid request.', 'volunteer-exchange-platform' ), 400 );
        }

        $participant = $this->participant_service->get_by_randon_key( $randon_key );
        if ( ! $participant ) {
            wp_send_json_error( __( 'Participant not found.', 'volunteer-exchange-platform' ), 404 );
        }

        $agreement = $this->event_service->get_agreement_by_id( $agreement_id );
        if ( ! $agreement ) {
            wp_send_json_error( __( 'Agreement not found.', 'volunteer-exchange-platform' ), 404 );
        }

        // Verify the participant is a party to this agreement.
        $participant_id = (int) $participant->id;
        if ( (int) $agreement->participant1_id !== $participant_id && (int) $agreement->participant2_id !== $participant_id ) {
            wp_send_json_error( __( 'Access denied.', 'volunteer-exchange-platform' ), 403 );
        }

        $ok = $this->event_service->update_agreement( $agreement_id, array( 'status' => $status ) );
        if ( ! $ok ) {
            wp_send_json_error( __( 'Could not update agreement status.', 'volunteer-exchange-platform' ), 500 );
        }

        wp_send_json_success( array(
            'message' => __( 'Status saved.', 'volunteer-exchange-platform' ),
            'status'  => $status,
        ) );
    }
}
