<?php
/**
 * Plugin deactivator
 *
 * @package VEP
 * @subpackage Plugin
 */

namespace VolunteerExchangePlatform\Plugin;

use VolunteerExchangePlatform\Email\EmailCleanupWorker;
use VolunteerExchangePlatform\Email\TransactionalEmailWorker;
use VolunteerExchangePlatform\Email\ParticipantReminderWorker;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {
    /**
     * Run plugin deactivation tasks.
     *
     * @param bool $network_wide Whether plugin is deactivated network-wide.
     * @return void
     */
    public static function deactivate( $network_wide = false ) {
        if ( is_multisite() && $network_wide ) {
            self::deactivate_network_wide();
            return;
        }

        self::deactivate_for_current_site();
    }

    /**
     * Deactivate plugin for current site.
     *
     * @return void
     */
    private static function deactivate_for_current_site() {
        delete_transient( 'vep_admin_notices' );
        TransactionalEmailWorker::unschedule();
        ParticipantReminderWorker::unschedule();
        EmailCleanupWorker::unschedule();
        flush_rewrite_rules();
    }

    /**
     * Deactivate plugin for all sites in network.
     *
     * @return void
     */
    private static function deactivate_network_wide() {
        $site_ids = get_sites(
            array(
                'fields' => 'ids',
                'number' => 0,
            )
        );

        foreach ( $site_ids as $site_id ) {
            switch_to_blog( (int) $site_id );
            self::deactivate_for_current_site();
            restore_current_blog();
        }
    }
}
