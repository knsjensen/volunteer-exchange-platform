<?php
/**
 * Transactional email enqueue service.
 *
 * @package VEP
 * @subpackage Email
 */

namespace VolunteerExchangePlatform\Email;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TransactionalEmailService {
    /**
     * @var EmailQueueRepository
     */
    private $queue_repository;

    public function __construct( ?\VolunteerExchangePlatform\Email\EmailQueueRepository $queue_repository = null, $register_hook = true ) {
        $this->queue_repository = $queue_repository ? $queue_repository : new \VolunteerExchangePlatform\Email\EmailQueueRepository();

        if ( $register_hook ) {
            add_action( 'vep_enqueue_transactional_email', array( $this, 'enqueue' ), 10, 1 );
        }
    }

    /**
     * Enqueue transactional email payload.
     *
     * Accepted keys in $message:
     *   to           string|string[]  Required. Recipient(s) as "Name <email>" or plain email.
     *   subject      string           Required unless template_key provides a default subject.
     *   template_key string           Optional. Internal profile key defined in Email Settings.
     *   template_data array           Optional. Key-value pairs forwarded to SMTP2GO template.
     *   sender       string           Optional. Overrides global sender setting.
     *   html_body    string           Optional. Overrides profile default.
     *   text_body    string           Optional. Overrides profile default.
     *
     * @param array $message Message payload.
     * @return int|false Queue row ID on success, false on validation failure.
     */
    public function enqueue( $message ) {
        if ( ! is_array( $message ) ) {
            return false;
        }

        // ── Normalise `to` to an array of strings ──────────────────────
        $raw_to = isset( $message['to'] ) ? $message['to'] : array();
        $to     = $this->normalise_recipients( $raw_to );

        if ( empty( $to ) ) {
            return false;
        }

        // ── Template profile (optional) ────────────────────────────────
        $template_key      = isset( $message['template_key'] ) ? sanitize_key( $message['template_key'] ) : '';
        $template_id       = '';
        $allowed_data_keys = array();
        $profile_subject   = '';
        $profile_html_body = '';
        $profile_text_body = '';

        if ( '' !== $template_key ) {
            $profile = \VolunteerExchangePlatform\Email\EmailSettings::get_profile( $template_key );
            if ( null === $profile ) {
                return false;
            }
            $template_id       = isset( $profile['template_id'] )       ? (string) $profile['template_id']       : '';
            $allowed_data_keys = isset( $profile['allowed_data_keys'] ) ? (array)  $profile['allowed_data_keys'] : array();
            $profile_subject   = isset( $profile['default_subject'] )   ? (string) $profile['default_subject']   : '';
            $profile_html_body = isset( $profile['default_html_body'] ) ? (string) $profile['default_html_body'] : '';
            $profile_text_body = isset( $profile['default_text_body'] ) ? (string) $profile['default_text_body'] : '';
        }

        // ── Subject ────────────────────────────────────────────────────
        $subject = isset( $message['subject'] ) ? sanitize_text_field( $message['subject'] ) : $profile_subject;
        if ( empty( $subject ) ) {
            return false;
        }

        // ── template_data — filter to allowed keys only ────────────────
        $raw_template_data = ( isset( $message['template_data'] ) && is_array( $message['template_data'] ) )
            ? $message['template_data']
            : array();

        $template_data = array();
        if ( ! empty( $allowed_data_keys ) ) {
            foreach ( $allowed_data_keys as $dk ) {
                if ( array_key_exists( $dk, $raw_template_data ) ) {
                    $template_data[ $dk ] = $raw_template_data[ $dk ];
                }
            }
        } elseif ( ! empty( $raw_template_data ) && '' === $template_key ) {
            $template_data = $raw_template_data;
        }

        // ── Sender ─────────────────────────────────────────────────────
        $sender = ( isset( $message['sender'] ) && '' !== $message['sender'] )
            ? sanitize_text_field( $message['sender'] )
            : \VolunteerExchangePlatform\Email\EmailSettings::sender();

        // ── Body ───────────────────────────────────────────────────────
        $html_body = isset( $message['html_body'] ) ? (string) $message['html_body'] : $profile_html_body;
        $text_body = isset( $message['text_body'] ) ? (string) $message['text_body'] : $profile_text_body;

        // ── Build stored payload ───────────────────────────────────────
        $payload = array(
            'to'            => $to,
            'sender'        => $sender,
            'subject'       => $subject,
            'template_key'  => $template_key,
            'template_id'   => $template_id,
            'template_data' => $template_data,
            'html_body'     => $html_body,
            'text_body'     => $text_body,
        );

        return $this->queue_repository->enqueue( $payload );
    }

    /**
     * Normalise recipients to an array of non-empty strings.
     *
     * @param string|array $raw Raw recipient value.
     * @return string[]
     */
    private function normalise_recipients( $raw ) {
        if ( is_string( $raw ) ) {
            $raw = array( $raw );
        }

        if ( ! is_array( $raw ) ) {
            return array();
        }

        $out = array();
        foreach ( $raw as $entry ) {
            $entry = trim( (string) $entry );
            if ( '' !== $entry ) {
                $out[] = $entry;
            }
        }

        return $out;
    }
}
