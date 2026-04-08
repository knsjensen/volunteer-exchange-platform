<?php
/**
 * Participant AJAX handler
 *
 * @package VEP
 * @subpackage Ajax
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Email\EmailSettings;
use VolunteerExchangePlatform\Services\ParticipantService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Participant AJAX handler
 * 
 * Handles AJAX requests for participant registration from frontend
 * 
 * @package VolunteerExchangePlatform\Ajax
 */
class ParticipantHandler {
    
    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * Constructor.
     *
     * @param ParticipantService|null $participant_service Participant service instance.
     * @return void
     */
    public function __construct( ?ParticipantService $participant_service = null ) {
        $this->participant_service = $participant_service ?: new ParticipantService();
        add_action('wp_ajax_vep_register_participant', array($this, 'register'));
        add_action('wp_ajax_nopriv_vep_register_participant', array($this, 'register'));
        add_action('wp_ajax_vep_update_participant', array($this, 'update'));
        add_action('wp_ajax_nopriv_vep_update_participant', array($this, 'update'));
        add_action('wp_ajax_vep_get_participant_for_update', array($this, 'get_participant_for_update'));
        add_action('wp_ajax_nopriv_vep_get_participant_for_update', array($this, 'get_participant_for_update'));
    }
    
    /**
     * Register participant via AJAX
     *
     * Validates submission, uploads logo, and creates participant record
     *
     * @return void
     */
    public function register() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_frontend_nonce' ) ) {
            wp_send_json_error(array('message' => __('Security check failed.', 'volunteer-exchange-platform')));
        }

        $organization_name = isset( $_POST['organization_name'] ) ? sanitize_text_field( wp_unslash( $_POST['organization_name'] ) ) : '';
        $participant_type_id = isset( $_POST['participant_type_id'] ) ? absint( wp_unslash( $_POST['participant_type_id'] ) ) : 0;
        $contact_person_name = isset( $_POST['contact_person_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_person_name'] ) ) : '';
        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        $tags = isset( $_POST['tags'] ) && is_array( $_POST['tags'] ) ? array_map( 'absint', wp_unslash( $_POST['tags'] ) ) : array();
        
        // Validate required fields
        if ( '' === $organization_name || ! $participant_type_id || '' === $contact_person_name || ! $event_id ) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', 'volunteer-exchange-platform')));
        }
        
        // Handle logo upload
        $logo_url = '';
        $logo_name = isset( $_FILES['logo']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['logo']['name'] ) ) : '';
        if ( '' !== $logo_name ) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_handle_upload expects the raw $_FILES entry.
            $file = $_FILES['logo'];
            $file_size = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
            $file_type = isset( $file['type'] ) ? sanitize_mime_type( $file['type'] ) : '';
            
            // Validate file size (2MB max)
            if ($file_size > 2097152) {
                wp_send_json_error(array('message' => __('Logo file size must be less than 2MB.', 'volunteer-exchange-platform')));
            }
            
            // Validate file type
            $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
            if (!in_array($file_type, $allowed_types, true)) {
                wp_send_json_error(array('message' => __('Logo must be JPG, PNG, or GIF format.', 'volunteer-exchange-platform')));
            }
            
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($file, $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                $logo_url = $movefile['url'];
            } else {
                wp_send_json_error(array('message' => __('Error uploading logo.', 'volunteer-exchange-platform')));
            }
        }
        
        // Insert participant
        $expected_count = null;
        $expected_count_raw = isset( $_POST['expected_participants_count'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_participants_count'] ) ) : '';
        if ( '' !== $expected_count_raw ) {
            $expected_count = absint( $expected_count_raw );
        }

        $data = array(
            'event_id' => $event_id,
            'organization_name' => $organization_name,
            'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'expected_participants_count' => $expected_count,
            'expected_participants_names' => isset( $_POST['expected_participants_names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['expected_participants_names'] ) ) : '',
            'participant_type_id' => $participant_type_id,
            'contact_person_name' => $contact_person_name,
            'contact_email' => isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '',
            'contact_phone' => isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '',
            'logo_url' => $logo_url,
            'is_approved' => 0 // Awaiting approval
        );
        $tag_ids = $tags;

        $participant_id = $this->participant_service->create_with_tags($data, $tag_ids);

        if ( false === $participant_id ) {
            wp_send_json_error(array('message' => __('Error saving registration. Please try again.', 'volunteer-exchange-platform')));
        }

        $this->participant_service->queue_new_registration_notification( $participant_id );
        
        wp_send_json_success(array(
            'message' => __('Registration submitted successfully! Your registration is pending approval.', 'volunteer-exchange-platform')
        ));
    }

    /**
     * Update participant via AJAX.
     *
     * @return void
     */
    public function update() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_frontend_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'volunteer-exchange-platform' ) ) );
        }

        $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
        $event_id       = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        $randon_key     = isset( $_POST['randon_key'] ) ? sanitize_text_field( wp_unslash( $_POST['randon_key'] ) ) : '';

        if ( ! $participant_id || ! $event_id ) {
            wp_send_json_error( array( 'message' => __( 'Please select a participant.', 'volunteer-exchange-platform' ) ) );
        }

        $participant = $this->participant_service->get_by_id( $participant_id );
        if ( ! $participant || (int) $participant->event_id !== (int) $event_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid participant selection.', 'volunteer-exchange-platform' ) ) );
        }

        if ( '' === $randon_key || ! isset( $participant->randon_key ) || (string) $participant->randon_key !== (string) $randon_key ) {
            wp_send_json_error( array( 'message' => __( 'Invalid participant selection.', 'volunteer-exchange-platform' ) ) );
        }

        $remove_logo = isset( $_POST['remove_logo'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['remove_logo'] ) );

        $logo_url  = null;
        $logo_name = isset( $_FILES['logo']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['logo']['name'] ) ) : '';
        if ( '' !== $logo_name ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_handle_upload expects raw $_FILES entry.
            $file      = $_FILES['logo'];
            $file_size = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
            $file_type = isset( $file['type'] ) ? sanitize_mime_type( $file['type'] ) : '';

            if ( $file_size > 2097152 ) {
                wp_send_json_error( array( 'message' => __( 'Logo file size must be less than 2MB.', 'volunteer-exchange-platform' ) ) );
            }

            $allowed_types = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif' );
            if ( ! in_array( $file_type, $allowed_types, true ) ) {
                wp_send_json_error( array( 'message' => __( 'Logo must be JPG, PNG, or GIF format.', 'volunteer-exchange-platform' ) ) );
            }

            $upload_overrides = array( 'test_form' => false );
            $movefile         = wp_handle_upload( $file, $upload_overrides );

            if ( $movefile && ! isset( $movefile['error'] ) ) {
                $logo_url = $movefile['url'];
            } else {
                wp_send_json_error( array( 'message' => __( 'Error uploading logo.', 'volunteer-exchange-platform' ) ) );
            }
        }

        $data = array(
            'organization_name'           => isset( $_POST['organization_name'] ) ? sanitize_text_field( wp_unslash( $_POST['organization_name'] ) ) : '',
            'participant_type_id'         => isset( $_POST['participant_type_id'] ) ? absint( wp_unslash( $_POST['participant_type_id'] ) ) : 0,
            'contact_person_name'         => isset( $_POST['contact_person_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_person_name'] ) ) : '',
            'contact_email'               => isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '',
            'contact_phone'               => isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '',
            'description'                 => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'expected_participants_names' => isset( $_POST['expected_participants_names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['expected_participants_names'] ) ) : '',
        );

        $expected_count_raw = isset( $_POST['expected_participants_count'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_participants_count'] ) ) : '';
        if ( '' === $expected_count_raw ) {
            $data['expected_participants_count'] = null;
        } else {
            $expected_count = absint( $expected_count_raw );
            $max_count = EmailSettings::max_participants_per_organization();
            
            if ( $expected_count < 1 ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'Participants Expected must be empty or at least 1.', 'volunteer-exchange-platform' ),
                    )
                );
            }

            if ($expected_count > $max_count ) {
                wp_send_json_error(
                    array(
                        'message' => sprintf(
                            /* translators: %d: max participants per organization */
                            __( 'Participants Expected must be empty or between 1 and %d.', 'volunteer-exchange-platform' ),
                            (int) $max_count
                        ),
                    )
                );
            }

            $data['expected_participants_count'] = $expected_count;
        }

        if ( null !== $logo_url ) {
            // A new file was uploaded — always takes priority over the remove flag.
            $data['logo_url'] = $logo_url;
        } elseif ( $remove_logo ) {
            $data['logo_url'] = '';
        }

        $updated = $this->participant_service->update_participant( $participant_id, $data );
        if ( false === $updated ) {
            wp_send_json_error( array( 'message' => __( 'Error updating participant. Please try again.', 'volunteer-exchange-platform' ) ) );
        }

        $tag_ids = isset( $_POST['tags'] ) && is_array( $_POST['tags'] ) ? array_map( 'absint', wp_unslash( $_POST['tags'] ) ) : array();
        if ( ! $this->participant_service->replace_tags( $participant_id, $tag_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Error updating participant tags. Please try again.', 'volunteer-exchange-platform' ) ) );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Participant updated successfully.', 'volunteer-exchange-platform' ),
            )
        );
    }

    /**
     * Get participant data for update form prefill.
     *
     * @return void
     */
    public function get_participant_for_update() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_frontend_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'volunteer-exchange-platform' ) ) );
        }

        $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
        $event_id       = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

        if ( ! $participant_id || ! $event_id ) {
            wp_send_json_error( array( 'message' => __( 'Please select a participant.', 'volunteer-exchange-platform' ) ) );
        }

        $participant = $this->participant_service->get_by_id( $participant_id );
        if ( ! $participant || (int) $participant->event_id !== (int) $event_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid participant selection.', 'volunteer-exchange-platform' ) ) );
        }

        $tag_ids = $this->participant_service->get_tag_ids( $participant_id );

        wp_send_json_success(
            array(
                'participant' => array(
                    'organization_name'           => isset( $participant->organization_name ) ? (string) $participant->organization_name : '',
                    'participant_type_id'         => isset( $participant->participant_type_id ) ? (int) $participant->participant_type_id : 0,
                    'contact_person_name'         => isset( $participant->contact_person_name ) ? (string) $participant->contact_person_name : '',
                    'contact_email'               => isset( $participant->contact_email ) ? (string) $participant->contact_email : '',
                    'contact_phone'               => isset( $participant->contact_phone ) ? (string) $participant->contact_phone : '',
                    'description'                 => isset( $participant->description ) ? (string) $participant->description : '',
                    'expected_participants_count' => isset( $participant->expected_participants_count ) ? $participant->expected_participants_count : null,
                    'expected_participants_names' => isset( $participant->expected_participants_names ) ? (string) $participant->expected_participants_names : '',
                    'tag_ids'                     => array_map( 'absint', is_array( $tag_ids ) ? $tag_ids : array() ),
                ),
            )
        );
    }
}
