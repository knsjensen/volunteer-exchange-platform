<?php
/**
 * Participant email service
 *
 * Responsible for queuing all transactional emails related to participants.
 *
 * @package VEP
 * @subpackage Services
 */

namespace VolunteerExchangePlatform\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParticipantEmailService {

    /** @var ParticipantService */
    private $participant_service;

    /**
     * Constructor.
     *
     * @param ParticipantService|null $participant_service Participant service instance.
     */
    public function __construct( ?ParticipantService $participant_service = null ) {
        $this->participant_service = $participant_service ?: new ParticipantService();
    }

    /**
     * Queue approval confirmation email to participant contact address.
     *
     * @param int $participant_id Participant ID.
     * @return void
     */
    public function queue_participant_approved_email( $participant_id ) {
        if ( ! function_exists( 'vep_queue_transactional_email' ) ) {
            return;
        }

        $participant = $this->participant_service->get_by_id_with_details( $participant_id );
        if ( ! $participant ) {
            return;
        }

        $email = isset( $participant->contact_email ) ? sanitize_email( $participant->contact_email ) : '';
        if ( '' === $email ) {
            return;
        }

        $random_key = isset( $participant->randon_key ) ? sanitize_text_field( (string) $participant->randon_key ) : '';
        if ( '' === $random_key ) {
            return;
        }

        $confirm_url = \VolunteerExchangePlatform\Frontend\UpdateParticipantPage::get_update_participant_url( $random_key );

        vep_queue_transactional_email(
            array(
                'to'            => array( $email ),
                'template_key'  => 'participant_approved',
                'template_data' => array(
                    'contact_name' => (string) ( $participant->contact_person_name ?? '' ),
                    'event_name'   => (string) ( $participant->event_name ?? '' ),
                    'confirm_url'  => $confirm_url,
                ),
            )
        );
    }

    /**
     * Build translated missing-field entries for update reminders.
     *
     * @param object|null $participant Participant row.
     * @param array|null  $tag_ids Optional preloaded tag IDs.
     * @return array[] Array of objects with name key.
     */
    public function get_update_reminder_missing_fields( $participant, $tag_ids = null ) {
        if ( ! $participant ) {
            return array();
        }

        $missing_fields = array();

        $has_logo = ! empty( $participant->logo_url ) || ! empty( $participant->no_logo );
        if ( ! $has_logo ) {
            $missing_fields[] = array(
                'name' => __( 'Organization Logo', 'volunteer-exchange-platform' ),
            );
        }

        $has_description = ! empty( trim( (string) ( $participant->description ?? '' ) ) );
        if ( ! $has_description ) {
            $missing_fields[] = array(
                'name' => __( 'Organization Description', 'volunteer-exchange-platform' ),
            );
        }

        $expected_count = $participant->expected_participants_count ?? null;
        $has_expected_count = ( null !== $expected_count && '' !== (string) $expected_count ) || ! empty( $participant->no_expected_count );
        if ( ! $has_expected_count ) {
            $missing_fields[] = array(
                'name' => __( 'Participants Expected', 'volunteer-exchange-platform' ),
            );
        }

        // An empty JSON list ('[]') counts as no names.
        $expected_names_raw = trim( (string) ( $participant->expected_participants_names ?? '' ) );
        if ( '[]' === $expected_names_raw ) {
            $expected_names_raw = '';
        }
        $has_expected_names = '' !== $expected_names_raw || ! empty( $participant->no_expected_names );
        if ( ! $has_expected_names ) {
            $missing_fields[] = array(
                'name' => __( 'Participant Names', 'volunteer-exchange-platform' ),
            );
        }

        if ( null === $tag_ids ) {
            $tag_ids = $this->participant_service->get_tag_ids( (int) $participant->id );
        }

        if ( empty( $tag_ids ) ) {
            $missing_fields[] = array(
                'name' => __( 'What We Offer', 'volunteer-exchange-platform' ),
            );
        }

        return $missing_fields;
    }

