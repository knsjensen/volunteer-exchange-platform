<?php
/**
 * We Offer Tags admin page
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\TagService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * We Offer Tags admin page
 *
 * Handles the admin interface for managing "We Offer" tags including
 * listing, adding, editing, and deleting tags.
 *
 * @package VolunteerExchangePlatform\Admin
 */
class TagsPage {
    /**
     * @var TagService
     */
    private $service;

    /**
     * Constructor.
     *
     * @param TagService|null $service Tag service instance.
     * @return void
     */
    public function __construct( ?TagService $service = null ) {
        $this->service = $service ?: new TagService();
        add_action('admin_init', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_delete'));
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
        $tag_id_raw = filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );
        $action  = $action_raw ? sanitize_key( $action_raw ) : 'list';
        $tag_id = $tag_id_raw ? absint( $tag_id_raw ) : 0;

        switch ($action) {
            case 'add':
            case 'edit':
                $this->render_form($tag_id);
                break;
            default:
                $this->render_list();
                break;
        }
    }

    /**
     * Render list of we offer tags
     *
     * Displays the WP_List_Table with all tags and success messages
     *
     * @return void
     */
    private function render_list() {
        $list_table = new \VolunteerExchangePlatform\Admin\TagsListTable($this->service);
        $list_table->prepare_items();
        $message = filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_raw = filter_input( INPUT_GET, 'notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_type = $notice_raw ? sanitize_key( $notice_raw ) : 'success';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('We Offer Tags', 'volunteer-exchange-platform'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-tags&action=add')); ?>" class="page-title-action">
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
     * Render form for adding or editing a we offer tag
     *
     * @param int $tag_id The tag ID (0 for new tag)
     * @return void
     */
    private function render_form($tag_id = 0) {
        $tag = null;
        if ($tag_id > 0) {
            $tag = $this->service->get_by_id($tag_id);
        }

        $title = $tag ? __('Edit Tag', 'volunteer-exchange-platform') : __('Add New Tag', 'volunteer-exchange-platform');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <form method="post" action="" class="vep-tag-form">
                <?php wp_nonce_field('vep_tag_form', 'vep_tag_nonce'); ?>
                <input type="hidden" name="tag_id" value="<?php echo esc_attr($tag_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tag_icon"><?php esc_html_e('Icon', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <div class="vep-icon-picker">
                                <input type="hidden" id="tag_icon" name="tag_icon" class="vep-icon-input" value="<?php echo $tag && isset($tag->icon) ? esc_attr($tag->icon) : ''; ?>">
                                <span class="vep-icon-preview" id="icon-preview">
                                    <?php
                                    $has_icon = $tag && isset($tag->icon) && ! empty($tag->icon);
                                    if ($has_icon) :
                                        $icon_value = $tag->icon;
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
                            <label for="tag_name"><?php esc_html_e('Name', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="tag_name" name="tag_name" class="regular-text" 
                                   value="<?php echo $tag ? esc_attr($tag->name) : ''; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tag_description"><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <textarea id="tag_description" name="tag_description" rows="5" class="large-text"><?php echo $tag ? esc_textarea($tag->description ?? '') : ''; ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr($tag ? __('Update', 'volunteer-exchange-platform') : __('Create', 'volunteer-exchange-platform')); ?>">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-tags')); ?>" class="button">
                        <?php esc_html_e('Cancel', 'volunteer-exchange-platform'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle form submission for creating or updating a tag
     *
     * Validates nonce, checks permissions, and saves the tag data
     *
     * @return void
     */
    public function handle_form_submission() {
        // Only process on our page
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'volunteer-exchange-tags' !== $page ) {
            return;
        }

        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
        if ( 'POST' !== strtoupper( $request_method ) ) {
            return;
        }

        $nonce = isset( $_POST['vep_tag_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vep_tag_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_tag_form' ) ) {
            wp_die(esc_html__('Security check failed.', 'volunteer-exchange-platform'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }

        $tag_id = isset( $_POST['tag_id'] ) ? absint( wp_unslash( $_POST['tag_id'] ) ) : 0;
        $data = array(
            'name'        => isset( $_POST['tag_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '',
            'description' => isset( $_POST['tag_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tag_description'] ) ) : '',
            'icon'        => isset( $_POST['tag_icon'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_icon'] ) ) : ''
        );

        if ($tag_id > 0) {
            $this->service->update_tag($tag_id, $data);
            $message = __('Tag updated successfully.', 'volunteer-exchange-platform');
        } else {
            $this->service->create($data);
            $message = __('Tag created successfully.', 'volunteer-exchange-platform');
        }

        wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-tags&message=' . urlencode($message))));
        exit;
    }

    /**
     * Handle deletion of a we offer tag
     *
     * Validates permissions and deletes the specified tag from the database
     *
     * @return void
     */
    public function handle_delete() {
        // Only process on our page
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'volunteer-exchange-tags' !== $page ) {
            return;
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        if ( 'delete' !== $action || ! isset( $_GET['id'] ) ) {
            return;
        }

        $tag_id = absint( wp_unslash( $_GET['id'] ) );

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }

        $nonce_action = 'vep_tag_delete_' . $tag_id;
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
            $message = __('Security check failed.', 'volunteer-exchange-platform');
            wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-tags&notice=error&message=' . urlencode($message))));
            exit;
        }

        $this->service->delete_tag($tag_id);

        wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-tags&notice=success&message=' . urlencode(__('Tag deleted.', 'volunteer-exchange-platform')))));
        exit;
    }
}
