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
    public const SETTINGS_PAGE_SLUG = 'vep-settings';
    public const SETTINGS_TAB = 'participant-types';

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
        add_action( 'admin_init', array( $this, 'handle_delete' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_vep_save_participant_type', array( $this, 'save_ajax' ) );
        add_action( 'wp_ajax_vep_get_types_table', array( $this, 'render_table_ajax' ) );
    }

    /**
     * Enqueue admin assets for participant types page.
     *
     * @param string $hook Current admin hook.
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( ! ( false !== strpos( $hook, self::SETTINGS_PAGE_SLUG ) && self::is_settings_tab_request() ) ) {
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
     * Render list of participant types
     *
     * Displays the WP_List_Table with all participant types and success messages
     *
     * @return void
     */
    private function render_list( $embedded = false ) {
        $list_table = new \VolunteerExchangePlatform\Admin\ParticipantTypesListTable( $this->service );
        $list_table->prepare_items();
        $message    = filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_raw = filter_input( INPUT_GET, 'notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_type = $notice_raw ? sanitize_key( $notice_raw ) : 'success';

        // Fetch all types for modal pre-fill.
        $all_types  = $this->service->get_paginated( 9999, 0 );
        $types_json = wp_json_encode(
            array_map( function ( $t ) {
                return array(
                    'id'          => (int) $t->id,
                    'name'        => (string) $t->name,
                    'description' => isset( $t->description ) ? (string) $t->description : '',
                    'icon'        => isset( $t->icon )        ? (string) $t->icon        : '',
                    'color'       => isset( $t->color )       ? (string) $t->color       : '',
                );
            }, $all_types )
        );
        ?>
        <div class="<?php echo $embedded ? 'vep-settings-tab-panel' : 'wrap'; ?>">
            <<?php echo $embedded ? 'h2' : 'h1'; ?> class="wp-heading-inline"><?php esc_html_e( 'Participant Types', 'volunteer-exchange-platform' ); ?></<?php echo $embedded ? 'h2' : 'h1'; ?>>
            <button type="button" class="page-title-action" id="vep-type-add-btn">
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

            <div id="vep-types-table-wrap" class="vep-ajax-wrap"><?php $list_table->display(); ?></div>
        </div>

        <!-- Participant type modal -->
        <div class="vep-modal-overlay" id="vep-type-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="vep-type-modal-title">
            <div class="vep-modal-container">
                <div class="vep-modal-header">
                    <h2 id="vep-type-modal-title"><?php esc_html_e( 'Add New Participant Type', 'volunteer-exchange-platform' ); ?></h2>
                    <button type="button" class="vep-modal-close" aria-label="<?php esc_attr_e( 'Close', 'volunteer-exchange-platform' ); ?>">&times;</button>
                </div>
                <div class="vep-modal-body">
                    <form class="vep-category-form" id="vep-type-modal-form">
                        <?php wp_nonce_field( 'vep_admin_nonce', 'vep_admin_nonce_type_field' ); ?>
                        <input type="hidden" id="vep-type-modal-id" name="type_id" value="0">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="type_icon"><?php esc_html_e( 'Icon', 'volunteer-exchange-platform' ); ?></label></th>
                                <td>
                                    <div class="vep-icon-picker">
                                        <input type="hidden" id="type_icon" name="type_icon" class="vep-icon-input" value="">
                                        <span class="vep-icon-preview" id="icon-preview">
                                            <span class="vep-icon-placeholder"><?php esc_html_e( 'Please select icon', 'volunteer-exchange-platform' ); ?></span>
                                        </span>
                                        <button type="button" id="open-icon-picker" class="button"><?php esc_html_e( 'Select Icon', 'volunteer-exchange-platform' ); ?></button>
                                        <button type="button" id="clear-icon-picker" class="button button-secondary" style="margin-left:5px;"><?php esc_html_e( 'Clear', 'volunteer-exchange-platform' ); ?></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="type_name"><?php esc_html_e( 'Name', 'volunteer-exchange-platform' ); ?></label></th>
                                <td><input type="text" id="type_name" name="type_name" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="type_color"><?php esc_html_e( 'Color', 'volunteer-exchange-platform' ); ?></label></th>
                                <td>
                                    <input type="text" id="type_color" name="type_color" class="regular-text vep-color-picker" value="">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="type_description"><?php esc_html_e( 'Description', 'volunteer-exchange-platform' ); ?></label></th>
                                <td><textarea id="type_description" name="type_description" rows="4" class="large-text"></textarea></td>
                            </tr>
                        </table>
                    </form>
                </div>
                <div class="vep-modal-footer">
                    <button type="button" class="button button-primary" id="vep-type-modal-save"><?php esc_html_e( 'Save', 'volunteer-exchange-platform' ); ?></button>
                    <button type="button" class="button vep-modal-close"><?php esc_html_e( 'Cancel', 'volunteer-exchange-platform' ); ?></button>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var overlay    = document.getElementById('vep-type-modal-overlay');
            var modalTitle = document.getElementById('vep-type-modal-title');
            var idField    = document.getElementById('vep-type-modal-id');
            var nameField  = document.getElementById('type_name');
            var descField  = document.getElementById('type_description');
            var iconField  = document.getElementById('type_icon');
            var iconPreview= document.getElementById('icon-preview');
            var colorField = document.getElementById('type_color');
            var saveBtn    = document.getElementById('vep-type-modal-save');
            var nonce      = document.getElementById('vep_admin_nonce_type_field').value;
            var typesData  = <?php echo $types_json; ?>;
            var labelAdd   = <?php echo wp_json_encode( __( 'Add New Participant Type', 'volunteer-exchange-platform' ) ); ?>;
            var labelEdit  = <?php echo wp_json_encode( __( 'Edit Participant Type', 'volunteer-exchange-platform' ) ); ?>;
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

            function setColor(val) {
                if (window.jQuery && jQuery(colorField).hasClass('wp-color-picker')) {
                    jQuery(colorField).wpColorPicker('color', val || '');
                } else {
                    colorField.value = val || '';
                }
            }

            function openModal(type) {
                if (type) {
                    modalTitle.textContent = labelEdit;
                    idField.value   = type.id;
                    nameField.value = type.name;
                    descField.value = type.description;
                    iconField.value = type.icon;
                    renderIconPreview(type.icon);
                    setColor(type.color);
                } else {
                    modalTitle.textContent = labelAdd;
                    idField.value   = 0;
                    nameField.value = '';
                    descField.value = '';
                    iconField.value = '';
                    renderIconPreview('');
                    setColor('');
                }
                overlay.style.display = 'flex';
                nameField.focus();
            }

            function closeModal() {
                overlay.style.display = 'none';
            }

            // Initialize WP color picker inside modal.
            document.addEventListener('DOMContentLoaded', function () {
                if (window.jQuery && jQuery.fn.wpColorPicker) {
                    jQuery('#type_color').wpColorPicker();
                }
            });

            // Add New button.
            document.getElementById('vep-type-add-btn').addEventListener('click', function () {
                openModal(null);
            });

            // Edit buttons (delegated).
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.vep-type-edit-btn');
                if (!btn) return;
                e.preventDefault();
                var id   = parseInt(btn.dataset.id, 10);
                var type = typesData.find(function (t) { return t.id === id; });
                openModal(type || null);
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
                var form = document.getElementById('vep-type-modal-form');
                if (!form.reportValidity()) return;
                saveBtn.disabled = true;
                var colorVal = (window.jQuery && jQuery(colorField).hasClass('wp-color-picker'))
                    ? jQuery(colorField).wpColorPicker('color')
                    : colorField.value;
                var data = new FormData();
                data.append('action', 'vep_save_participant_type');
                data.append('nonce', nonce);
                data.append('type_id', idField.value);
                data.append('type_name', nameField.value);
                data.append('type_description', descField.value);
                data.append('type_icon', iconField.value);
                data.append('type_color', colorVal);
                fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        saveBtn.disabled = false;
                        closeModal();
                        if (window.vepToast) window.vepToast(res.data && res.data.message ? res.data.message : '');
                        if (res.success) {
                            if (window.jQuery) window.jQuery(window).off('beforeunload');
                            var wrap = document.getElementById('vep-types-table-wrap');
                            if (wrap) wrap.classList.add('is-loading');
                            var refresh = new FormData();
                            refresh.append('action', 'vep_get_types_table');
                            refresh.append('nonce', nonce);
                            fetch(ajaxUrl, { method: 'POST', body: refresh, credentials: 'same-origin' })
                                .then(function (r2) { return r2.json(); })
                                .then(function (res2) {
                                    if (wrap) wrap.classList.remove('is-loading');
                                    if (res2.success) {
                                        wrap.innerHTML = res2.data.html;
                                        typesData = res2.data.items;
                                    }
                                })
                                .catch(function () { if (wrap) wrap.classList.remove('is-loading'); });
                        }
                    })
                    .catch(function () { saveBtn.disabled = false; });
            });

            // ESC key.
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && overlay.style.display !== 'none') closeModal();
            });

            // Table navigation (pagination + sort) via AJAX — no page reload.
            document.addEventListener('click', function (e) {
                var wrap = document.getElementById('vep-types-table-wrap');
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
                params.append('action', 'vep_get_types_table');
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
                            typesData = res.data.items;
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
     * Render form for adding or editing a participant type
     *
     * @param int $type_id The participant type ID (0 for new type)
     * @return void
     */
    private function render_form($type_id = 0, $embedded = false) {
        $type = null;
        if ($type_id > 0) {
            $type = $this->service->get_by_id($type_id);
        }

        $title = $type ? __('Edit Participant Type', 'volunteer-exchange-platform') : __('Add New Participant Type', 'volunteer-exchange-platform');
        ?>
        <div class="<?php echo $embedded ? 'vep-settings-tab-panel' : 'wrap'; ?>">
            <<?php echo $embedded ? 'h2' : 'h1'; ?>><?php echo esc_html($title); ?></<?php echo $embedded ? 'h2' : 'h1'; ?>>
            
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
                    <a href="<?php echo esc_url( self::get_admin_url() ); ?>" class="button">
                        <?php esc_html_e('Cancel', 'volunteer-exchange-platform'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle AJAX save (add or update) for a participant type.
     *
     * @return void
     */
    public function save_ajax() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'volunteer-exchange-platform' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'volunteer-exchange-platform' ) ) );
        }

        $type_id = isset( $_POST['type_id'] ) ? absint( wp_unslash( $_POST['type_id'] ) ) : 0;
        $data    = array(
            'name'        => isset( $_POST['type_name'] )        ? sanitize_text_field( wp_unslash( $_POST['type_name'] ) )            : '',
            'description' => isset( $_POST['type_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['type_description'] ) ) : '',
            'icon'        => isset( $_POST['type_icon'] )        ? sanitize_text_field( wp_unslash( $_POST['type_icon'] ) )            : '',
            'color'       => isset( $_POST['type_color'] )       ? sanitize_text_field( wp_unslash( $_POST['type_color'] ) )           : '',
        );

        if ( '' === $data['name'] ) {
            wp_send_json_error( array( 'message' => __( 'Name is required.', 'volunteer-exchange-platform' ) ) );
        }

        if ( $type_id > 0 ) {
            $this->service->update_type( $type_id, $data );
            $message = __( 'Type updated successfully.', 'volunteer-exchange-platform' );
        } else {
            $this->service->create( $data );
            $message = __( 'Type created successfully.', 'volunteer-exchange-platform' );
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

        $list_table = new \VolunteerExchangePlatform\Admin\ParticipantTypesListTable( $this->service );
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
                'color'       => isset( $t->color )       ? (string) $t->color       : '',
            );
        }, $this->service->get_paginated( 9999, 0 ) );

        wp_send_json_success( array( 'html' => $html, 'items' => $items ) );
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
        if ( ! self::is_settings_tab_request() ) {
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
            wp_safe_redirect( esc_url_raw( self::get_admin_url( array( 'notice' => 'error', 'message' => $message ) ) ) );
            exit;
        }

        $this->service->delete_type($type_id);

        wp_safe_redirect( esc_url_raw( self::get_admin_url( array( 'notice' => 'success', 'message' => __( 'Type deleted.', 'volunteer-exchange-platform' ) ) ) ) );
        exit;
    }
}
