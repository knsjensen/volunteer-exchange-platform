<?php
/**
 * Settings admin page.
 *
 * @package VEP
 * @subpackage Admin
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Admin\ParticipantTypesPage;
use VolunteerExchangePlatform\Admin\TagsPage;
use VolunteerExchangePlatform\Email\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettingsPage {
    /**
     * @var ParticipantTypesPage
     */
    private ParticipantTypesPage $participant_types_page;

    /**
     * @var TagsPage
     */
    private TagsPage $tags_page;

    public function __construct( ?ParticipantTypesPage $participant_types_page = null, ?TagsPage $tags_page = null ) {
        $this->participant_types_page = $participant_types_page ?: new ParticipantTypesPage();
        $this->tags_page = $tags_page ?: new TagsPage();
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_vep_get_settings_tab', array( $this, 'get_tab_ajax' ) );
        add_action( 'wp_ajax_vep_get_emails_table', array( $this, 'get_emails_table_ajax' ) );
    }

    /**
     * Enqueue inline JS for dynamic template-profile rows.
     *
     * @param string $hook Current page hook.
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( false === strpos( $hook, 'vep-settings' ) ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        // Inline script â€” no external dependency needed.
        wp_add_inline_script(
            'jquery',
            $this->inline_js()
        );
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'volunteer-exchange-platform' ) );
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
        if ( 'sent-emails' === $tab ) {
            $tab = 'transactional-emails';
        }
        if ( ! in_array( $tab, array( 'settings', 'participant-types', 'tags', 'design', 'smtp2go', 'transactional-emails' ), true ) ) {
            $tab = 'settings';
        }

        $saved    = false;
        $errors   = array();

        if ( in_array( $tab, array( 'settings', 'design', 'smtp2go' ), true ) && isset( $_POST['vep_email_settings_nonce'] ) ) {
            if ( ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['vep_email_settings_nonce'] ) ),
                'vep_email_settings_save'
            ) ) {
                $errors[] = __( 'Security check failed. Please try again.', 'volunteer-exchange-platform' );
            } else {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
                Settings::save( wp_unslash( $_POST ) );
                $saved = true;
            }
        }

        $settings = Settings::get_all();
        $profiles = $settings['template_profiles'];
        ?>
        <div class="wrap vep-settings">
            <h1><?php esc_html_e( 'Settings', 'volunteer-exchange-platform' ); ?></h1>
            <script>
            window._vepSettingsPage = {
                nonce: '<?php echo esc_js( wp_create_nonce( 'vep_admin_nonce' ) ); ?>',
                ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>'
            };
            </script>

            <?php $this->render_tabs( $tab ); ?>

            <?php if ( $saved ) : ?>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.vepToast) { window.vepToast(<?php echo wp_json_encode( __( 'Settings saved.', 'volunteer-exchange-platform' ) ); ?>); }
                });
                </script>
            <?php endif; ?>

            <?php foreach ( $errors as $error ) : ?>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.vepToast) { window.vepToast(<?php echo wp_json_encode( $error ); ?>, 'error'); }
                });
                </script>
            <?php endforeach; ?>

            <div id="vep-settings-tab-content" class="vep-ajax-wrap">
                <?php $this->render_tab_content( $tab ); ?>
            </div>
        </div>

        <style>
            .vep-settings .vep-profile-row { border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px; margin-bottom: 16px; background: #f9f9f9; }
            .vep-settings .vep-profile-row h3 { margin-top: 0; }
            .vep-settings .vep-profile-row .form-table th { width: 200px; }
            .vep-settings .remove-profile { float: right; color: #b32d2e; border-color: #b32d2e; }
            .vep-settings .remove-profile:hover { background: #b32d2e; color: #fff; }
        </style>
        <?php
    }

    /**
     * Render content for a single settings tab (used by both render() and AJAX).
     *
     * @param string $tab Active tab key.
     * @return void
     */
    private function render_tab_content( $tab ) {
        $settings = Settings::get_all();
        $profiles = $settings['template_profiles'];

        if ( 'transactional-emails' === $tab ) {
            $this->render_sent_emails_tab();
        } elseif ( 'participant-types' === $tab ) {
            $this->participant_types_page->render_settings_tab();
        } elseif ( 'tags' === $tab ) {
            $this->tags_page->render_settings_tab();
        } else {
            ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'vep_email_settings_save', 'vep_email_settings_nonce' ); ?>
                <input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />

                <?php if ( 'settings' === $tab ) : ?>

                <!-- â”€â”€ General â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
                <h2 class="title"><?php esc_html_e( 'General', 'volunteer-exchange-platform' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="vep_info_email_recipient"><?php esc_html_e( 'Info emails recipient', 'volunteer-exchange-platform' ); ?></label>
                        </th>
                        <td>
                            <input
                                type="email"
                                id="vep_info_email_recipient"
                                name="info_email_recipient"
                                class="regular-text"
                                value="<?php echo esc_attr( $settings['info_email_recipient'] ); ?>"
                            >
                            <p class="description"><?php esc_html_e( 'Email address that receives system notifications, e.g. new participant registrations.', 'volunteer-exchange-platform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vep_max_participants_per_organization"><?php esc_html_e( 'Max participants per organization', 'volunteer-exchange-platform' ); ?></label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="vep_max_participants_per_organization"
                                name="max_participants_per_organization"
                                class="small-text"
                                min="1"
                                step="1"
                                value="<?php echo esc_attr( (int) $settings['max_participants_per_organization'] ); ?>"
                            >
                            <p class="description"><?php esc_html_e( 'Maximum allowed value for Participants Expected on the update form.', 'volunteer-exchange-platform' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- â”€â”€ Log retention â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
                <h2 class="title"><?php esc_html_e( 'Email Log Retention', 'volunteer-exchange-platform' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="vep_log_retention_days"><?php esc_html_e( 'Delete after (days)', 'volunteer-exchange-platform' ); ?></label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="vep_log_retention_days"
                                name="log_retention_days"
                                class="small-text"
                                min="0"
                                step="1"
                                value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>"
                            >
                            <p class="description"><?php esc_html_e( 'Transactional email records older than this many days are automatically removed. Set to 0 to keep records indefinitely.', 'volunteer-exchange-platform' ); ?></p>
                        </td>
                    </tr>
                </table>

                <?php elseif ( 'design' === $tab ) : ?>

                <h2 class="title"><?php esc_html_e( 'Buttons', 'volunteer-exchange-platform' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="vep_button_background_color"><?php esc_html_e( 'Button background', 'volunteer-exchange-platform' ); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="vep_button_background_color"
                                name="button_background_color"
                                class="regular-text vep-color-picker"
                                value="<?php echo esc_attr( $settings['button_background_color'] ); ?>"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vep_button_border_color"><?php esc_html_e( 'Button border', 'volunteer-exchange-platform' ); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="vep_button_border_color"
                                name="button_border_color"
                                class="regular-text vep-color-picker"
                                value="<?php echo esc_attr( $settings['button_border_color'] ); ?>"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vep_button_text_color"><?php esc_html_e( 'Button text', 'volunteer-exchange-platform' ); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="vep_button_text_color"
                                name="button_text_color"
                                class="regular-text vep-color-picker"
                                value="<?php echo esc_attr( $settings['button_text_color'] ); ?>"
                            >
                        </td>
                    </tr>
                </table>

                <?php elseif ( 'smtp2go' === $tab ) : ?>

                <!-- â”€â”€ SMTP2GO connection â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
                <h2 class="title"><?php esc_html_e( 'SMTP2GO Connection', 'volunteer-exchange-platform' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="vep_api_key"><?php esc_html_e( 'API Key', 'volunteer-exchange-platform' ); ?></label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="vep_api_key"
                                name="api_key"
                                class="regular-text"
                                autocomplete="new-password"
                            >
                            <?php if ( '' !== $settings['api_key'] ) : ?>
                                <p class="description"><?php esc_html_e( 'A key is already saved. Leave blank to keep the current key, or enter a new one to replace it.', 'volunteer-exchange-platform' ); ?></p>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e( 'Your SMTP2GO API key.', 'volunteer-exchange-platform' ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vep_sender_name"><?php esc_html_e( 'Sender Name', 'volunteer-exchange-platform' ); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="vep_sender_name"
                                name="sender_name"
                                class="regular-text"
                                value="<?php echo esc_attr( $settings['sender_name'] ); ?>"
                            >
                            <p class="description"><?php esc_html_e( 'Display name used as email sender, e.g. Frivilligcenter Odense.', 'volunteer-exchange-platform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="vep_sender_email"><?php esc_html_e( 'Sender Email', 'volunteer-exchange-platform' ); ?></label>
                        </th>
                        <td>
                            <input
                                type="email"
                                id="vep_sender_email"
                                name="sender_email"
                                class="regular-text"
                                value="<?php echo esc_attr( $settings['sender_email'] ); ?>"
                            >
                            <p class="description"><?php esc_html_e( 'From address for all outgoing emails.', 'volunteer-exchange-platform' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- â”€â”€ Template profiles â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
                <h2 class="title"><?php esc_html_e( 'Email Templates', 'volunteer-exchange-platform' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Each template corresponds to an SMTP2GO template ID. The internal key is used in code to queue the right email.', 'volunteer-exchange-platform' ); ?></p>

                <div id="vep-template-profiles">
                    <?php foreach ( $profiles as $i => $profile ) : ?>
                        <?php $this->render_profile_row( $i, $profile ); ?>
                    <?php endforeach; ?>

                    <?php if ( empty( $profiles ) ) : ?>
                        <?php $this->render_profile_row( 0, array() ); ?>
                    <?php endif; ?>
                </div>

                <p>
                    <button type="button" id="vep-add-profile" class="button">
                        <?php esc_html_e( '+ Add Template Profile', 'volunteer-exchange-platform' ); ?>
                    </button>
                </p>

                <!-- Row template used by JS to clone new rows. -->
                <script type="text/html" id="vep-profile-row-template">
                    <?php $this->render_profile_row( '__INDEX__', array() ); ?>
                </script>

                <?php endif; ?>

                <?php submit_button( __( 'Save Settings', 'volunteer-exchange-platform' ) ); ?>
            </form>
            <?php
        }
    }

    /**
     * AJAX handler: return rendered tab content HTML.
     *
     * @return void
     */
    /**
     * AJAX: return the transactional-emails tab HTML with table params injected.
     *
     * @return void
     */
    public function get_emails_table_ajax() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_admin_nonce' ) ) {
            wp_send_json_error();
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        // Forward table-specific params from POST into $_GET so WP_List_Table reads them.
        $table_params = array( 'orderby', 'order', 'paged', 'vep_email_status', 'vep_per_page', 'vep_date_from', 'vep_date_to', 'vep_search_email' );
        foreach ( $table_params as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
                $_GET[ $key ]     = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
                $_REQUEST[ $key ] = $_GET[ $key ];
            }
        }

        // Spoof REQUEST_URI so WP_List_Table builds sort/pagination links pointing to the
        // real admin page rather than admin-ajax.php.
        $uri_params = array( 'page' => 'vep-settings', 'tab' => 'transactional-emails' );
        foreach ( $table_params as $key ) {
            if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
                $uri_params[ $key ] = $_GET[ $key ];
            }
        }
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?' . http_build_query( $uri_params );

        ob_start();
        $this->render_sent_emails_tab();
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html ) );
    }

    public function get_tab_ajax() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_admin_nonce' ) ) {
            wp_send_json_error();
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'settings';
        if ( ! in_array( $tab, array( 'settings', 'participant-types', 'tags', 'design', 'smtp2go', 'transactional-emails' ), true ) ) {
            $tab = 'settings';
        }

        ob_start();
        $this->render_tab_content( $tab );
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html, 'tab' => $tab ) );
    }

    /**
     * Render tab navigation.
     *
     * @param string $active_tab Active tab key.
     * @return void
     */
    private function render_tabs( $active_tab ) {
        $settings_url = admin_url( 'admin.php?page=vep-settings&tab=settings' );
        $participant_types_url = admin_url( 'admin.php?page=vep-settings&tab=participant-types' );
        $tags_url = admin_url( 'admin.php?page=vep-settings&tab=tags' );
        $design_url = admin_url( 'admin.php?page=vep-settings&tab=design' );
        $smtp2go_url = admin_url( 'admin.php?page=vep-settings&tab=smtp2go' );
        $log_url = admin_url( 'admin.php?page=vep-settings&tab=transactional-emails' );
        ?>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url( $settings_url ); ?>" data-vep-tab="settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Settings', 'volunteer-exchange-platform' ); ?>
            </a>
            <a href="<?php echo esc_url( $participant_types_url ); ?>" data-vep-tab="participant-types" class="nav-tab <?php echo 'participant-types' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Participant Types', 'volunteer-exchange-platform' ); ?>
            </a>
            <a href="<?php echo esc_url( $tags_url ); ?>" data-vep-tab="tags" class="nav-tab <?php echo 'tags' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'We Offer Tags', 'volunteer-exchange-platform' ); ?>
            </a>
            <a href="<?php echo esc_url( $design_url ); ?>" data-vep-tab="design" class="nav-tab <?php echo 'design' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Design', 'volunteer-exchange-platform' ); ?>
            </a>
            <a href="<?php echo esc_url( $smtp2go_url ); ?>" data-vep-tab="smtp2go" class="nav-tab <?php echo 'smtp2go' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'SMTP2GO', 'volunteer-exchange-platform' ); ?>
            </a>
            <a href="<?php echo esc_url( $log_url ); ?>" data-vep-tab="transactional-emails" class="nav-tab <?php echo 'transactional-emails' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Sent Mail', 'volunteer-exchange-platform' ); ?>
            </a>
        </h2>
        <?php
    }

    /**
     * Render the sent emails tab table.
     *
     * @return void
     */
    private function render_sent_emails_tab() {
        $list_table = new TransactionalEmailsListTable();
        $list_table->prepare_items();

        $status_filter = isset( $_GET['vep_email_status'] ) ? sanitize_key( wp_unslash( $_GET['vep_email_status'] ) ) : 'all';
        ?>
        <form method="get">
            <input type="hidden" name="page" value="vep-settings" />
            <input type="hidden" name="tab" value="transactional-emails" />
            <?php if ( 'all' !== $status_filter ) : ?>
                <input type="hidden" name="vep_email_status" value="<?php echo esc_attr( $status_filter ); ?>" />
            <?php endif; ?>
            <?php $list_table->views(); ?>
            <?php $list_table->display(); ?>
        </form>

        <div id="vep-payload-modal" class="vep-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="vep-payload-modal-title">
            <div class="vep-modal-container" style="max-width:780px;">
                <div class="vep-modal-header">
                    <h2 id="vep-payload-modal-title"><?php esc_html_e( 'Email Payload', 'volunteer-exchange-platform' ); ?></h2>
                    <button type="button" id="vep-payload-modal-close" class="vep-modal-close" aria-label="<?php esc_attr_e( 'Close', 'volunteer-exchange-platform' ); ?>">&times;</button>
                </div>
                <div class="vep-modal-body">
                    <pre id="vep-payload-modal-content" class="vep-payload-pre"></pre>
                </div>
                <div class="vep-modal-footer">
                    <button type="button" id="vep-payload-modal-copy" class="button"><?php esc_html_e( 'Copy', 'volunteer-exchange-platform' ); ?></button>
                    <button type="button" id="vep-payload-modal-close-footer" class="button button-primary"><?php esc_html_e( 'Close', 'volunteer-exchange-platform' ); ?></button>
                </div>
            </div>
        </div>

        <script>
        (function () {
            'use strict';
            var modal   = document.getElementById('vep-payload-modal');
            var content = document.getElementById('vep-payload-modal-content');

            function openModal(payloadText) {
                content.textContent = payloadText;
                modal.style.display = 'flex';
                modal.focus();
            }

            function closeModal() {
                modal.style.display = 'none';
            }

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.vep-payload-view-btn');
                if (btn) {
                    openModal(btn.getAttribute('data-payload') || '');
                }
            });

            document.getElementById('vep-payload-modal-close').addEventListener('click', closeModal);
            document.getElementById('vep-payload-modal-close-footer').addEventListener('click', closeModal);

            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
            });

            document.getElementById('vep-payload-modal-copy').addEventListener('click', function () {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(content.textContent).then(function () {
                        if (window.vepToast) window.vepToast(<?php echo wp_json_encode( __( 'Copied to clipboard.', 'volunteer-exchange-platform' ) ); ?>);
                    });
                }
            });

            // Date range constraints: "to" cannot be before "from".
            var dateFrom = document.getElementById('vep-date-from');
            var dateTo   = document.getElementById('vep-date-to');
            if (dateFrom && dateTo) {
                if (dateFrom.value) dateTo.min   = dateFrom.value;
                if (dateTo.value)   dateFrom.max = dateTo.value;
                dateFrom.addEventListener('change', function () {
                    dateTo.min = this.value || '';
                    if (dateTo.value && dateTo.value < this.value) dateTo.value = this.value;
                });
                dateTo.addEventListener('change', function () {
                    dateFrom.max = this.value || '';
                    if (dateFrom.value && dateFrom.value > this.value) dateFrom.value = this.value;
                });
            }
        }());
        </script>
        <?php
    }

    /**
     * Render a single template profile row.
     *
     * @param int|string $index     Row index (or __INDEX__ for template).
     * @param array      $profile   Saved profile data.
     * @return void
     */
    private function render_profile_row( $index, array $profile ) {
        $key       = isset( $profile['key'] )               ? $profile['key']               : '';
        $tpl_id    = isset( $profile['template_id'] )       ? $profile['template_id']       : '';
        $subject   = isset( $profile['default_subject'] )   ? $profile['default_subject']   : '';
        $html_body = isset( $profile['default_html_body'] ) ? $profile['default_html_body'] : '';
        $text_body = isset( $profile['default_text_body'] ) ? $profile['default_text_body'] : '';

        ?>
        <div class="vep-profile-row">
            <h3>
                <?php esc_html_e( 'Template Profile', 'volunteer-exchange-platform' ); ?>
                <button type="button" class="button button-small remove-profile"><?php esc_html_e( 'Remove', 'volunteer-exchange-platform' ); ?></button>
            </h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label><?php esc_html_e( 'Internal Key', 'volunteer-exchange-platform' ); ?></label></th>
                    <td>
                        <?php
                        $known_keys = array(
                            'participant_approved'          => __( 'participant_approved', 'volunteer-exchange-platform' ),
                            'update_participant'            => __( 'update_participant', 'volunteer-exchange-platform' ),
                            'new_participant_registration'  => __( 'new_participant_registration', 'volunteer-exchange-platform' ),
                            'indgaaede_aftale_oversigt'     => __( 'indgaaede_aftale_oversigt', 'volunteer-exchange-platform' ),
                        );
                        ?>
                        <select name="profile_key[<?php echo esc_attr( $index ); ?>]" class="regular-text">
                            <option value=""><?php esc_html_e( 'â€” Select a key â€”', 'volunteer-exchange-platform' ); ?></option>
                            <?php foreach ( $known_keys as $k => $label ) : ?>
                                <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $key, $k ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'The internal template key used in code to queue this email.', 'volunteer-exchange-platform' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Template ID (SMTP2GO)', 'volunteer-exchange-platform' ); ?></label></th>
                    <td>
                        <input type="text" name="profile_template_id[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $tpl_id ); ?>" class="regular-text" placeholder="e.g. 3898074">
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Default Subject', 'volunteer-exchange-platform' ); ?></label></th>
                    <td>
                        <input type="text" name="profile_subject[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $subject ); ?>" class="large-text">
                        <p class="description"><?php esc_html_e( 'Used when no subject is passed in code.', 'volunteer-exchange-platform' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Default HTML Body (fallback)', 'volunteer-exchange-platform' ); ?></label></th>
                    <td>
                        <textarea name="profile_html_body[<?php echo esc_attr( $index ); ?>]" rows="4" class="large-text"><?php echo esc_textarea( $html_body ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Used when no html_body is passed in code and template ID is not set.', 'volunteer-exchange-platform' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Default Text Body (fallback)', 'volunteer-exchange-platform' ); ?></label></th>
                    <td>
                        <textarea name="profile_text_body[<?php echo esc_attr( $index ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( $text_body ); ?></textarea>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Inline JS for add/remove template profile rows.
     *
     * @return string
     */
    private function inline_js() {
        return <<<'JS'
jQuery(function ($) {
    if ($.fn.wpColorPicker) {
        $('.vep-color-picker').wpColorPicker();
    }

    var $container = $('#vep-template-profiles');
    var $template  = $('#vep-profile-row-template').html();
    var $addButton = $('#vep-add-profile');
    var index      = $container.find('.vep-profile-row').length;
    var allKeyOptions = [];

    function profileSelects() {
        return $container.find('select[name^="profile_key["]');
    }

    function collectAllKeyOptions() {
        if (allKeyOptions.length > 0) {
            return;
        }

        var $firstSelect = profileSelects().first();
        if (!$firstSelect.length) {
            return;
        }

        $firstSelect.find('option').each(function () {
            var value = $(this).attr('value');
            if (value) {
                allKeyOptions.push({
                    value: value,
                    label: $(this).text()
                });
            }
        });
    }

    function selectedKeyValues() {
        var selected = [];
        profileSelects().each(function () {
            var value = $(this).val();
            if (value) {
                selected.push(value);
            }
        });
        return selected;
    }

    function enforceUniqueSelections() {
        var seen = {};
        profileSelects().each(function () {
            var $select = $(this);
            var value = $select.val();

            if (!value) {
                return;
            }

            if (seen[value]) {
                $select.val('');
                return;
            }

            seen[value] = true;
        });
    }

    function rebuildSelectOptions() {
        var selected = selectedKeyValues();

        profileSelects().each(function () {
            var $select = $(this);
            var currentValue = $select.val();

            $select.empty();
            $select.append($('<option>', {
                value: '',
                text: '\u2014 Select a key \u2014'
            }));

            allKeyOptions.forEach(function (option) {
                var isSelectedElsewhere = selected.indexOf(option.value) !== -1 && option.value !== currentValue;
                if (isSelectedElsewhere) {
                    return;
                }

                $select.append($('<option>', {
                    value: option.value,
                    text: option.label
                }));
            });

            if (currentValue && $select.find('option[value="' + currentValue.replace(/"/g, '\\"') + '"]').length) {
                $select.val(currentValue);
            } else {
                $select.val('');
            }
        });
    }

    function updateAddButtonVisibility() {
        var usedCount = selectedKeyValues().length;
        $addButton.toggle(usedCount < allKeyOptions.length);
    }

    function refreshKeyUI() {
        collectAllKeyOptions();
        enforceUniqueSelections();
        rebuildSelectOptions();
        updateAddButtonVisibility();
    }

    refreshKeyUI();

    $('#vep-add-profile').on('click', function () {
        if (selectedKeyValues().length >= allKeyOptions.length) {
            return;
        }

        var html = $template.replace(/__INDEX__/g, index);
        $container.append(html);
        index++;
        refreshKeyUI();
    });

    $container.on('click', '.remove-profile', function () {
        $(this).closest('.vep-profile-row').remove();
        refreshKeyUI();
    });

    $container.on('change', 'select[name^="profile_key["]', function () {
        refreshKeyUI();
    });
});

// Settings tab switching via AJAX.
jQuery(function () {
    var cfg = window._vepSettingsPage;
    if (!cfg) return;

    var content = document.getElementById('vep-settings-tab-content');
    if (!content) return;

    function executeScripts(container) {
        Array.from(container.querySelectorAll('script')).forEach(function (old) {
            if (old.type && old.type !== 'text/javascript') return;
            var s = document.createElement('script');
            Array.from(old.attributes).forEach(function (a) { s.setAttribute(a.name, a.value); });
            s.textContent = old.textContent;
            old.parentNode.replaceChild(s, old);
        });
    }

    function loadTab(tab) {
        content.classList.add('is-loading');
        var fd = new FormData();
        fd.append('action', 'vep_get_settings_tab');
        fd.append('nonce', cfg.nonce);
        fd.append('tab', tab);
        fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                content.classList.remove('is-loading');
                if (!res.success) return;
                content.innerHTML = res.data.html;
                executeScripts(content);
                if (typeof jQuery !== 'undefined' && jQuery.fn.wpColorPicker) {
                    jQuery(content).find('.vep-color-picker').wpColorPicker();
                }
            })
            .catch(function () { content.classList.remove('is-loading'); });
    }

    document.querySelectorAll('.nav-tab[data-vep-tab]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var tab = link.getAttribute('data-vep-tab');
            document.querySelectorAll('.nav-tab').forEach(function (t) {
                t.classList.toggle('nav-tab-active', t === link);
            });
            history.pushState({ tab: tab }, '', link.getAttribute('href'));
            loadTab(tab);
        });
    });

    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.tab) {
            var tab = e.state.tab;
            document.querySelectorAll('.nav-tab[data-vep-tab]').forEach(function (t) {
                t.classList.toggle('nav-tab-active', t.getAttribute('data-vep-tab') === tab);
            });
            loadTab(tab);
        }
    });

    // ── Emails table AJAX navigation (sort, pagination, filter) ──────────
    var emailTableKeys = ['orderby', 'order', 'paged', 'vep_email_status', 'vep_per_page', 'vep_date_from', 'vep_date_to', 'vep_search_email'];

    function isEmailsTabShown() {
        return !!(content && content.querySelector('#vep-payload-modal'));
    }

    function loadEmailsTable(searchParams) {
        content.classList.add('is-loading');
        var fd = new FormData();
        fd.append('action', 'vep_get_emails_table');
        fd.append('nonce', cfg.nonce);
        emailTableKeys.forEach(function (k) {
            var v = searchParams.get(k);
            if (v !== null && v !== '') fd.append(k, v);
        });
        fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                content.classList.remove('is-loading');
                if (!res.success) return;
                content.innerHTML = res.data.html;
                executeScripts(content);
            })
            .catch(function () { content.classList.remove('is-loading'); });
    }

    // Intercept sort and pagination link clicks inside the emails tab.
    document.addEventListener('click', function (e) {
        if (!isEmailsTabShown()) return;
        var link = e.target.closest('a');
        if (!link || !content.contains(link)) return;
        var href = link.getAttribute('href');
        if (!href || href === '#') return;
        var url;
        try { url = new URL(href, window.location.href); } catch (ex) { return; }
        var hasTableParam = url.searchParams.has('orderby') || url.searchParams.has('order') ||
            url.searchParams.has('paged') || url.searchParams.has('vep_email_status');
        if (!hasTableParam) return;
        e.preventDefault();
        history.replaceState(null, '', href);
        loadEmailsTable(url.searchParams);
    });

    // Intercept filter form submit (date, email search, per-page select).
    document.addEventListener('submit', function (e) {
        if (!isEmailsTabShown()) return;
        var form = e.target;
        if (!content.contains(form)) return;
        e.preventDefault();
        var sp = new URLSearchParams();
        Array.from(new FormData(form)).forEach(function (pair) { sp.set(pair[0], pair[1]); });
        history.replaceState(null, '', '?' + sp.toString());
        loadEmailsTable(sp);
    });
});
JS;
    }
}
