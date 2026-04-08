<?php
/**
 * Participant types admin page
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\ParticipantTypeService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Participant Types admin page
 *
 * Handles the admin interface for managing participant types including
 * listing, adding, editing, and deleting types.
 *
 * @package VolunteerExchangePlatform\Admin
 */
class ParticipantTypesPage {
    /**
     * @var ParticipantTypeService
     */
    private $service;

    /**
     * Constructor.
     *
     * @param ParticipantTypeService|null $service Participant type service instance.
     * @return void
     */
    public function __construct( ?ParticipantTypeService $service = null ) {
        $this->service = $service ?: new ParticipantTypeService();
        add_action('admin_init', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_delete'));
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue admin assets for participant types page.
     *
     * @param string $hook Current admin hook.
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( false === strpos( $hook, 'volunteer-exchange-participant-types' ) ) {
            return;
        }

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".vep-color-picker").wpColorPicker();});' );
    }

    /**
     * Render the page
     *
     * Routes to the appropriate view based on the action parameter
     *
     * @return void
     */
    public function render() {
        $action_raw = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $type_id_raw = filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );
        $action  = $action_raw ? sanitize_key( $action_raw ) : 'list';
        $type_id = $type_id_raw ? absint( $type_id_raw ) : 0;

        switch ($action) {
            case 'add':
            case 'edit':
                $this->render_form($type_id);
                break;
            default:
                $this->render_list();
                break;
        }
    }

    /**
     * Render list of participant types
     *
     * Displays the WP_List_Table with all participant types and success messages
     *
     * @return void
     */
    private function render_list() {
        $list_table = new \VolunteerExchangePlatform\Admin\ParticipantTypesListTable($this->service);
        $list_table->prepare_items();
        $message = filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_raw = filter_input( INPUT_GET, 'notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_type = $notice_raw ? sanitize_key( $notice_raw ) : 'success';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Participant Types', 'volunteer-exchange-platform'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-participant-types&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'volunteer-exchange-platform'); ?>
            </a>
            <hr class="wp-header-end">
            
            <?php if ( ! empty( $message ) ): ?>
                <div class="notice notice-<?php echo esc_attr(in_array($notice_type, array('success', 'warning', 'error', 'info'), true) ? $notice_type : 'success'); ?> is-dismissible">
                    <p><?php echo esc_html( urldecode( (string) $message ) ); ?></p>
                </div>
            <?php endif; ?>
            
            <?php $list_table->display(); ?>
        </div>
        <?php
    }

    /**
     * Render form for adding or editing a participant type
     *
     * @param int $type_id The participant type ID (0 for new type)
     * @return void
     */
    private function render_form($type_id = 0) {
        $type = null;
        if ($type_id > 0) {
            $type = $this->service->get_by_id($type_id);
        }

        $title = $type ? __('Edit Participant Type', 'volunteer-exchange-platform') : __('Add New Participant Type', 'volunteer-exchange-platform');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <form method="post" action="" class="vep-category-form">
                <?php wp_nonce_field('vep_type_form', 'vep_type_nonce'); ?>
                <input type="hidden" name="type_id" value="<?php echo esc_attr($type_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="type_icon"><?php esc_html_e('Icon', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <div class="vep-icon-picker">
                                <input type="hidden" id="type_icon" name="type_icon" class="vep-icon-input" value="<?php echo $type && isset($type->icon) ? esc_attr($type->icon) : ''; ?>">
                                <span class="vep-icon-preview" id="icon-preview">
                                    <?php
                                    $has_icon = $type && isset($type->icon) && ! empty($type->icon);
                                    if ($has_icon) :
                                        $icon_value = $type->icon;
                                        if (strpos($icon_value, 's:') === 0) {
                                            $icon_class = 'fa-solid';
                                            $icon_name = substr($icon_value, 2);
                                        } elseif (strpos($icon_value, 'r:') === 0) {
                                            $icon_class = 'fa-regular';
                                            $icon_name = substr($icon_value, 2);
                                        } elseif (strpos($icon_value, 'b:') === 0) {
                                            $icon_class = 'fa-brands';
                                            $icon_name = substr($icon_value, 2);
                                        } else {
                                            $icon_class = 'fa-solid';
                                            $icon_name = $icon_value;
                                        }
                                    ?>
                                        <i class="<?php echo esc_attr($icon_class); ?> <?php echo esc_attr($icon_name); ?>"></i>
                                    <?php else : ?>
                                        <span class="vep-icon-placeholder"><?php esc_html_e('Please select icon', 'volunteer-exchange-platform'); ?></span>
                                    <?php endif; ?>
                                </span>
                                <button type="button" id="open-icon-picker" class="button"><?php esc_html_e('Select Icon', 'volunteer-exchange-platform'); ?></button>
                                <button type="button" id="clear-icon-picker" class="button button-secondary" style="margin-left: 5px;"><?php esc_html_e('Clear', 'volunteer-exchange-platform'); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e('Select an icon from the picker.', 'volunteer-exchange-platform'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="type_name"><?php esc_html_e('Name', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="type_name" name="type_name" class="regular-text"
                                   value="<?php echo $type ? esc_attr($type->name) : ''; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="type_color"><?php esc_html_e('Color', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="type_color"
                                name="type_color"
                                class="regular-text vep-color-picker"
                                value="<?php echo $type && isset( $type->color ) ? esc_attr( $type->color ) : ''; ?>"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="type_description"><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <textarea id="type_description" name="type_description" rows="5" class="large-text"><?php echo $type ? esc_textarea($type->description ?? '') : ''; ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr($type ? __('Update', 'volunteer-exchange-platform') : __('Create', 'volunteer-exchange-platform')); ?>">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-participant-types')); ?>" class="button">
                        <?php esc_html_e('Cancel', 'volunteer-exchange-platform'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle form submission for creating or updating a participant type
     *
     * Validates nonce, checks permissions, and saves the type data
     *
     * @return void
     */
    public function handle_form_submission() {
        // Only process on our page
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'volunteer-exchange-participant-types' !== $page ) {
            return;
        }

        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
        if ( 'POST' !== strtoupper( $request_method ) ) {
            return;
        }

        $nonce = isset( $_POST['vep_type_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vep_type_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_type_form' ) ) {
            wp_die(esc_html__('Security check failed.', 'volunteer-exchange-platform'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }

        $type_id = isset( $_POST['type_id'] ) ? absint( wp_unslash( $_POST['type_id'] ) ) : 0;
        $data    = array(
            'name'        => isset( $_POST['type_name'] ) ? sanitize_text_field( wp_unslash( $_POST['type_name'] ) ) : '',
            'description' => isset( $_POST['type_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['type_description'] ) ) : '',
            'icon'        => isset( $_POST['type_icon'] ) ? sanitize_text_field( wp_unslash( $_POST['type_icon'] ) ) : '',
            'color'       => isset( $_POST['type_color'] ) ? sanitize_text_field( wp_unslash( $_POST['type_color'] ) ) : '',
        );

        if ($type_id > 0) {
            $this->service->update_type($type_id, $data);
            $message = __('Type updated successfully.', 'volunteer-exchange-platform');
        } else {
            $this->service->create($data);
            $message = __('Type created successfully.', 'volunteer-exchange-platform');
        }

        wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-participant-types&message=' . urlencode($message))));
        exit;
    }

    /**
     * Handle deletion of a participant type
     *
     * Validates permissions and deletes the specified type from the database
     *
     * @return void
     */
    public function handle_delete() {
        // Only process on our page
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'volunteer-exchange-participant-types' !== $page ) {
            return;
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        if ( 'delete' !== $action || ! isset( $_GET['id'] ) ) {
            return;
        }

        $type_id = absint( wp_unslash( $_GET['id'] ) );

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }

        $nonce_action = 'vep_participant_type_delete_' . $type_id;
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
            $message = __('Security check failed.', 'volunteer-exchange-platform');
            wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-participant-types&notice=error&message=' . urlencode($message))));
            exit;
        }

        $this->service->delete_type($type_id);

        wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-participant-types&notice=success&message=' . urlencode(__('Type deleted.', 'volunteer-exchange-platform')))));
        exit;
    }
}
