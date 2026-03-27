<?php
/**
 * Competitions admin page
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Competitions admin page.
 *
 * Placeholder page for competition management. Full create/edit functionality
 * will be added in a future iteration.
 */
class CompetitionsPage {
    /**
     * Render competitions page.
     *
     * @return void
     */
    public function render() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Competitions', 'volunteer-exchange-platform' ); ?></h1>

            <div class="notice notice-info">
                <p><?php esc_html_e( 'Competition functionality will be added later.', 'volunteer-exchange-platform' ); ?></p>
            </div>

            <h2><?php esc_html_e( 'Create Competition', 'volunteer-exchange-platform' ); ?></h2>
            <p><?php esc_html_e( 'The create competition flow is planned and will be implemented in a future update.', 'volunteer-exchange-platform' ); ?></p>
        </div>
        <?php
    }
}
