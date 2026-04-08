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
        $api_key = \VolunteerExchangePlatform\Email\Settings::api_key();
        if ( '' === $api_key ) {
            return new \WP_Error( 'vep_email_missing_api_key', 'SMTP2GO API key is not configured.' );
        }

        // ── Guard: recipients ──────────────────────────────────────────
        $to = ( isset( $payload['to'] ) && is_array( $payload['to'] ) ) ? $payload['to'] : array();
        if ( empty( $to ) ) {
            return new \WP_Error( 'vep_email_missing_to', 'Transactional email has no recipients.' );
        }

        // ── Build SMTP2GO /v3/email/send request body ──────────────────
        // api_key goes in the body per SMTP2GO's canonical examples.
        $request_body = array(
            'api_key' => $api_key,
            'to'      => $to,
            'sender'  => ( isset( $payload['sender'] ) && '' !== $payload['sender'] )
                ? $payload['sender']
                : \VolunteerExchangePlatform\Email\Settings::sender(),
            'subject' => isset( $payload['subject'] ) ? (string) $payload['subject'] : '',
        );

        $template_id = isset( $payload['template_id'] ) ? (string) $payload['template_id'] : '';
        if ( '' !== $template_id ) {
            $request_body['template_id'] = $template_id;
        }

        if ( ! empty( $payload['template_data'] ) && is_array( $payload['template_data'] ) ) {
            $request_body['template_data'] = $payload['template_data'];
        }

        if ( ! empty( $payload['html_body'] ) ) {
            $request_body['html_body'] = (string) $payload['html_body'];
        }

        if ( ! empty( $payload['text_body'] ) ) {
            $request_body['text_body'] = (string) $payload['text_body'];
        }

        // ── POST to SMTP2GO send endpoint ──────────────────────────────
        // Uses /v3/email/send (standard API key permission) rather than
        // /v3/email/batch which requires a separate batch-sending permission.
        $endpoint = (string) apply_filters( 'vep_smtp2go_send_endpoint', 'https://api.smtp2go.com/v3/email/send' );

        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'accept'       => 'application/json',
                ),
                'body' => wp_json_encode( $request_body ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body_text   = (string) wp_remote_retrieve_body( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return new \WP_Error(
                'vep_email_http_failure',
                sprintf( 'SMTP2GO returned HTTP %d. Body: %s', $status_code, substr( $body_text, 0, 500 ) )
            );
        }

        $decoded = json_decode( $body_text, true );

        // ── Check API-level failures ───────────────────────────────────
        // /v3/email/send returns data.failed (int) and data.failures (array).
        if ( isset( $decoded['data']['failed'] ) && (int) $decoded['data']['failed'] > 0 ) {
            $failures = isset( $decoded['data']['failures'] ) && is_array( $decoded['data']['failures'] )
                ? $decoded['data']['failures']
                : array();
            $reason = isset( $failures[0]['error'] ) ? (string) $failures[0]['error'] : 'SMTP2GO reported delivery failure.';
            return new \WP_Error( 'vep_email_provider_failure', $reason );
        }

        // ── Extract message_id ─────────────────────────────────────────
        $provider_message_id = '';
        if ( isset( $decoded['data']['email_id'] ) ) {
            $provider_message_id = (string) $decoded['data']['email_id'];
        } elseif ( isset( $decoded['data']['transaction_id'] ) ) {
            $provider_message_id = (string) $decoded['data']['transaction_id'];
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
