<?php
/**
 * Email settings admin page.
 *
 * @package VEP
 * @subpackage Admin
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Email\EmailSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmailSettingsPage {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue inline JS for dynamic template-profile rows.
     *
     * @param string $hook Current page hook.
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( false === strpos( $hook, 'vep-email-settings' ) ) {
            return;
        }

        // Inline script — no external dependency needed.
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
        if ( ! in_array( $tab, array( 'settings', 'transactional-emails' ), true ) ) {
            $tab = 'settings';
        }

        $saved    = false;
        $errors   = array();

        if ( 'settings' === $tab && isset( $_POST['vep_email_settings_nonce'] ) ) {
            if ( ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['vep_email_settings_nonce'] ) ),
                'vep_email_settings_save'
            ) ) {
                $errors[] = __( 'Security check failed. Please try again.', 'volunteer-exchange-platform' );
            } else {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
                EmailSettings::save( wp_unslash( $_POST ) );
                $saved = true;
            }
        }

        $settings = EmailSettings::get_all();
        $profiles = $settings['template_profiles'];
        ?>
        <div class="wrap vep-email-settings">
            <h1><?php esc_html_e( 'Email Settings', 'volunteer-exchange-platform' ); ?></h1>

            <?php $this->render_tabs( $tab ); ?>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Settings saved.', 'volunteer-exchange-platform' ); ?></p>
                </div>
            <?php endif; ?>

            <?php foreach ( $errors as $error ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endforeach; ?>

            <?php if ( 'transactional-emails' === $tab ) : ?>
                <?php $this->render_sent_emails_tab(); ?>
            <?php else : ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'vep_email_settings_save', 'vep_email_settings_nonce' ); ?>

                <!-- ── SMTP2GO connection ───────────────────────────────── -->
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
                                value=""
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

                <!-- ── Log retention ──────────────────────────────────── -->
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

                <!-- ── Template profiles ───────────────────────────────── -->
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

                <?php submit_button( __( 'Save Settings', 'volunteer-exchange-platform' ) ); ?>
            </form>
            <?php endif; ?>
        </div>

        <?php if ( 'settings' === $tab ) : ?>
            <!-- Row template used by JS to clone new rows. -->
            <script type="text/html" id="vep-profile-row-template">
                <?php $this->render_profile_row( '__INDEX__', array() ); ?>
            </script>
        <?php endif; ?>

        <style>
            .vep-email-settings .vep-profile-row { border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px; margin-bottom: 16px; background: #f9f9f9; }
            .vep-email-settings .vep-profile-row h3 { margin-top: 0; }
            .vep-email-settings .vep-profile-row .form-table th { width: 200px; }
            .vep-email-settings .remove-profile { float: right; color: #b32d2e; border-color: #b32d2e; }
            .vep-email-settings .remove-profile:hover { background: #b32d2e; color: #fff; }
        </style>
        <?php
    }

    /**
     * Render tab navigation.
     *
     * @param string $active_tab Active tab key.
     * @return void
     */
    private function render_tabs( $active_tab ) {
        $settings_url = admin_url( 'admin.php?page=vep-email-settings&tab=settings' );
        $log_url = admin_url( 'admin.php?page=vep-email-settings&tab=transactional-emails' );
        ?>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url( $settings_url ); ?>" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Settings', 'volunteer-exchange-platform' ); ?>
            </a>
            <a href="<?php echo esc_url( $log_url ); ?>" class="nav-tab <?php echo 'transactional-emails' === $active_tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Transactional Emails', 'volunteer-exchange-platform' ); ?>
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
            <input type="hidden" name="page" value="vep-email-settings" />
            <input type="hidden" name="tab" value="transactional-emails" />
            <?php if ( 'all' !== $status_filter ) : ?>
                <input type="hidden" name="vep_email_status" value="<?php echo esc_attr( $status_filter ); ?>" />
            <?php endif; ?>
            <?php $list_table->views(); ?>
            <?php $list_table->display(); ?>
        </form>
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
        $label     = isset( $profile['label'] )             ? $profile['label']             : '';
        $tpl_id    = isset( $profile['template_id'] )       ? $profile['template_id']       : '';
        $subject   = isset( $profile['default_subject'] )   ? $profile['default_subject']   : '';
        $html_body = isset( $profile['default_html_body'] ) ? $profile['default_html_body'] : '';
        $text_body = isset( $profile['default_text_body'] ) ? $profile['default_text_body'] : '';
        $data_keys = isset( $profile['allowed_data_keys'] ) && is_array( $profile['allowed_data_keys'] )
            ? implode( "\n", $profile['allowed_data_keys'] )
            : '';

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
                        <input type="text" name="profile_key[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $key ); ?>" class="regular-text" placeholder="e.g. participant_confirmation">
                        <p class="description"><?php esc_html_e( 'Unique slug used in code, e.g. participant_confirmation. Lowercase, no spaces.', 'volunteer-exchange-platform' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Label', 'volunteer-exchange-platform' ); ?></label></th>
                    <td>
                        <input type="text" name="profile_label[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" placeholder="e.g. Participant Confirmation">
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
                    <th><label><?php esc_html_e( 'Allowed template_data Keys', 'volunteer-exchange-platform' ); ?></label></th>
                    <td>
                        <textarea name="profile_data_keys[<?php echo esc_attr( $index ); ?>]" rows="4" class="large-text" placeholder="event_name&#10;contact_name&#10;confirm_url"><?php echo esc_textarea( $data_keys ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One key per line. Only keys listed here are forwarded to SMTP2GO.', 'volunteer-exchange-platform' ); ?></p>
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
    var $container = $('#vep-template-profiles');
    var $template  = $('#vep-profile-row-template').html();
    var index      = $container.find('.vep-profile-row').length;

    $('#vep-add-profile').on('click', function () {
        var html = $template.replace(/__INDEX__/g, index);
        $container.append(html);
        index++;
    });

    $container.on('click', '.remove-profile', function () {
        $(this).closest('.vep-profile-row').remove();
    });
});
JS;
    }
}
