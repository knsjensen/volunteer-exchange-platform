<?php
/**
 * Participant AJAX handler
 *
 * @package VEP
 * @subpackage Ajax
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Email\Settings;
use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantEmailService;
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
     * @var ParticipantEmailService
     */
    private $participant_email_service;

    /**
     * @var EventService
     */
    private $event_service;

    /**
     * Max registrations per IP within the throttle window.
     */
    const REGISTRATION_LIMIT = 20;

    /**
     * Throttle window in seconds.
     */
    const REGISTRATION_WINDOW = 600;

    /**
     * Constructor.
     *
     * @param ParticipantService|null $participant_service Participant service instance.
     * @return void
     */
    public function __construct( ?ParticipantService $participant_service = null, ?ParticipantEmailService $participant_email_service = null, ?EventService $event_service = null ) {
        $this->participant_service       = $participant_service ?: new ParticipantService();
        $this->participant_email_service = $participant_email_service ?: new ParticipantEmailService( $this->participant_service );
        $this->event_service             = $event_service ?: new EventService();
        add_action('wp_ajax_vep_register_participant', array($this, 'register'));
        add_action('wp_ajax_nopriv_vep_register_participant', array($this, 'register'));
        add_action('wp_ajax_vep_update_participant', array($this, 'update'));
        add_action('wp_ajax_nopriv_vep_update_participant', array($this, 'update'));
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
        
        // Honeypot: real users never fill this hidden field. Pretend success so bots learn nothing.
        $honeypot = isset( $_POST['vep_hp_field'] ) ? trim( (string) wp_unslash( $_POST['vep_hp_field'] ) ) : '';
        if ( '' !== $honeypot ) {
            wp_send_json_success(array(
                'message' => __('Registration submitted successfully! Your registration is pending approval.', 'volunteer-exchange-platform')
            ));
        }

        // Per-IP throttle.
        $throttle_key = $this->get_registration_throttle_key();
        if ( (int) get_transient( $throttle_key ) >= self::REGISTRATION_LIMIT ) {
            wp_send_json_error(array('message' => __('Too many registrations from your connection. Please try again later.', 'volunteer-exchange-platform')));
        }

        // Validate required fields
        if ( '' === $organization_name || ! $participant_type_id || '' === $contact_person_name || ! $event_id ) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', 'volunteer-exchange-platform')));
        }

        $contact_email = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
        if ( '' === $contact_email || ! is_email( $contact_email ) ) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'volunteer-exchange-platform')));
        }

        // The event must be the active event and must not have ended (event dates are WP-local time).
        $active_event = $this->event_service->get_active_event();
        if ( ! $active_event || (int) $active_event->id !== (int) $event_id ) {
            wp_send_json_error(array('message' => __('Registration is not open for this event.', 'volunteer-exchange-platform')));
        }
        $event_end_ts = $this->local_datetime_to_timestamp( isset( $active_event->end_date ) ? (string) $active_event->end_date : '' );
        if ( $event_end_ts && time() > $event_end_ts ) {
            wp_send_json_error(array('message' => __('Registration is not open for this event.', 'volunteer-exchange-platform')));
        }
        
        // Handle logo upload
        $logo_url = '';
        $logo_name = isset( $_FILES['logo']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['logo']['name'] ) ) : '';
        if ( '' !== $logo_name ) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_handle_upload expects the raw $_FILES entry.
            $file     = $_FILES['logo'];
            $logo_url = $this->handle_logo_upload( $file );
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
            'contact_email' => $contact_email,
            'contact_phone' => isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '',
            'logo_url' => $logo_url,
            'is_approved' => 0 // Awaiting approval
        );
        $tag_ids = $tags;

        $participant_id = $this->participant_service->create_with_tags($data, $tag_ids);

        if ( false === $participant_id ) {
            wp_send_json_error(array('message' => __('Error saving registration. Please try again.', 'volunteer-exchange-platform')));
        }

        set_transient( $throttle_key, (int) get_transient( $throttle_key ) + 1, self::REGISTRATION_WINDOW );

        $this->participant_email_service->queue_new_registration_notification( $participant_id );
        
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
            $file     = $_FILES['logo'];
            $logo_url = $this->handle_logo_upload( $file );
        }

        $data = array(
            'organization_name'           => isset( $_POST['organization_name'] ) ? sanitize_text_field( wp_unslash( $_POST['organization_name'] ) ) : '',
            'participant_type_id'         => isset( $_POST['participant_type_id'] ) ? absint( wp_unslash( $_POST['participant_type_id'] ) ) : 0,
            'contact_person_name'         => isset( $_POST['contact_person_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_person_name'] ) ) : '',
            'contact_email'               => isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '',
            'contact_phone'               => isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '',
            'description'                 => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'expected_participants_names' => ( isset( $_POST['expected_participants_names'] ) && is_array( $_POST['expected_participants_names'] ) )
                ? $this->encode_names_list( $_POST['expected_participants_names'] )
                : ( isset( $_POST['expected_participants_names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['expected_participants_names'] ) ) : '' ),
            'link'                        => isset( $_POST['link'] ) ? esc_url_raw( wp_unslash( $_POST['link'] ) ) : '',
        );

        $expected_count_raw = isset( $_POST['expected_participants_count'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_participants_count'] ) ) : '';
        if ( '' === $expected_count_raw ) {
            $data['expected_participants_count'] = null;
        } else {
            $expected_count = absint( $expected_count_raw );
            $max_count = Settings::max_participants_per_organization();
            
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

        $data['no_logo']           = isset( $_POST['no_logo'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['no_logo'] ) ) ? 1 : 0;
        $data['no_link']           = isset( $_POST['no_link'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['no_link'] ) ) ? 1 : 0;
        $data['no_expected_count'] = isset( $_POST['no_expected_count'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['no_expected_count'] ) ) ? 1 : 0;
        $data['no_expected_names'] = isset( $_POST['no_expected_names'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['no_expected_names'] ) ) ? 1 : 0;

        if ( $data['no_logo'] ) {
            // "Has no logo" — clear any existing logo regardless of file upload or remove flag.
            $data['logo_url'] = '';
        } elseif ( null !== $logo_url ) {
            // A new file was uploaded — always takes priority over the remove flag.
            $data['logo_url'] = $logo_url;
        } elseif ( $remove_logo ) {
            $data['logo_url'] = '';
        }

        if ( $data['no_link'] ) {
            $data['link'] = '';
        }

        if ( $data['no_expected_count'] ) {
            $data['expected_participants_count'] = null;
        }

        if ( $data['no_expected_names'] ) {
            $data['expected_participants_names'] = '';
        }

        $updated = $this->participant_service->update_participant( $participant_id, $data );
        if ( false === $updated ) {
            // The new upload is orphaned; remove it.
            if ( null !== $logo_url ) {
                $this->delete_uploaded_logo( $logo_url, 0 );
            }
            wp_send_json_error( array( 'message' => __( 'Error updating participant. Please try again.', 'volunteer-exchange-platform' ) ) );
        }

        // Remove the previous logo file when it was replaced or cleared.
        if ( isset( $data['logo_url'] ) && ! empty( $participant->logo_url ) && (string) $data['logo_url'] !== (string) $participant->logo_url ) {
            $this->delete_uploaded_logo( (string) $participant->logo_url, $participant_id );
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
     * Encode a submitted list of names as JSON. An empty list is stored as an empty string.
     *
     * @param array $raw Raw submitted names.
     * @return string
     */
    private function encode_names_list( $raw ) {
        $names = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'wp_unslash', $raw ) ), 'strlen' ) );

        return empty( $names ) ? '' : (string) wp_json_encode( $names );
    }

    /**
     * Convert a WP-local datetime string to a Unix timestamp.
     *
     * @param string $datetime Local datetime (Y-m-d H:i:s).
     * @return int 0 when empty/invalid.
     */
    private function local_datetime_to_timestamp( $datetime ) {
        $datetime = trim( $datetime );
        if ( '' === $datetime ) {
            return 0;
        }

        try {
            $date = new \DateTimeImmutable( $datetime, wp_timezone() );
        } catch ( \Exception $e ) {
            return 0;
        }

        return $date->getTimestamp();
    }

    /**
     * Transient key used to throttle registrations per client IP.
     *
     * @return string
     */
    private function get_registration_throttle_key() {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        return 'vep_reg_' . md5( $ip );
    }

    /**
     * Allowed logo mime types (extension pattern => mime type).
     *
     * @return array
     */
    private function get_allowed_logo_mimes() {
        return array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
        );
    }

    /**
     * Validate and store an uploaded logo. Sends a JSON error and exits on failure.
     *
     * The client supplied MIME type is never trusted: the file extension and content are verified.
     *
     * @param array $file Raw $_FILES entry.
     * @return string Public URL of the stored logo.
     */
    private function handle_logo_upload( $file ) {
        $file_size = isset( $file['size'] ) ? absint( $file['size'] ) : 0;

        // Validate file size (2MB max).
        if ( $file_size > 2097152 ) {
            wp_send_json_error( array( 'message' => __( 'Logo file size must be less than 2MB.', 'volunteer-exchange-platform' ) ) );
        }

        $mimes     = $this->get_allowed_logo_mimes();
        $tmp_name  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
        $file_name = isset( $file['name'] ) ? (string) $file['name'] : '';

        $checked = wp_check_filetype_and_ext( $tmp_name, $file_name, $mimes );
        if ( empty( $checked['ext'] ) || empty( $checked['type'] ) || ! in_array( $checked['type'], $mimes, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Logo must be JPG, PNG, GIF, or WebP format.', 'volunteer-exchange-platform' ) ) );
        }

        $image_info = ( '' !== $tmp_name && is_uploaded_file( $tmp_name ) ) ? @getimagesize( $tmp_name ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ( ! $image_info || empty( $image_info['mime'] ) || ! in_array( $image_info['mime'], $mimes, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Logo must be JPG, PNG, GIF, or WebP format.', 'volunteer-exchange-platform' ) ) );
        }

        $movefile = wp_handle_upload(
            $file,
            array(
                'test_form' => false,
                'mimes'     => $mimes,
            )
        );

        if ( ! $movefile || isset( $movefile['error'] ) || empty( $movefile['url'] ) || empty( $movefile['file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Error uploading logo.', 'volunteer-exchange-platform' ) ) );
        }

        // Re-verify the stored file's actual content.
        $stored_info = @getimagesize( $movefile['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ( ! $stored_info || empty( $stored_info['mime'] ) || ! in_array( $stored_info['mime'], $mimes, true ) ) {
            wp_delete_file( $movefile['file'] );
            wp_send_json_error( array( 'message' => __( 'Logo must be JPG, PNG, GIF, or WebP format.', 'volunteer-exchange-platform' ) ) );
        }

        return (string) $movefile['url'];
    }

    /**
     * Delete a previously uploaded logo file, conservatively.
     *
     * Only deletes when the URL points to a file inside the uploads directory, the file is
     * not a media library attachment, and no other participant still uses the same URL.
     *
     * @param string $logo_url       Logo URL.
     * @param int    $participant_id Participant that owned the logo (excluded from the "in use" check).
     * @return void
     */
    private function delete_uploaded_logo( $logo_url, $participant_id ) {
        global $wpdb;

        $logo_url = trim( (string) $logo_url );
        if ( '' === $logo_url ) {
            return;
        }

        $uploads = wp_get_upload_dir();
        if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
            return;
        }

        $base_url = set_url_scheme( $uploads['baseurl'], 'http' );
        $url      = set_url_scheme( $logo_url, 'http' );
        if ( 0 !== strpos( $url, trailingslashit( $base_url ) ) ) {
            return;
        }

        // Media library files are managed elsewhere.
        if ( function_exists( 'attachment_url_to_postid' ) && attachment_url_to_postid( $logo_url ) ) {
            return;
        }

        // Another participant may still reference the same file.
        $table = $wpdb->prefix . 'vep_participants';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Controlled table name.
        $in_use = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE logo_url = %s AND id <> %d", $logo_url, (int) $participant_id ) );
        if ( $in_use > 0 ) {
            return;
        }

        $relative = ltrim( substr( $url, strlen( trailingslashit( $base_url ) ) - 1 ), '/' );
        $path     = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . $relative );
        $real     = realpath( $path );
        $real_dir = realpath( $uploads['basedir'] );
        if ( ! $real || ! $real_dir ) {
            return;
        }

        $real     = wp_normalize_path( $real );
        $real_dir = trailingslashit( wp_normalize_path( $real_dir ) );
        if ( 0 !== strpos( $real, $real_dir ) || ! is_file( $real ) ) {
            return;
        }

        wp_delete_file( $real );
    }
}
