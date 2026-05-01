<?php
/**
 * Events admin page
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantTypeService;
use VolunteerExchangePlatform\Services\TagService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Events admin page
 *
 * Handles the admin interface for managing events including listing,
 * adding, editing, viewing, and deleting events with associated data.
 *
 * @package VolunteerExchangePlatform\Admin
 */
class EventsPage {
    /**
     * @var EventService
     */
    private $event_service;

    /**
     * @var ParticipantTypeService
     */
    private $type_service;

    /**
     * @var TagService
     */
    private $tag_service;

    /**
     * Constructor.
     *
     * @param EventService|null           $event_service Event service instance.
     * @param ParticipantTypeService|null $type_service Participant type service instance.
     * @param TagService|null             $tag_service Tag service instance.
     * @return void
     */
    public function __construct( ?EventService $event_service = null, ?ParticipantTypeService $type_service = null, ?TagService $tag_service = null ) {
        $this->event_service = $event_service ?: new EventService();
        $this->type_service = $type_service ?: new ParticipantTypeService();
        $this->tag_service = $tag_service ?: new TagService();
        add_action('admin_init', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_quick_action'));
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
        $event_id_raw = filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );
        $action = $action_raw ? sanitize_key( $action_raw ) : 'list';
        $event_id = $event_id_raw ? absint( $event_id_raw ) : 0;

        switch ($action) {
            case 'add':
                $this->render_form();
                break;
            case 'edit':
                $this->render_form($event_id);
                break;
            case 'view':
                $this->render_view($event_id);
                break;
            case 'edit-agreement':
                $this->render_edit_agreement($event_id);
                break;
            default:
                $this->render_list();
                break;
        }
    }

