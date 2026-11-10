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
    public const SETTINGS_PAGE_SLUG = 'vep-settings';
    public const SETTINGS_TAB = 'tags';

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
        add_action( 'admin_init', array( $this, 'handle_delete' ) );
        add_action( 'wp_ajax_vep_save_tag', array( $this, 'save_ajax' ) );
        add_action( 'wp_ajax_vep_get_tags_table', array( $this, 'render_table_ajax' ) );
    }

    /**
     * Render the page
     *
     * Routes to the appropriate view based on the action parameter
     *
     * @return void
     */
    public function render() {
        $this->render_current_view( false );
    }

    /**
     * Render the page inside the Settings tabs.
     *
     * @return void
     */
    public function render_settings_tab() {
        $this->render_current_view( true );
    }

    /**
     * Determine whether current request targets the settings tab.
     *
     * @return bool
     */
    public static function is_settings_tab_request() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

        return self::SETTINGS_PAGE_SLUG === $page && self::SETTINGS_TAB === $tab;
    }

    /**
     * Build the canonical admin URL for this settings tab.
     *
     * @param array $args Optional query args.
     * @return string
     */
    public static function get_admin_url( array $args = array() ) {
        return add_query_arg(
            array_merge(
                array(
                    'page' => self::SETTINGS_PAGE_SLUG,
                    'tab'  => self::SETTINGS_TAB,
                ),
                $args
            ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * Render current view in either standalone or embedded settings context.
     *
     * @param bool $embedded Whether rendering inside settings tabs.
     * @return void
     */
    private function render_current_view( $embedded ) {
        $this->render_list( $embedded );
    }

    /**
     * Render list of we offer tags
     *
     * Displays the WP_List_Table with all tags and success messages
     *
     * @return void
     */
    private function render_list( $embedded = false ) {
        $list_table = new \VolunteerExchangePlatform\Admin\TagsListTable( $this->service );
        $list_table->prepare_items();
        $message    = filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_raw = filter_input( INPUT_GET, 'notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_type = $notice_raw ? sanitize_key( $notice_raw ) : 'success';

        // Fetch all tags for modal pre-fill.
        $all_tags   = $this->service->get_paginated( 9999, 0 );
        $tags_json  = wp_json_encode(
            array_map( function ( $t ) {
                return array(
                    'id'          => (int) $t->id,
                    'name'        => (string) $t->name,
                    'description' => isset( $t->description ) ? (string) $t->description : '',
                    'icon'        => isset( $t->icon ) ? (string) $t->icon : '',
                );
            }, $all_tags )
        );
        ?>
        <div class="<?php echo $embedded ? 'vep-settings-tab-panel' : 'wrap'; ?>">
            <<?php echo $embedded ? 'h2' : 'h1'; ?> class="wp-heading-inline"><?php esc_html_e( 'We Offer Tags', 'volunteer-exchange-platform' ); ?></<?php echo $embedded ? 'h2' : 'h1'; ?>>
            <button type="button" class="page-title-action" id="vep-tag-add-btn">
                <?php esc_html_e( 'Add New', 'volunteer-exchange-platform' ); ?>
            </button>
            <hr class="wp-header-end">

            <?php if ( ! empty( $message ) ) : ?>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.vepToast) {
                        window.vepToast(<?php echo wp_json_encode( urldecode( (string) $message ) ); ?>, <?php echo wp_json_encode( in_array( $notice_type, array( 'success', 'warning', 'error', 'info' ), true ) ? $notice_type : 'success' ); ?>);
                    }
                });
                </script>
            <?php endif; ?>

            <div id="vep-tags-table-wrap" class="vep-ajax-wrap"><?php $list_table->display(); ?></div>
        </div>
        <div class="vep-modal-overlay" id="vep-tag-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="vep-tag-modal-title">
            <div class="vep-modal-container">
                <div class="vep-modal-header">
                    <h2 id="vep-tag-modal-title"><?php esc_html_e( 'Add New Tag', 'volunteer-exchange-platform' ); ?></h2>
                    <button type="button" class="vep-modal-close" aria-label="<?php esc_attr_e( 'Close', 'volunteer-exchange-platform' ); ?>">&times;</button>
                </div>
                <div class="vep-modal-body">
                    <form class="vep-tag-form" id="vep-tag-modal-form">
                        <?php wp_nonce_field( 'vep_admin_nonce', 'vep_admin_nonce_field' ); ?>
                        <input type="hidden" id="vep-tag-modal-id" name="tag_id" value="0">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="tag_icon"><?php esc_html_e( 'Icon', 'volunteer-exchange-platform' ); ?></label></th>
                                <td>
                                    <div class="vep-icon-picker">
                                        <input type="hidden" id="tag_icon" name="tag_icon" class="vep-icon-input" value="">
                                        <span class="vep-icon-preview" id="icon-preview">
                                            <span class="vep-icon-placeholder"><?php esc_html_e( 'Please select icon', 'volunteer-exchange-platform' ); ?></span>
                                        </span>
                                        <button type="button" id="open-icon-picker" class="button"><?php esc_html_e( 'Select Icon', 'volunteer-exchange-platform' ); ?></button>
                                        <button type="button" id="clear-icon-picker" class="button button-secondary" style="margin-left:5px;"><?php esc_html_e( 'Clear', 'volunteer-exchange-platform' ); ?></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tag_name"><?php esc_html_e( 'Name', 'volunteer-exchange-platform' ); ?></label></th>
                                <td><input type="text" id="tag_name" name="tag_name" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tag_description"><?php esc_html_e( 'Description', 'volunteer-exchange-platform' ); ?></label></th>
                                <td><textarea id="tag_description" name="tag_description" rows="4" class="large-text"></textarea></td>
                            </tr>
                        </table>
                    </form>
                </div>
                <div class="vep-modal-footer">
                    <button type="button" class="button button-primary" id="vep-tag-modal-save"><?php esc_html_e( 'Save', 'volunteer-exchange-platform' ); ?></button>
                    <button type="button" class="button vep-modal-close"><?php esc_html_e( 'Cancel', 'volunteer-exchange-platform' ); ?></button>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var overlay    = document.getElementById('vep-tag-modal-overlay');
            var modalTitle = document.getElementById('vep-tag-modal-title');
            var idField    = document.getElementById('vep-tag-modal-id');
            var nameField  = document.getElementById('tag_name');
            var descField  = document.getElementById('tag_description');
            var iconField  = document.getElementById('tag_icon');
            var iconPreview= document.getElementById('icon-preview');
            var saveBtn    = document.getElementById('vep-tag-modal-save');
            var nonce      = document.getElementById('vep_admin_nonce_field').value;
            var tagsData   = <?php echo $tags_json; ?>;
            var labelAdd   = <?php echo wp_json_encode( __( 'Add New Tag', 'volunteer-exchange-platform' ) ); ?>;
            var labelEdit  = <?php echo wp_json_encode( __( 'Edit Tag', 'volunteer-exchange-platform' ) ); ?>;
            var ajaxUrl    = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

            function renderIconPreview(iconVal) {
                if (!iconVal) {
                    iconPreview.innerHTML = '<span class="vep-icon-placeholder"><?php echo esc_js( __( 'Please select icon', 'volunteer-exchange-platform' ) ); ?></span>';
                    return;
                }
                var cls = 'fa-solid', name = iconVal;
                if (iconVal.indexOf('s:') === 0) { cls = 'fa-solid';   name = iconVal.slice(2); }
                else if (iconVal.indexOf('r:') === 0) { cls = 'fa-regular'; name = iconVal.slice(2); }
                else if (iconVal.indexOf('b:') === 0) { cls = 'fa-brands';  name = iconVal.slice(2); }
                if (name.indexOf('fa-') !== 0) { name = 'fa-' + name; }
                iconPreview.innerHTML = '<i class="' + cls + ' ' + name + '"></i>';
            }

            // Document-level listeners are re-bound on every AJAX tab load: drop the previous ones first.
            var docReg = window._vepTagsDocHandlers = window._vepTagsDocHandlers || [];
            docReg.forEach(function (h) { document.removeEventListener(h[0], h[1]); });
            docReg.length = 0;
            function bindDoc(type, fn) { document.addEventListener(type, fn); docReg.push([type, fn]); }

            function openModal(tag) {
                if (tag) {
                    modalTitle.textContent = labelEdit;
                    idField.value   = tag.id;
                    nameField.value = tag.name;
                    descField.value = tag.description;
                    iconField.value = tag.icon;
                    renderIconPreview(tag.icon);
                } else {
                    modalTitle.textContent = labelAdd;
                    idField.value   = 0;
                    nameField.value = '';
                    descField.value = '';
                    iconField.value = '';
                    renderIconPreview('');
                }
                overlay.style.display = 'flex';
                nameField.focus();
            }

            function closeModal() {
                overlay.style.display = 'none';
            }

            // Add New button.
            document.getElementById('vep-tag-add-btn').addEventListener('click', function () {
                openModal(null);
            });

            // Edit buttons (delegated - list table rows).
            bindDoc('click', function (e) {
                var btn = e.target.closest('.vep-tag-edit-btn');
                if (!btn) return;
                e.preventDefault();
                var id  = parseInt(btn.dataset.id, 10);
                var tag = tagsData.find(function (t) { return t.id === id; });
                openModal(tag || null);
            });

            // Close on overlay background click.
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });

            // Close buttons.
            overlay.querySelectorAll('.vep-modal-close').forEach(function (btn) {
                btn.addEventListener('click', closeModal);
            });

            // Save via AJAX.
            saveBtn.addEventListener('click', function () {
                var form = document.getElementById('vep-tag-modal-form');
                if (!form.reportValidity()) return;
                saveBtn.disabled = true;
                var data = new FormData();
                data.append('action', 'vep_save_tag');
                data.append('nonce', nonce);
                data.append('tag_id', idField.value);
                data.append('tag_name', nameField.value);
                data.append('tag_description', descField.value);
                data.append('tag_icon', iconField.value);
                fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        saveBtn.disabled = false;
                        closeModal();
                        if (window.vepToast) window.vepToast(res.data && res.data.message ? res.data.message : '');
                        if (res.success) {
                            if (window.jQuery) window.jQuery(window).off('beforeunload');
                            var wrap = document.getElementById('vep-tags-table-wrap');
                            if (wrap) wrap.classList.add('is-loading');
                            var refresh = new FormData();
                            refresh.append('action', 'vep_get_tags_table');
                            refresh.append('nonce', nonce);
                            fetch(ajaxUrl, { method: 'POST', body: refresh, credentials: 'same-origin' })
                                .then(function (r2) { return r2.json(); })
                                .then(function (res2) {
                                    if (wrap) wrap.classList.remove('is-loading');
                                    if (res2.success) {
                                        wrap.innerHTML = res2.data.html;
                                        tagsData = res2.data.items;
                                    }
                                })
                                .catch(function () { if (wrap) wrap.classList.remove('is-loading'); });
                        }
                    })
                    .catch(function () { saveBtn.disabled = false; });
            });

            // ESC key.
            bindDoc('keydown', function (e) {
                if (e.key === 'Escape' && overlay.style.display !== 'none') closeModal();
            });

            // Table navigation (pagination + sort) via AJAX — no page reload.
            bindDoc('click', function (e) {
                var wrap = document.getElementById('vep-tags-table-wrap');
                if (!wrap) return;
                var link = e.target.closest('a');
                if (!link || !wrap.contains(link)) return;
                var href = link.getAttribute('href');
                if (!href || href === '#') return;
                var url;
                try { url = new URL(href, window.location.href); } catch (ex) { return; }
                var hasTableParam = url.searchParams.has('paged') || url.searchParams.has('orderby') || url.searchParams.has('order');
                if (!hasTableParam) return;
                e.preventDefault();
                wrap.classList.add('is-loading');
                var params = new FormData();
                params.append('action', 'vep_get_tags_table');
                params.append('nonce', nonce);
                url.searchParams.forEach(function (v, k) {
                    if (k === 'orderby' || k === 'order' || k === 'paged') params.append(k, v);
                });
                fetch(ajaxUrl, { method: 'POST', body: params, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        wrap.classList.remove('is-loading');
                        if (res.success) {
                            wrap.innerHTML = res.data.html;
                            tagsData = res.data.items;
                            history.replaceState(null, '', href);
                        }
                    })
                    .catch(function () { wrap.classList.remove('is-loading'); });
            });
        }());
        </script>
        <?php
    }

    /**
     * Render form for adding or editing a we offer tag
     *
     * @param int $tag_id The tag ID (0 for new tag)
     * @return void
     */
    private function render_form($tag_id = 0, $embedded = false) {
        $tag = null;
        if ($tag_id > 0) {
            $tag = $this->service->get_by_id($tag_id);
        }

        $title = $tag ? __('Edit Tag', 'volunteer-exchange-platform') : __('Add New Tag', 'volunteer-exchange-platform');
        ?>
        <div class="<?php echo $embedded ? 'vep-settings-tab-panel' : 'wrap'; ?>">
            <<?php echo $embedded ? 'h2' : 'h1'; ?>><?php echo esc_html($title); ?></<?php echo $embedded ? 'h2' : 'h1'; ?>>
            
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
                    <a href="<?php echo esc_url( self::get_admin_url() ); ?>" class="button">
                        <?php esc_html_e('Cancel', 'volunteer-exchange-platform'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle AJAX save (add or update) for a tag.
     *
     * @return void
     */
    public function save_ajax() {        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'volunteer-exchange-platform' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'volunteer-exchange-platform' ) ) );
        }

        $tag_id = isset( $_POST['tag_id'] ) ? absint( wp_unslash( $_POST['tag_id'] ) ) : 0;
        $data   = array(
            'name'        => isset( $_POST['tag_name'] )        ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) )       : '',
            'description' => isset( $_POST['tag_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tag_description'] ) ) : '',
            'icon'        => isset( $_POST['tag_icon'] )        ? sanitize_text_field( wp_unslash( $_POST['tag_icon'] ) )       : '',
        );

        if ( '' === $data['name'] ) {
            wp_send_json_error( array( 'message' => __( 'Name is required.', 'volunteer-exchange-platform' ) ) );
        }

        if ( $tag_id > 0 ) {
            $ok      = false !== $this->service->update_tag( $tag_id, $data );
            $message = __( 'Tag updated successfully.', 'volunteer-exchange-platform' );
        } else {
            $ok      = (bool) $this->service->create( $data );
            $message = __( 'Tag created successfully.', 'volunteer-exchange-platform' );
        }

        if ( ! $ok ) {
            wp_send_json_error( array( 'message' => __( 'Could not save the tag. The name may already be in use.', 'volunteer-exchange-platform' ) ) );
        }

        wp_send_json_success( array( 'message' => $message ) );
    }

    /**
     * Return rendered table HTML + items JSON for in-place refresh.
     *
     * @return void
     */
    public function render_table_ajax() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_admin_nonce' ) ) {
            wp_send_json_error();
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        $list_table = new \VolunteerExchangePlatform\Admin\TagsListTable( $this->service );
        foreach ( array( 'orderby', 'order', 'paged' ) as $param ) {
            if ( isset( $_POST[ $param ] ) ) {
                $val = sanitize_key( wp_unslash( $_POST[ $param ] ) );
                $_GET[ $param ]     = $val;
                $_REQUEST[ $param ] = $val;
            }
        }
        $list_table->prepare_items();
        ob_start();
        $list_table->display();
        $html = ob_get_clean();

        $items = array_map( function ( $t ) {
            return array(
                'id'          => (int) $t->id,
                'name'        => (string) $t->name,
                'description' => isset( $t->description ) ? (string) $t->description : '',
                'icon'        => isset( $t->icon )        ? (string) $t->icon        : '',
            );
        }, $this->service->get_paginated( 9999, 0 ) );

        wp_send_json_success( array( 'html' => $html, 'items' => $items ) );
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
        if ( ! self::is_settings_tab_request() ) {
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
            wp_safe_redirect( esc_url_raw( self::get_admin_url( array( 'notice' => 'error', 'message' => $message ) ) ) );
            exit;
        }

        $this->service->delete_tag($tag_id);

        wp_safe_redirect( esc_url_raw( self::get_admin_url( array( 'notice' => 'success', 'message' => __( 'Tag deleted.', 'volunteer-exchange-platform' ) ) ) ) );
        exit;
    }
}