    /**
     * Determine whether a participant still needs update reminders.
     *
     * Reminder is needed when at least one of these is missing:
     * logo, organization description, participants expected,
     * participant names, or what we offer tags.
     *
     * Participants who have unsubscribed from reminders are skipped unless $force is true.
     *
     * @param int  $participant_id Participant ID.
     * @param bool $force          Ignore the reminder unsubscribe flag (explicit override only).
     * @return bool
     */
    public function should_send_update_reminder( $participant_id, $force = false ) {
        $participant = $this->participant_service->get_by_id( $participant_id );
        if ( ! $participant || empty( $participant->is_approved ) ) {
            return false;
        }

        if ( ! $force && ! empty( $participant->reminder_unsubscribed ) ) {
            return false;
        }

        $missing_fields = $this->get_update_reminder_missing_fields( $participant );

        return ! empty( $missing_fields );
    }

    /**
     * Queue update reminder email for a participant.
     *
     * @param int  $participant_id Participant ID.
     * @param bool $force          Ignore the reminder unsubscribe flag (explicit override only).
     * @return bool True when queued, false when skipped/invalid.
     */
    public function queue_update_participant_reminder( $participant_id, $force = false ) {
        if ( ! function_exists( 'vep_queue_transactional_email' ) ) {
            return false;
        }

        if ( ! $this->should_send_update_reminder( $participant_id, $force ) ) {
            return false;
        }

        $participant = $this->participant_service->get_by_id_with_details( $participant_id );
        if ( ! $participant || empty( $participant->is_approved ) ) {
            return false;
        }

        $tag_ids = $this->participant_service->get_tag_ids( $participant_id );
        $missing_fields = $this->get_update_reminder_missing_fields( $participant, $tag_ids );
        if ( empty( $missing_fields ) ) {
            return false;
        }

        $email = isset( $participant->contact_email ) ? sanitize_email( $participant->contact_email ) : '';
        if ( '' === $email ) {
            return false;
        }

        $random_key = isset( $participant->randon_key ) ? sanitize_text_field( (string) $participant->randon_key ) : '';
        if ( '' === $random_key ) {
            return false;
        }

        $confirm_url     = \VolunteerExchangePlatform\Frontend\UpdateParticipantPage::get_update_participant_url( $random_key );
        $unsubscribe_url = \VolunteerExchangePlatform\Frontend\UnsubscribePage::get_unsubscribe_url( $random_key, 'deltager-paamindelse' );
        $event_name      = isset( $participant->event_name ) ? (string) $participant->event_name : '';
        $event_date      = '';
        if ( ! empty( $participant->event_date ) ) {
            $event_date = date_i18n( get_option( 'date_format' ), strtotime( (string) $participant->event_date ) );
        }

        $queued = vep_queue_transactional_email(
            array(
                'to'            => array( $email ),
                'template_key'  => 'update_participant',
                'template_data' => array(
                    'event_name'     => $event_name,
                    'event_date'     => $event_date,
                    'contact_name'   => (string) ( $participant->contact_person_name ?? '' ),
                    'confirm_url'     => $confirm_url,
                    'unsubscribe_url' => $unsubscribe_url,
                    'missing_fields'  => $missing_fields,
                ),
            )
        );

        return false !== $queued;
    }

    /**
     * Queue a new-registration notification email to the system info recipient.
     *
     * @param int $participant_id Participant ID.
     * @return bool
     */
    public function queue_new_registration_notification( $participant_id ) {
        if ( ! function_exists( 'vep_queue_transactional_email' ) ) {
            return false;
        }

        $recipient = \VolunteerExchangePlatform\Email\Settings::info_email_recipient();
        if ( '' === $recipient ) {
            return false;
        }

        $participant = $this->participant_service->get_by_id_with_details( $participant_id );
        if ( ! $participant ) {
            return false;
        }

        $event_name        = isset( $participant->event_name ) ? (string) $participant->event_name : '';
        $organization_name = isset( $participant->organization_name ) ? (string) $participant->organization_name : '';

        $queued = vep_queue_transactional_email(
            array(
                'to'            => array( $recipient ),
                'template_key'  => 'new_participant_registration',
                'template_data' => array(
                    'event_name'        => $event_name,
                    'organization_name' => $organization_name,
                    'admin_url'         => admin_url(),
                ),
            )
        );

        return false !== $queued;
    }