    /**
     * Render list of events
     *
     * Displays the WP_List_Table with all events and success/warning messages.
     * Shows a warning if no participant types or tags exist.
     *
     * @return void
     */
    private function render_list() {
        $types_count = $this->type_service->count_all();
        $tags_count = $this->tag_service->count_all();
        $can_create_event = ($types_count > 0 && $tags_count > 0);
        $message = filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_raw = filter_input( INPUT_GET, 'notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_type = $notice_raw ? sanitize_key( $notice_raw ) : 'success';

        $list_table = new \VolunteerExchangePlatform\Admin\EventsListTable($this->event_service);
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Events', 'volunteer-exchange-platform'); ?></h1>

            <?php if ($can_create_event): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-events&action=add')); ?>" class="page-title-action">
                    <?php esc_html_e('Add New', 'volunteer-exchange-platform'); ?>
                </a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php if (!$can_create_event): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        echo esc_html__(
                            'Before you can create an event, you must create at least one Participant Type and at least one We Offer Tag.',
                            'volunteer-exchange-platform'
                        );
                        ?>
                    </p>
                    <p>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-participant-types')); ?>">
                            <?php echo esc_html__('Go to Participant Types', 'volunteer-exchange-platform'); ?>
                        </a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-tags')); ?>">
                            <?php echo esc_html__('Go to We Offer Tags', 'volunteer-exchange-platform'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

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
     * Render form for adding or editing an event
     *
     * Validates that participant types and tags exist before allowing creation
     *
     * @param int $event_id The event ID (0 for new event)
     * @return void
     */
    private function render_form($event_id = 0) {
        $types_count = $this->type_service->count_all();
        $tags_count = $this->tag_service->count_all();
        $can_create_event = ($types_count > 0 && $tags_count > 0);
        
        $event = null;
        if ($event_id > 0) {
            $event = $this->event_service->get_by_id($event_id);
        }
        
        $title = $event ? __('Edit Event', 'volunteer-exchange-platform') : __('Add New Event', 'volunteer-exchange-platform');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>

            <?php if ($event_id === 0 && !$can_create_event): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        echo esc_html__(
                            'Before you can create an event, you must create at least one Participant Type and at least one We Offer Tag.',
                            'volunteer-exchange-platform'
                        );
                        ?>
                    </p>
                    <p>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-participant-types')); ?>">
                            <?php echo esc_html__('Go to Participant Types', 'volunteer-exchange-platform'); ?>
                        </a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-tags')); ?>">
                            <?php echo esc_html__('Go to We Offer Tags', 'volunteer-exchange-platform'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('vep_event_form', 'vep_event_nonce'); ?>
                <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="event_name"><?php esc_html_e('Event Name', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="event_name" name="event_name" class="regular-text" 
                                   value="<?php echo $event ? esc_attr($event->name) : ''; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="event_description"><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <textarea id="event_description" name="event_description" rows="5" class="large-text"><?php echo $event ? esc_textarea($event->description ?? '') : ''; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="start_date"><?php esc_html_e('Start Date', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" id="start_date" name="start_date" 
                                   value="<?php echo $event && $event->start_date ? esc_attr(str_replace(' ', 'T', substr((string) $event->start_date, 0, 16))) : ''; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="end_date"><?php esc_html_e('End Date', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" id="end_date" name="end_date" 
                                   value="<?php echo $event && $event->end_date ? esc_attr(str_replace(' ', 'T', substr((string) $event->end_date, 0, 16))) : ''; ?>" required>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr($event ? __('Update Event', 'volunteer-exchange-platform') : __('Create Event', 'volunteer-exchange-platform')); ?>" <?php echo ($event_id === 0 && !$can_create_event) ? esc_attr('disabled') : ''; ?>>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-events')); ?>" class="button">
                        <?php esc_html_e('Cancel', 'volunteer-exchange-platform'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render view event with agreements and statistics
     *
     * Displays event details, participant counts, and all agreements for this event
     *
     * @param int $event_id The event ID to view
     * @return void
     */
    private function render_view($event_id) {
        $event = $this->event_service->get_by_id($event_id);
        
        if (!$event) {
            echo '<div class="wrap"><p>' . esc_html__('Event not found.', 'volunteer-exchange-platform') . '</p></div>';
            return;
        }
        
        // Get agreements for this event
        $agreements = $this->event_service->get_agreements_for_event($event_id);
        $stats = $this->event_service->get_event_stats($event_id);
        $expected_total = $stats['expected_total'];
        $approved_participants_count = $stats['approved_count'];
        $expected_count_rows = $stats['expected_count_rows'];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($event->name); ?></h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-events')); ?>">&larr; <?php esc_html_e('Back to Events', 'volunteer-exchange-platform'); ?></a></p>
            
            <h2><?php esc_html_e('Event Details', 'volunteer-exchange-platform'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($event->description ?? ''); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Approved Participants', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <?php
                        if ($approved_participants_count === 0) {
                            echo '<span style="color: #999;">—</span>';
                        } else {
                            echo esc_html(number_format_i18n($approved_participants_count));
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Expected Participants (Approved)', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <?php
                        if ($expected_count_rows === 0) {
                            echo '<span style="color: #999;">—</span>';
                        } else {
                            echo esc_html(number_format_i18n($expected_total));
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Start Date', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event->start_date))); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('End Date', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event->end_date))); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Status', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo $event->is_active ? '<span style="color: green;">' . esc_html__('Active', 'volunteer-exchange-platform') . '</span>' : esc_html__('Inactive', 'volunteer-exchange-platform'); ?></td>
                </tr>
            </table>
            
            <h2><?php esc_html_e('Agreements for this Event', 'volunteer-exchange-platform'); ?></h2>
            <?php if (empty($agreements)): ?>
                <p><?php esc_html_e('No agreements yet.', 'volunteer-exchange-platform'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Participant 1', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Participant 2', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Initiator', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Created', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Actions', 'volunteer-exchange-platform'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agreements as $agreement): ?>
                            <tr>
                                <td><?php echo esc_html($agreement->id); ?></td>
                                <td><?php echo esc_html($agreement->participant1_name); ?></td>
                                <td><?php echo esc_html($agreement->participant2_name); ?></td>
                                <td><?php echo esc_html($agreement->initiator_name); ?></td>
                                <td><?php echo esc_html(wp_trim_words($agreement->description, 10)); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($agreement->created_at))); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-events&action=edit-agreement&id=' . $agreement->id . '&return_id=' . $event_id)); ?>">
                                        <?php esc_html_e('Edit', 'volunteer-exchange-platform'); ?>
                                    </a>
                                    <a class="button button-small" style="margin-left: 6px;" href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=volunteer-exchange-events&action=delete-agreement&id=' . $agreement->id . '&return_id=' . $event_id), 'vep_agreement_delete_' . $agreement->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __('Are you sure you want to delete this agreement?', 'volunteer-exchange-platform') ); ?>');">
                                        <?php esc_html_e('Delete', 'volunteer-exchange-platform'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render agreement edit form
     *
     * @param int $agreement_id The agreement ID to edit
     * @return void
     */
    private function render_edit_agreement($agreement_id) {
        $return_id = absint( filter_input( INPUT_GET, 'return_id', FILTER_VALIDATE_INT ) );

        $agreement = $this->event_service->get_agreement_by_id($agreement_id);
        
        if (!$agreement) {
            echo '<div class="wrap"><p>' . esc_html__('Agreement not found.', 'volunteer-exchange-platform') . '</p></div>';
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Edit Agreement', 'volunteer-exchange-platform'); ?></h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-events')); ?>">&larr; <?php esc_html_e('Back to Events', 'volunteer-exchange-platform'); ?></a></p>
            
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('ID', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($agreement->id); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Participant 1', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($agreement->participant1_name ?? ''); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Participant 2', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($agreement->participant2_name ?? ''); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Initiator', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <select name="initiator_id" id="initiator_id" form="vep-agreement-edit-form">
                            <option value="<?php echo esc_attr( (int) ( $agreement->participant1_id ?? 0 ) ); ?>" <?php selected( (int) ( $agreement->initiator_id ?? 0 ), (int) ( $agreement->participant1_id ?? 0 ) ); ?>>
                                <?php echo esc_html($agreement->participant1_name ?? ''); ?>
                            </option>
                            <option value="<?php echo esc_attr( (int) ( $agreement->participant2_id ?? 0 ) ); ?>" <?php selected( (int) ( $agreement->initiator_id ?? 0 ), (int) ( $agreement->participant2_id ?? 0 ) ); ?>>
                                <?php echo esc_html($agreement->participant2_name ?? ''); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Created', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($agreement->created_at))); ?></td>
                </tr>
                <tr>
                    <th><label for="description"><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></label></th>
                    <td>
                        <form id="vep-agreement-edit-form" method="post" action="">
                            <?php wp_nonce_field('vep_agreement_edit', 'vep_agreement_nonce'); ?>
                            <input type="hidden" name="agreement_id" value="<?php echo esc_attr($agreement->id); ?>">
                            <input type="hidden" name="return_id" value="<?php echo esc_attr($return_id); ?>">
                            <textarea id="description" name="description" rows="5" class="large-text"><?php echo esc_textarea($agreement->description ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Update the description of this agreement.', 'volunteer-exchange-platform'); ?></p>
                            <p class="submit">
                                <input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e('Update Agreement', 'volunteer-exchange-platform'); ?>">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-events')); ?>" class="button">
                                    <?php esc_html_e('Cancel', 'volunteer-exchange-platform'); ?>
                                </a>
                            </p>
                        </form>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Handle form submission for creating or updating an event
     * 
     * Validates nonce, checks permissions, and saves the event data.
     * New events are automatically set as active and deactivate other events.
     * 
     * @return void
     */
    public function handle_form_submission() {
        // Only process on our page
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'volunteer-exchange-events' !== $page ) {
            return;
        }

        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
        if ( 'POST' !== strtoupper( $request_method ) ) {
            return;
        }

        // Check for agreement form submission
        $agreement_nonce = isset( $_POST['vep_agreement_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vep_agreement_nonce'] ) ) : '';
        if ( ! empty( $agreement_nonce ) ) {
            if ( ! wp_verify_nonce( $agreement_nonce, 'vep_agreement_edit' ) ) {
                wp_die(esc_html__('Security check failed.', 'volunteer-exchange-platform'));
            }
            
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
            }
            
            $agreement_id = isset( $_POST['agreement_id'] ) ? absint( wp_unslash( $_POST['agreement_id'] ) ) : 0;
            if ( $agreement_id > 0 ) {
                $description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
                $update_data = array( 'description' => $description );
                if ( isset( $_POST['initiator_id'] ) ) {
                    $initiator_id = absint( wp_unslash( $_POST['initiator_id'] ) );
                    if ( $initiator_id > 0 ) {
                        $update_data['initiator_id'] = $initiator_id;
                    }
                }
                $this->event_service->update_agreement(
                    $agreement_id,
                    $update_data
                );
                $return_id = isset( $_POST['return_id'] ) ? absint( wp_unslash( $_POST['return_id'] ) ) : 0;
                if ( $return_id > 0 ) {
                    wp_safe_redirect(admin_url('admin.php?page=volunteer-exchange-events&action=view&id=' . $return_id . '&message=' . urlencode(__('Agreement updated successfully.', 'volunteer-exchange-platform'))));
                } else {
                    wp_safe_redirect(admin_url('admin.php?page=volunteer-exchange-events&message=' . urlencode(__('Agreement updated successfully.', 'volunteer-exchange-platform'))));
                }
                exit;
            }
            return;
        }

        $nonce = isset( $_POST['vep_event_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vep_event_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_event_form' ) ) {
            wp_die(esc_html__('Security check failed.', 'volunteer-exchange-platform'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }
        
        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        $name = isset( $_POST['event_name'] ) ? sanitize_text_field( wp_unslash( $_POST['event_name'] ) ) : '';
        $description = isset( $_POST['event_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['event_description'] ) ) : '';
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
        
        if ($event_id > 0) {
            // Update existing event
            $this->event_service->update_event(
                $event_id,
                array(
                    'name' => $name,
                    'description' => $description,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                )
            );
            $message = __('Event updated successfully.', 'volunteer-exchange-platform');
        } else {
            $types_count = $this->type_service->count_all();
            $tags_count = $this->tag_service->count_all();
            if ($types_count < 1 || $tags_count < 1) {
                $message = __('Before you can create an event, you must create at least one Participant Type and at least one We Offer Tag.', 'volunteer-exchange-platform');
                wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-events&notice=error&message=' . urlencode($message))));
                exit;
            }

            $this->event_service->create_active(
                array(
                    'name' => $name,
                    'description' => $description,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                )
            );
            $message = __('Event created successfully and set as active.', 'volunteer-exchange-platform');
        }
        
        wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-events&notice=success&message=' . urlencode($message))));
        exit;
    }
    
    /**
     * Handle quick actions for events (delete, deactivate)
     * 
     * Validates permissions and performs the requested action.
     * Delete operation cascades to all associated data (participants, agreements, tags).
     * 
     * @return void
     */
    public function handle_quick_action() {
        // Only process on our page
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'volunteer-exchange-events' !== $page ) {
            return;
        }

        if ( ! isset( $_GET['action'], $_GET['id'] ) ) {
            return;
        }

        $action = sanitize_key( wp_unslash( $_GET['action'] ) );
        $event_id = absint( wp_unslash( $_GET['id'] ) );
        
        if (!in_array($action, array('delete', 'deactivate', 'delete-agreement'))) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }

        $nonce_action = 'delete-agreement' === $action
            ? 'vep_agreement_delete_' . $event_id
            : 'vep_event_' . $action . '_' . $event_id;

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
            $message = __('Security check failed.', 'volunteer-exchange-platform');
            wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-events&notice=error&message=' . urlencode($message))));
            exit;
        }
        
        if ($action === 'delete') {
            $this->event_service->delete_event_with_related($event_id);
            
            $message = __('Event and all associated data deleted successfully.', 'volunteer-exchange-platform');
        } elseif ($action === 'deactivate') {
            $this->event_service->deactivate($event_id);
            $message = __('Event deactivated.', 'volunteer-exchange-platform');
        } elseif ($action === 'delete-agreement') {
            $result = $this->event_service->delete_agreement($event_id);
            $notice = $result ? 'success' : 'error';
            $message = $result ? __('Agreement deleted.', 'volunteer-exchange-platform') : __('Could not delete agreement.', 'volunteer-exchange-platform');
            $return_id = isset( $_GET['return_id'] ) ? absint( wp_unslash( $_GET['return_id'] ) ) : 0;
            if ( $return_id > 0 ) {
                wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-events&action=view&id=' . $return_id . '&notice=' . $notice . '&message=' . urlencode($message))));
            } else {
                wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-events&notice=' . $notice . '&message=' . urlencode($message))));
            }
            exit;
        }
        
        wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=volunteer-exchange-events&notice=success&message=' . urlencode($message))));
        exit;
    }
}
