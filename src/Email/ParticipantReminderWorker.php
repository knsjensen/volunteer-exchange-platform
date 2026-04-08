<?php
/**
 * Automatic participant reminder worker.
 *
 * @package VEP
 * @subpackage Email
 */

namespace VolunteerExchangePlatform\Email;

use VolunteerExchangePlatform\Services\ParticipantService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParticipantReminderWorker {
    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * @var \wpdb
     */
    private $wpdb;

    public function __construct( ?ParticipantService $participant_service = null, ?\wpdb $wpdb_instance = null ) {
        global $wpdb;

        $this->participant_service = $participant_service ? $participant_service : new ParticipantService();
        $this->wpdb = $wpdb_instance ? $wpdb_instance : $wpdb;

        add_action( 'init', array( $this, 'ensure_schedule' ) );
        add_action( 'vep_send_participant_update_reminders', array( $this, 'process_reminders' ) );
    }

    /**
     * Ensure daily reminder cron is registered.
     *
     * @return void
     */
    public function ensure_schedule() {
        if ( ! wp_next_scheduled( 'vep_send_participant_update_reminders' ) ) {
            wp_schedule_event( time() + 300, 'daily', 'vep_send_participant_update_reminders' );
        }
    }

    /**
     * Process automatic reminders (1 month, 14 days, 7 days before event).
     *
     * @return void
     */
    public function process_reminders() {
        $today = current_datetime();

        $targets = array(
            '21d'  => $today->modify( '+1 month' )->format( 'Y-m-d' ),
            '14d' => $today->modify( '+14 days' )->format( 'Y-m-d' ),
            '7d'  => $today->modify( '+7 days' )->format( 'Y-m-d' ),
        );

        foreach ( $targets as $reminder_type => $target_date ) {
            $participants = $this->participant_service->get_reminder_candidates_by_event_date( $target_date );

            foreach ( $participants as $participant ) {
                $participant_id = isset( $participant->id ) ? (int) $participant->id : 0;
                if ( $participant_id <= 0 ) {
                    continue;
                }

                if ( $this->has_sent_reminder( $participant_id, $reminder_type ) ) {
                    continue;
                }

                $queued = $this->participant_service->queue_update_participant_reminder( $participant_id );
                if ( $queued ) {
                    $this->mark_reminder_sent( $participant_id, $reminder_type );
                }
            }
        }
    }

    /**
     * Check whether this reminder type was already sent for participant.
     *
     * @param int    $participant_id Participant ID.
     * @param string $reminder_type Reminder type key.
     * @return bool
     */
    private function has_sent_reminder( $participant_id, $reminder_type ) {
        $table = $this->wpdb->prefix . 'vep_participant_reminders';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is controlled from $wpdb->prefix.
        $sql = "SELECT 1 FROM {$table} WHERE participant_id = %d AND reminder_type = %s LIMIT 1";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared before execution.
        $prepared = $this->wpdb->prepare( $sql, $participant_id, $reminder_type );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared SQL query with scalar result.
        return (bool) $this->wpdb->get_var( $prepared );
    }

    /**
     * Mark reminder type as sent for participant.
     *
     * @param int    $participant_id Participant ID.
     * @param string $reminder_type Reminder type key.
     * @return void
     */
    private function mark_reminder_sent( $participant_id, $reminder_type ) {
        $table = $this->wpdb->prefix . 'vep_participant_reminders';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for idempotent upsert marker.
        $this->wpdb->replace(
            $table,
            array(
                'participant_id' => (int) $participant_id,
                'reminder_type'  => (string) $reminder_type,
                'sent_at'        => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s' )
        );
    }

    /**
     * Unschedule reminder cron event.
     *
     * @return void
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled( 'vep_send_participant_update_reminders' );

        while ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'vep_send_participant_update_reminders' );
            $timestamp = wp_next_scheduled( 'vep_send_participant_update_reminders' );
        }
    }
}