    /**
     * Queue an agreement overview email for a single participant.
     *
     * @param int   $participant_id       Participant ID.
     * @param int   $event_id             Event ID.
     * @param array $agreements           Array of agreement objects (each with partner_name, status, description).
     * @param int   $total_agreement_count Total number of agreements on the event.
     * @return bool True if queued.
     */
    public function queue_agreement_overview_email( $participant_id, $event_id, $agreements, $total_agreement_count = 0 ) {
        if ( ! function_exists( 'vep_queue_transactional_email' ) ) {
            return false;
        }

        $participant = $this->participant_service->get_by_id_with_details( (int) $participant_id );
        if ( ! $participant || empty( $participant->is_approved ) ) {
            return false;
        }

        // Respect the agreement overview unsubscribe flag.
        if ( ! empty( $participant->agreement_overview_unsubscribed ) ) {
            return false;
        }

        $email = isset( $participant->contact_email ) ? sanitize_email( $participant->contact_email ) : '';
        if ( '' === $email ) {
            return false;
        }

        $random_key = ! empty( $participant->randon_key )
            ? sanitize_text_field( (string) $participant->randon_key )
            : $this->participant_service->ensure_randon_key( (int) $participant_id );
        if ( '' === $random_key ) {
            return false;
        }

        $update_url      = \VolunteerExchangePlatform\Frontend\UpdateAgreementsPage::get_update_agreements_url( $random_key );
        $unsubscribe_url = \VolunteerExchangePlatform\Frontend\UnsubscribePage::get_unsubscribe_url( $random_key, 'aftale-paamindelse' );
        $event_name      = isset( $participant->event_name ) ? (string) $participant->event_name : '';
        $event_date_raw  = isset( $participant->event_date ) ? (string) $participant->event_date : '';
        $event_date      = '' !== $event_date_raw ? date_i18n( 'j. F Y', strtotime( $event_date_raw ) ) : '';
        $contact_name    = isset( $participant->contact_person_name ) ? (string) $participant->contact_person_name : '';
        $org_name        = isset( $participant->organization_name ) ? (string) $participant->organization_name : '';

        // Build agreements array for template.
        $agreements_data = array();
        foreach ( $agreements as $agreement ) {
            $status_key   = isset( $agreement->status ) ? (string) $agreement->status : '';
            $status_label = AgreementStatus::label( $status_key );
            $is_initiator = isset( $agreement->initiator_id ) && (int) $agreement->initiator_id === (int) $participant_id;
            $agreements_data[] = array(
                'organization_name' => isset( $agreement->organization_name ) ? (string) $agreement->organization_name : '',
                'contact_name'      => isset( $agreement->contact_name )      ? (string) $agreement->contact_name      : '',
                'contact_email'     => isset( $agreement->contact_email )     ? sanitize_email( (string) $agreement->contact_email ) : '',
                'contact_phone'     => isset( $agreement->contact_phone )     ? (string) $agreement->contact_phone     : '',
                'initiator'            => $is_initiator ? __( 'You', 'volunteer-exchange-platform' ) : __( 'Them', 'volunteer-exchange-platform' ),
                'description'          => isset( $agreement->description )          ? (string) $agreement->description          : '',
                'status'               => $status_label,
            );
        }

        $agreement_count = count( $agreements );

        $queued = vep_queue_transactional_email(
            array(
                'to'            => array( $email ),
                'template_key'  => 'indgaaede_aftale_oversigt',
                'template_data' => array(
                    'total_agreement_count' => (int) $total_agreement_count,
                    'event_name'            => $event_name,
                    'event_date'            => $event_date,
                    'contact_name'          => $contact_name,
                    'organization_name'     => $org_name,
                    'agreement_count'       => $agreement_count,
                    'agreement_count_one'   => 1 === $agreement_count,
                    'agreements'            => $agreements_data,
                    'update_agreements_url' => $update_url,
                    'unsubscribe_url'       => $unsubscribe_url,
                ),
            )
        );

        return false !== $queued;
    }
}
