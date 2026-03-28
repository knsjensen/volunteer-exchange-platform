<?php
/**
 * Email queue repository.
 *
 * @package VEP
 * @subpackage Email
 */

namespace VolunteerExchangePlatform\Email;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmailQueueRepository {
    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $table;

    public function __construct( ?\wpdb $wpdb_instance = null ) {
        global $wpdb;

        $this->wpdb = $wpdb_instance ? $wpdb_instance : $wpdb;
        $this->table = $this->wpdb->prefix . 'vep_transactional_emails';
    }

    /**
     * Add a new message to the queue.
     *
     * @param array $message Message payload.
     * @return int|false Inserted row ID on success.
     */
    public function enqueue( array $message ) {
        $scheduled_at = ! empty( $message['scheduled_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $message['scheduled_at'] ) : gmdate( 'Y-m-d H:i:s' );
        $max_attempts = isset( $message['max_attempts'] ) ? max( 1, (int) $message['max_attempts'] ) : 3;

        $to = array();
        if ( isset( $message['to'] ) && is_array( $message['to'] ) ) {
            $to = array_values( array_filter( array_map( 'strval', $message['to'] ) ) );
        } elseif ( isset( $message['to'] ) && '' !== trim( (string) $message['to'] ) ) {
            $to = array( (string) $message['to'] );
        }

        $payload = array(
            'to'           => $to,
            'sender'       => isset( $message['sender'] ) ? (string) $message['sender'] : '',
            'subject'      => isset( $message['subject'] ) ? (string) $message['subject'] : '',
            'html_body'    => isset( $message['html_body'] ) ? (string) $message['html_body'] : '',
            'text_body'    => isset( $message['text_body'] ) ? (string) $message['text_body'] : '',
            'template_key' => isset( $message['template_key'] ) ? (string) $message['template_key'] : '',
            'template_id'  => isset( $message['template_id'] ) ? (string) $message['template_id'] : '',
            'template_data'=> isset( $message['template_data'] ) && is_array( $message['template_data'] ) ? $message['template_data'] : array(),
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Queue writes are core plugin behavior.
        $inserted = $this->wpdb->insert(
            $this->table,
            array(
                'status'       => 'pending',
                'attempts'     => 0,
                'max_attempts' => $max_attempts,
                'scheduled_at' => $scheduled_at,
                'payload'      => wp_json_encode( $payload ),
                'created_at'   => gmdate( 'Y-m-d H:i:s' ),
                'updated_at'   => gmdate( 'Y-m-d H:i:s' ),
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Claim one pending job for processing.
     *
     * @return object|null
     */
    public function claim_next() {
        $token = wp_generate_uuid4();
        $now = gmdate( 'Y-m-d H:i:s' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Controlled table name.
        $updated = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table}
                 SET status = 'processing',
                     attempts = attempts + 1,
                     lock_token = %s,
                     locked_at = %s,
                     updated_at = %s
                 WHERE status = 'pending'
                   AND scheduled_at <= %s
                   AND attempts < max_attempts
                 ORDER BY id ASC
                 LIMIT 1",
                $token,
                $now,
                $now,
                $now
            )
        );

        if ( empty( $updated ) ) {
            return null;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Queue read is core plugin behavior.
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE lock_token = %s LIMIT 1",
                $token
            )
        );

        return $row ? $row : null;
    }

    /**
     * Mark job as sent.
     *
     * @param int $id Job ID.
     * @param string $provider_message_id Provider-side message ID.
     * @return void
     */
    public function mark_sent( $id, $provider_message_id = '' ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Queue write is core plugin behavior.
        $this->wpdb->update(
            $this->table,
            array(
                'status'              => 'sent',
                'provider_message_id' => (string) $provider_message_id,
                'last_error'          => null,
                'lock_token'          => null,
                'locked_at'           => null,
                'sent_at'             => gmdate( 'Y-m-d H:i:s' ),
                'updated_at'          => gmdate( 'Y-m-d H:i:s' ),
            ),
            array( 'id' => (int) $id ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Delete email log rows older than the given number of days.
     *
     * @param int $days Minimum age in days. Must be ≥ 1.
     * @return int|false Number of rows deleted, or false on error.
     */
    public function delete_older_than_days( $days ) {
        $days = max( 1, (int) $days );
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentional batch cleanup operation.
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} WHERE created_at < %s",
                $cutoff
            )
        );

        return $result;
    }

    /**
     * Mark job as failed and schedule retry if attempts remain.
     *
     * @param int $id Job ID.
     * @param int $attempts Attempt count after processing.
     * @param int $max_attempts Maximum attempts.
     * @param string $error_message Error details.
     * @return void
     */
    public function mark_failed( $id, $attempts, $max_attempts, $error_message ) {
        $attempts = (int) $attempts;
        $max_attempts = (int) $max_attempts;

        $is_terminal = $attempts >= $max_attempts;
        $status = $is_terminal ? 'failed' : 'pending';

        $delay_seconds = min( 300, (int) pow( 2, max( 1, $attempts ) ) * 10 );
        $scheduled_at = gmdate( 'Y-m-d H:i:s', time() + $delay_seconds );

        $data = array(
            'status'      => $status,
            'last_error'  => (string) $error_message,
            'lock_token'  => null,
            'locked_at'   => null,
            'updated_at'  => gmdate( 'Y-m-d H:i:s' ),
        );
        $format = array( '%s', '%s', '%s', '%s', '%s' );

        if ( ! $is_terminal ) {
            $data['scheduled_at'] = $scheduled_at;
            $format[] = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Queue write is core plugin behavior.
        $this->wpdb->update(
            $this->table,
            $data,
            array( 'id' => (int) $id ),
            $format,
            array( '%d' )
        );
    }
}
