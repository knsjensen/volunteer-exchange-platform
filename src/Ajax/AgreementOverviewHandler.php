<?php
/**
 * Agreement Overview AJAX Handler
 *
 * @package VEP
 * @subpackage Ajax
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;
use VolunteerExchangePlatform\Email\Settings as EmailSettings;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles AJAX requests for "Send Agreement Overview" (admin-side bulk and single).
 */
class AgreementOverviewHandler {

    /**
     * @var EventService
     */
    private $event_service;

    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * Constructor
     *
     * @param EventService|null       $event_service
     * @param ParticipantService|null $participant_service
     */
    public function __construct(
        ?EventService $event_service = null,
        ?ParticipantService $participant_service = null
    ) {
        $this->event_service       = $event_service       ?: new EventService();
        $this->participant_service = $participant_service ?: new ParticipantService();

        add_action( 'wp_ajax_vep_send_agreement_overview',        array( $this, 'send_agreement_overview_ajax' ) );
        add_action( 'wp_ajax_vep_send_single_agreement_overview', array( $this, 'send_single_agreement_overview_ajax' ) );
    }

    /**
     * Send agreement overview emails to all eligible participants for an event.
     */
    public function send_agreement_overview_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'volunteer-exchange-platform' ) ), 403 );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'volunteer-exchange-platform' ) ), 403 );
        }

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        if ( $event_id <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid event.', 'volunteer-exchange-platform' ) ) );
        }

        $event = $this->event_service->get_by_id( $event_id );
        if ( ! $event ) {
            wp_send_json_error( array( 'message' => __( 'Event not found.', 'volunteer-exchange-platform' ) ) );
        }

        if ( ! empty( $event->agreement_overview_sent ) ) {
            wp_send_json_error( array( 'message' => __( 'Agreement overview has already been sent for this event.', 'volunteer-exchange-platform' ) ) );
        }

        // Pre-flight: verify SMTP2GO is configured.
        if ( '' === EmailSettings::api_key() ) {
            wp_send_json_error( array( 'message' => __( 'SMTP2GO API key is not configured. Please check Email Settings.', 'volunteer-exchange-platform' ) ) );
        }
        if ( null === EmailSettings::get_profile( 'indgaaede_aftale_oversigt' ) ) {
            wp_send_json_error( array( 'message' => __( 'No email template profile is configured for "indgaaede_aftale_oversigt". Please add it in Email Settings.', 'volunteer-exchange-platform' ) ) );
        }

        $participants = $this->event_service->get_participants_with_agreements_for_overview( $event_id );

        $sent_count = 0;
        foreach ( $participants as $participant ) {
            $agreements = isset( $participant->agreements ) ? $participant->agreements : array();
            if ( empty( $agreements ) ) {
                continue;
            }
            $queued = $this->participant_service->queue_agreement_overview_or_reminder_email(
                (int) $participant->id,
                $event_id,
                $agreements,
                false
            );
            if ( $queued ) {
                $sent_count++;
            }
        }

        // Mark event as sent regardless of partial failures so the button is disabled.
        $this->event_service->set_agreement_overview_sent( $event_id, 1 );

        wp_send_json_success( array(
            /* translators: %d: number of emails queued */
            'message'    => sprintf( __( 'Agreement overview queued for %d participant(s).', 'volunteer-exchange-platform' ), $sent_count ),
            'sent_count' => $sent_count,
        ) );
    }

    /**
     * Send agreement overview email for a single participant.
     */
    public function send_single_agreement_overview_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'volunteer-exchange-platform' ) ), 403 );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'volunteer-exchange-platform' ) ), 403 );
        }

        $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
        $event_id       = isset( $_POST['event_id'] )       ? absint( wp_unslash( $_POST['event_id'] ) )       : 0;

        if ( $participant_id <= 0 || $event_id <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid participant or event.', 'volunteer-exchange-platform' ) ) );
        }

        $agreements = $this->event_service->get_agreements_for_participant_on_event( $participant_id, $event_id );
        if ( empty( $agreements ) ) {
            wp_send_json_error( array( 'message' => __( 'No agreements found for this participant.', 'volunteer-exchange-platform' ) ) );
        }

        // Pre-flight: verify SMTP2GO is configured.
        if ( '' === EmailSettings::api_key() ) {
            wp_send_json_error( array( 'message' => __( 'SMTP2GO API key is not configured. Please check Email Settings.', 'volunteer-exchange-platform' ) ) );
        }
        if ( null === EmailSettings::get_profile( 'indgaaede_aftale_oversigt' ) ) {
            wp_send_json_error( array( 'message' => __( 'No email template profile is configured for "indgaaede_aftale_oversigt". Please add it in Email Settings.', 'volunteer-exchange-platform' ) ) );
        }

        $queued = $this->participant_service->queue_agreement_overview_or_reminder_email( $participant_id, $event_id, $agreements, false );

        if ( $queued ) {
            wp_send_json_success( array(
                'message' => __( 'Agreement overview queued for sending.', 'volunteer-exchange-platform' ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Could not queue agreement overview email. Check that the participant has a valid email address.', 'volunteer-exchange-platform' ),
            ) );
        }
    }
}
