<?php
/**
 * Background worker for transactional emails.
 *
 * @package VEP
 * @subpackage Email
 */

namespace VolunteerExchangePlatform\Email;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TransactionalEmailWorker {
    /**
     * @var EmailQueueRepository
     */
    private $queue_repository;

    public function __construct( ?\VolunteerExchangePlatform\Email\EmailQueueRepository $queue_repository = null ) {
        $this->queue_repository = $queue_repository ? $queue_repository : new \VolunteerExchangePlatform\Email\EmailQueueRepository();

        add_filter( 'cron_schedules', array( $this, 'register_cron_interval' ) );
        add_action( 'init', array( $this, 'ensure_schedule' ) );
        add_action( 'vep_process_transactional_email_queue', array( $this, 'process_queue' ) );
    }

    /**
     * Register one-minute schedule used by the queue worker.
     *
     * @param array $schedules Existing schedules.
     * @return array
     */
    public function register_cron_interval( $schedules ) {
        if ( ! isset( $schedules['vep_every_minute'] ) ) {
            $schedules['vep_every_minute'] = array(
                'interval' => 60,
                'display'  => __( 'Every Minute (VEP)', 'volunteer-exchange-platform' ),
            );
        }

        return $schedules;
    }

    /**
     * Ensure recurring worker event exists.
     *
     * @return void
     */
    public function ensure_schedule() {
        if ( ! wp_next_scheduled( 'vep_process_transactional_email_queue' ) ) {
            wp_schedule_event( time() + 60, 'vep_every_minute', 'vep_process_transactional_email_queue' );
        }
    }

    /**
     * Process a limited batch from queue.
     *
     * @return void
     */
    public function process_queue() {
        $max_jobs = (int) apply_filters( 'vep_transactional_email_max_jobs_per_run', 5 );

        for ( $i = 0; $i < $max_jobs; $i++ ) {
            $job = $this->queue_repository->claim_next();

            if ( ! $job ) {
                break;
            }

            $result = $this->send_job( $job );

            if ( is_wp_error( $result ) ) {
                $this->queue_repository->mark_failed(
                    (int) $job->id,
                    (int) $job->attempts,
                    (int) $job->max_attempts,
                    $result->get_error_message()
                );
                continue;
            }

            $provider_message_id = isset( $result['provider_message_id'] ) ? (string) $result['provider_message_id'] : '';
            $this->queue_repository->mark_sent( (int) $job->id, $provider_message_id );
        }
    }

    /**
     * Send a queued email via SMTP2GO batch API.
     *
     * @param object $job Queued job row.
     * @return array|\WP_Error
     */
    private function send_job( $job ) {
        $payload = json_decode( (string) $job->payload, true );
        if ( ! is_array( $payload ) ) {
            return new \WP_Error( 'vep_email_invalid_payload', 'Invalid transactional email payload.' );
        }

        // ── Guard: API key ─────────────────────────────────────────────
        $api_key = \VolunteerExchangePlatform\Email\EmailSettings::api_key();
        if ( '' === $api_key ) {
            return new \WP_Error( 'vep_email_missing_api_key', 'SMTP2GO API key is not configured.' );
        }

        // ── Guard: recipients ──────────────────────────────────────────
        $to = ( isset( $payload['to'] ) && is_array( $payload['to'] ) ) ? $payload['to'] : array();
        if ( empty( $to ) ) {
            return new \WP_Error( 'vep_email_missing_to', 'Transactional email has no recipients.' );
        }

        // ── Build SMTP2GO email object ─────────────────────────────────
        $email = array(
            'to'      => $to,
            'sender'  => ( isset( $payload['sender'] ) && '' !== $payload['sender'] )
                ? $payload['sender']
                : \VolunteerExchangePlatform\Email\EmailSettings::sender(),
            'subject' => isset( $payload['subject'] ) ? (string) $payload['subject'] : '',
        );

        $template_id = isset( $payload['template_id'] ) ? (string) $payload['template_id'] : '';
        if ( '' !== $template_id ) {
            $email['template_id'] = $template_id;
        }

        if ( ! empty( $payload['template_data'] ) && is_array( $payload['template_data'] ) ) {
            $email['template_data'] = $payload['template_data'];
        }

        if ( ! empty( $payload['html_body'] ) ) {
            $email['html_body'] = (string) $payload['html_body'];
        }

        if ( ! empty( $payload['text_body'] ) ) {
            $email['text_body'] = (string) $payload['text_body'];
        }

        // ── POST to SMTP2GO batch endpoint ─────────────────────────────
        $endpoint = (string) apply_filters( 'vep_smtp2go_batch_endpoint', 'https://api.smtp2go.com/v3/email/batch' );

        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Content-Type'      => 'application/json',
                    'accept'            => 'application/json',
                    'X-Smtp2go-Api-Key' => $api_key,
                ),
                'body' => wp_json_encode( array( 'emails' => array( $email ) ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body        = (string) wp_remote_retrieve_body( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return new \WP_Error(
                'vep_email_http_failure',
                sprintf( 'SMTP2GO returned HTTP %d. Body: %s', $status_code, substr( $body, 0, 500 ) )
            );
        }

        $decoded = json_decode( $body, true );

        // ── Check API-level failure ────────────────────────────────────
        $failed = ( isset( $decoded['data']['failed'] ) && is_array( $decoded['data']['failed'] ) )
            ? $decoded['data']['failed']
            : array();

        if ( ! empty( $failed ) ) {
            $reason = isset( $failed[0]['error'] ) ? (string) $failed[0]['error'] : 'SMTP2GO reported delivery failure.';
            return new \WP_Error( 'vep_email_provider_failure', $reason );
        }

        // ── Extract message_id ─────────────────────────────────────────
        $provider_message_id = '';
        if ( isset( $decoded['data']['succeeded'][0]['message_id'] ) ) {
            $provider_message_id = (string) $decoded['data']['succeeded'][0]['message_id'];
        }

        return array( 'provider_message_id' => $provider_message_id );
    }

    /**
     * Unschedule worker cron event.
     *
     * @return void
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled( 'vep_process_transactional_email_queue' );

        while ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'vep_process_transactional_email_queue' );
            $timestamp = wp_next_scheduled( 'vep_process_transactional_email_queue' );
        }
    }
}
