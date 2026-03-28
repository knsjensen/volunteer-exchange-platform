<?php
/**
 * Email log cleanup worker.
 *
 * Runs daily via WP-Cron and purges transactional email records older
 * than the configured retention period.
 *
 * @package VEP
 * @subpackage Email
 */

namespace VolunteerExchangePlatform\Email;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmailCleanupWorker {
    const HOOK = 'vep_email_cleanup_run';

    /**
     * @var EmailQueueRepository
     */
    private $repository;

    public function __construct( ?EmailQueueRepository $repository = null ) {
        $this->repository = $repository ? $repository : new EmailQueueRepository();

        add_action( 'init', array( $this, 'ensure_schedule' ) );
        add_action( self::HOOK, array( $this, 'run_cleanup' ) );
    }

    /**
     * Ensure the daily cleanup cron event is scheduled.
     *
     * @return void
     */
    public function ensure_schedule() {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 300, 'daily', self::HOOK );
        }
    }

    /**
     * Delete email log rows that exceed the configured retention period.
     *
     * @return void
     */
    public function run_cleanup() {
        $days = EmailSettings::log_retention_days();

        if ( $days <= 0 ) {
            // 0 means "never delete".
            return;
        }

        $this->repository->delete_older_than_days( $days );
    }

    /**
     * Unschedule the cleanup cron event on plugin deactivation.
     *
     * @return void
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled( self::HOOK );
        while ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
            $timestamp = wp_next_scheduled( self::HOOK );
        }
    }
}
