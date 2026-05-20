<?php
/**
 * Events admin page
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Admin\EventAgreementsListTable;
use VolunteerExchangePlatform\Admin\EventParticipantsListTable;
use VolunteerExchangePlatform\Admin\ParticipantAgreementsListTable;
use VolunteerExchangePlatform\Services\CompetitionService;
use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;
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
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * @var TagService
     */
    private $tag_service;

    /**
     * @var CompetitionService
     */
    private $competition_service;

    /**
     * Constructor.
     *
     * @param EventService|null           $event_service Event service instance.
     * @param ParticipantService|null      $participant_service Participant service instance.
     * @param ParticipantTypeService|null $type_service Participant type service instance.
     * @param TagService|null             $tag_service Tag service instance.
     * @param CompetitionService|null     $competition_service Competition service instance.
     * @return void
     */
    public function __construct( ?EventService $event_service = null, ?ParticipantService $participant_service = null, ?ParticipantTypeService $type_service = null, ?TagService $tag_service = null, ?CompetitionService $competition_service = null ) {
        $this->event_service = $event_service ?: new EventService();
        $this->participant_service = $participant_service ?: new ParticipantService();
        $this->type_service = $type_service ?: new ParticipantTypeService();
        $this->tag_service = $tag_service ?: new TagService();
        $this->competition_service = $competition_service ?: new CompetitionService();
        add_action('admin_init', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_quick_action'));
        add_action('admin_init', array($this, 'handle_bulk_action'));
        add_action( 'wp_ajax_vep_get_active_participant_emails', array( $this, 'handle_get_active_participant_emails' ) );
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
            case 'add-participant':
                $this->render_edit_participant( 0 );
                break;
            case 'view-participant':
                $this->render_view_participant($event_id);
                break;
            case 'edit-participant':
                $this->render_edit_participant($event_id);
                break;
            case 'view-agreement':
                $this->render_view_agreement($event_id);
                break;
            case 'add-agreement':
                $this->render_edit_agreement( 0 );
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
     * Render an admin notice from the current request, if present.
     *
     * @return void
     */
    private function render_notice_from_query() {
        $message = filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_raw = filter_input( INPUT_GET, 'notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_type = $notice_raw ? sanitize_key( $notice_raw ) : 'success';

        if ( ! empty( $message ) ) {
            $this->clear_flash_notice();
        }

        if ( empty( $message ) ) {
            $flash_notice = $this->get_flash_notice();
            if ( empty( $flash_notice['message'] ) ) {
                return;
            }

            $message = $flash_notice['message'];
            $notice_type = $flash_notice['notice'];
        }
        ?>
        <div class="notice notice-<?php echo esc_attr( in_array( $notice_type, array( 'success', 'warning', 'error', 'info' ), true ) ? $notice_type : 'success' ); ?> is-dismissible">
            <p><?php echo esc_html( urldecode( (string) $message ) ); ?></p>
        </div>
        <?php
    }

    /**
     * Store a short-lived notice for the current admin user.
     *
     * @param string $notice_type Notice type.
     * @param string $message Notice message.
     * @return void
     */
    private function set_flash_notice( $notice_type, $message ) {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 || '' === $message ) {
            return;
        }

        set_transient(
            'vep_events_notice_' . $user_id,
            array(
                'notice'  => sanitize_key( (string) $notice_type ),
                'message' => (string) $message,
            ),
            MINUTE_IN_SECONDS
        );
    }

    /**
     * Retrieve and clear the current admin user's flash notice.
     *
     * @return array<string, string>
     */
    private function get_flash_notice() {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return array();
        }

        $transient_key = 'vep_events_notice_' . $user_id;
        $notice = get_transient( $transient_key );
        delete_transient( $transient_key );

        return is_array( $notice ) ? $notice : array();
    }

    /**
     * Clear the current admin user's flash notice.
     *
     * @return void
     */
    private function clear_flash_notice() {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return;
        }

        delete_transient( 'vep_events_notice_' . $user_id );
    }

    /**
     * Redirect to the events admin page with a consistent notice payload.
     *
     * @param string $notice Notice type.
     * @param string $message Notice message.
     * @param array  $args Additional query arguments.
     * @return void
     */
    private function redirect_with_notice( $notice, $message, array $args = array() ) {
        $notice = sanitize_key( (string) $notice );
        $message = (string) $message;

        if ( '' !== $message ) {
            $this->set_flash_notice( $notice, $message );
        }

        $query_args = array_merge(
            array(
                'page'    => 'volunteer-exchange-events',
                'notice'  => $notice,
                'message' => urlencode( $message ),
            ),
            $args
        );

        wp_safe_redirect( admin_url( 'admin.php?' . http_build_query( $query_args, '', '&' ) ) );
        exit;
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
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vep-email-settings&tab=participant-types')); ?>">
                            <?php echo esc_html__('Go to Participant Types', 'volunteer-exchange-platform'); ?>
                        </a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vep-email-settings&tab=tags')); ?>">
                            <?php echo esc_html__('Go to We Offer Tags', 'volunteer-exchange-platform'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <?php $this->render_notice_from_query(); ?>

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
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vep-email-settings&tab=participant-types')); ?>">
                            <?php echo esc_html__('Go to Participant Types', 'volunteer-exchange-platform'); ?>
                        </a>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=vep-email-settings&tab=tags')); ?>">
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
        
        $agreements_list_table = new EventAgreementsListTable( $event_id, $this->event_service );
        $agreements_list_table->prepare_items();

        $stats = $this->event_service->get_event_stats($event_id);
        $expected_total = $stats['expected_total'];
        $expected_count_rows = $stats['expected_count_rows'];
        $participants_list_table = new EventParticipantsListTable( $event_id, $this->participant_service, $this->event_service );
        $participants_list_table->prepare_items();
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

            <?php $this->render_notice_from_query(); ?>

            <details class="vep-collapsible-section" open>
                <summary>
                    <span class="vep-collapsible-heading"><?php esc_html_e( 'Participants', 'volunteer-exchange-platform' ); ?></span>
                    <a
                        href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-events&action=add-participant&return_id=' . $event_id ) ); ?>"
                        class="page-title-action"
                        onclick="event.stopPropagation();"
                    ><?php esc_html_e( 'Add New', 'volunteer-exchange-platform' ); ?></a>
                </summary>
                <div class="vep-collapsible-content">
                    <?php $participants_list_table->views(); ?>
                    <form method="get" id="vep-event-participants-form" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                        <input type="hidden" name="page" value="volunteer-exchange-events">
                        <input type="hidden" name="action" value="view">
                        <input type="hidden" name="id" value="<?php echo esc_attr( (string) $event_id ); ?>">
                        <input type="hidden" name="per_page" id="vep-per-page-hidden" data-per-page-hidden="1" value="<?php echo esc_attr( filter_input( INPUT_GET, 'per_page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ? sanitize_key( (string) filter_input( INPUT_GET, 'per_page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) : '25' ); ?>">
                        <?php $participants_list_table->display(); ?>
                    </form>
                </div>
            </details>

            <?php $this->render_participant_print_modal(); ?>
            
            <details class="vep-collapsible-section" open>
                <summary>
                    <span class="vep-collapsible-heading"><?php esc_html_e('Agreements for this Event', 'volunteer-exchange-platform'); ?></span>
                    <a
                        href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-events&action=add-agreement&return_id=' . $event_id ) ); ?>"
                        class="page-title-action"
                        onclick="event.stopPropagation();"
                    ><?php esc_html_e( 'Add New', 'volunteer-exchange-platform' ); ?></a>
                </summary>
                <div class="vep-collapsible-content">
                    <?php $agreements_list_table->views(); ?>
                    <form method="get" id="vep-event-agreements-form">
                        <input type="hidden" name="page" value="volunteer-exchange-events">
                        <input type="hidden" name="action" value="view">
                        <input type="hidden" name="id" value="<?php echo esc_attr( (string) $event_id ); ?>">
                        <input type="hidden" name="per_page" id="vep-agreements-per-page-hidden" data-per-page-hidden="1" value="<?php echo esc_attr( filter_input( INPUT_GET, 'per_page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ? sanitize_key( (string) filter_input( INPUT_GET, 'per_page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) : '25' ); ?>">
                        <?php $agreements_list_table->display(); ?>
                    </form>
                </div>
            </details>
        </div>
        <?php
    }

    /**
     * Render participant print modal on the event page.
     *
     * @return void
     */
    private function render_participant_print_modal() {
        $print_fields = array(
            'logo'               => __( 'Logo', 'volunteer-exchange-platform' ),
            'participant_number' => __( 'Participant Number', 'volunteer-exchange-platform' ),
            'organization_name'  => __( 'Organization Name', 'volunteer-exchange-platform' ),
            'description'        => __( 'Description', 'volunteer-exchange-platform' ),
            'type'               => __( 'Type', 'volunteer-exchange-platform' ),
            'expected_count'     => __( 'Expected Participants', 'volunteer-exchange-platform' ),
            'expected_names'     => __( 'Participant Names', 'volunteer-exchange-platform' ),
            'contact_person'     => __( 'Contact Person', 'volunteer-exchange-platform' ),
            'email'              => __( 'Email', 'volunteer-exchange-platform' ),
            'phone'              => __( 'Phone', 'volunteer-exchange-platform' ),
            'we_offer'           => __( 'We Offer', 'volunteer-exchange-platform' ),
            'status'             => __( 'Status', 'volunteer-exchange-platform' ),
            'missing_fields'     => __( 'Missing Fields', 'volunteer-exchange-platform' ),
        );

        $default_checked_fields = array(
            'participant_number',
            'organization_name',
            'expected_count',
            'expected_names',
        );
        ?>
        <div id="vep-print-modal" class="vep-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="vep-print-modal-title">
            <div class="vep-modal-container">
                <div class="vep-modal-header">
                    <h2 id="vep-print-modal-title"><?php esc_html_e( 'Print Participant', 'volunteer-exchange-platform' ); ?></h2>
                    <button type="button" id="vep-print-modal-close" class="vep-modal-close" aria-label="<?php esc_attr_e( 'Close', 'volunteer-exchange-platform' ); ?>">&times;</button>
                </div>

                <div class="vep-modal-body">
                    <div class="vep-print-section">
                        <h3><?php esc_html_e( 'Select Fields', 'volunteer-exchange-platform' ); ?></h3>
                        <p class="vep-print-hint"><?php esc_html_e( 'Check the fields to print and drag to reorder.', 'volunteer-exchange-platform' ); ?></p>
                        <ul id="vep-print-fields-list" class="vep-sortable-list">
                            <?php foreach ( $print_fields as $key => $label ) : ?>
                            <li class="vep-sortable-item" data-field="<?php echo esc_attr( $key ); ?>">
                                <span class="vep-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'volunteer-exchange-platform' ); ?>">&#8942;&#8942;</span>
                                <label>
                                    <input type="checkbox" class="vep-print-field-cb" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $default_checked_fields, true ) ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <label class="vep-print-checkbox-option">
                            <input type="checkbox" id="vep-print-show-checkbox">
                            <?php esc_html_e( 'Show checkbox (empty box for manual checking on paper)', 'volunteer-exchange-platform' ); ?>
                        </label>
                    </div>

                    <div class="vep-print-section vep-print-settings">
                        <div class="vep-print-setting-group">
                            <label for="vep-print-layout"><?php esc_html_e( 'Layout', 'volunteer-exchange-platform' ); ?></label>
                            <select id="vep-print-layout">
                                <option value="liste" selected><?php esc_html_e( 'List', 'volunteer-exchange-platform' ); ?></option>
                                <option value="box"><?php esc_html_e( 'Box', 'volunteer-exchange-platform' ); ?></option>
                                <option value="grid"><?php esc_html_e( 'Grid', 'volunteer-exchange-platform' ); ?></option>
                            </select>
                        </div>

                        <div class="vep-print-setting-group" id="vep-print-grid-cols-wrap" style="display:none;">
                            <label for="vep-print-grid-cols"><?php esc_html_e( 'Columns per row', 'volunteer-exchange-platform' ); ?></label>
                            <input type="number" id="vep-print-grid-cols" value="3" min="1" max="6" style="width:60px;">
                        </div>

                        <div class="vep-print-setting-group">
                            <label for="vep-print-orientation"><?php esc_html_e( 'Orientation', 'volunteer-exchange-platform' ); ?></label>
                            <select id="vep-print-orientation">
                                <option value="portrait" selected><?php esc_html_e( 'Portrait', 'volunteer-exchange-platform' ); ?></option>
                                <option value="landscape"><?php esc_html_e( 'Landscape', 'volunteer-exchange-platform' ); ?></option>
                            </select>
                        </div>

                        <div class="vep-print-setting-group">
                            <label for="vep-print-paper"><?php esc_html_e( 'Paper Format', 'volunteer-exchange-platform' ); ?></label>
                            <select id="vep-print-paper">
                                <option value="A5"><?php esc_html_e( 'A5', 'volunteer-exchange-platform' ); ?></option>
                                <option value="A4" selected><?php esc_html_e( 'A4', 'volunteer-exchange-platform' ); ?></option>
                                <option value="A3"><?php esc_html_e( 'A3', 'volunteer-exchange-platform' ); ?></option>
                            </select>
                        </div>

                        <label class="vep-print-checkbox-option vep-print-checkbox-full-width">
                            <input type="checkbox" id="vep-print-allow-cut">
                            <?php esc_html_e( 'Allow boxes to break across pages (save paper)', 'volunteer-exchange-platform' ); ?>
                        </label>
                    </div>
                </div>

                <div class="vep-modal-footer">
                    <button type="button" id="vep-print-cancel" class="button"><?php esc_html_e( 'Cancel', 'volunteer-exchange-platform' ); ?></button>
                    <button type="button" id="vep-print-submit" class="button button-primary"><?php esc_html_e( 'Print', 'volunteer-exchange-platform' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render participant details for the current event context.
     *
     * @param int $participant_id Participant ID.
     * @return void
     */
    private function render_view_participant( $participant_id ) {
        $return_id = absint( filter_input( INPUT_GET, 'return_id', FILTER_VALIDATE_INT ) );
        $back_url = $return_id > 0
            ? admin_url( 'admin.php?page=volunteer-exchange-events&action=view&id=' . $return_id )
            : admin_url( 'admin.php?page=volunteer-exchange-events' );

        $participant = $this->participant_service->get_by_id_with_details( $participant_id );
        if ( ! $participant ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Participant not found.', 'volunteer-exchange-platform' ) . '</p></div>';
            return;
        }

        $tags = $this->participant_service->get_tags_for_participant( $participant_id );
        $show_reminder_button = $this->participant_service->should_send_update_reminder( $participant_id );
        $tag_ids = $this->participant_service->get_tag_ids( $participant_id );
        $missing_update_fields = $this->participant_service->get_update_reminder_missing_fields( $participant, $tag_ids );
        $agreements_list_table = new ParticipantAgreementsListTable( $participant_id, $return_id, $this->participant_service );
        $agreements_list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $participant->organization_name ); ?></h1>
            <p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to Events', 'volunteer-exchange-platform' ); ?></a></p>

            <h2><?php esc_html_e( 'Participant Details', 'volunteer-exchange-platform' ); ?></h2>
            <table class="form-table">
                <?php if ( $participant->participant_number ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Participant Number', 'volunteer-exchange-platform' ); ?></th>
                    <td><strong style="font-size: 18px; color: #2271b1;">#<?php echo esc_html( $participant->participant_number ); ?></strong></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e( 'Type', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( $participant->type_name ?? '' ); ?></td>
                </tr>
                <?php if ( $participant->description ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Description', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo nl2br( esc_html( $participant->description ) ); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( isset( $participant->expected_participants_count ) && null !== $participant->expected_participants_count && '' !== $participant->expected_participants_count ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Participants Expected', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( $participant->expected_participants_count ); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( isset( $participant->expected_participants_names ) && ! empty( $participant->expected_participants_names ) ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Participant Names', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo nl2br( esc_html( $participant->expected_participants_names ) ); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e( 'Contact Person', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( $participant->contact_person_name ?? '' ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Email', 'volunteer-exchange-platform' ); ?></th>
                    <td>
                        <?php if ( ! empty( $participant->contact_email ) ) : ?>
                            <a href="<?php echo esc_attr( 'mailto:' . $participant->contact_email ); ?>">
                                <?php echo esc_html( $participant->contact_email ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Phone', 'volunteer-exchange-platform' ); ?></th>
                    <td>
                        <?php if ( ! empty( $participant->contact_phone ) ) : ?>
                            <a href="<?php echo esc_attr( 'tel:' . $participant->contact_phone ); ?>">
                                <?php echo esc_html( $participant->contact_phone ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ( ! empty( $participant->logo_url ) ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Logo', 'volunteer-exchange-platform' ); ?></th>
                    <td><img src="<?php echo esc_url( $participant->logo_url ); ?>" style="max-width: 200px; height: auto;"></td>
                </tr>
                <?php endif; ?>
                <?php if ( ! empty( $missing_update_fields ) ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Missing to fill in', 'volunteer-exchange-platform' ); ?></th>
                    <td>
                        <ul style="margin: 6px 0 0 18px; list-style: disc;">
                            <?php foreach ( $missing_update_fields as $missing_field ) : ?>
                                <li><?php echo esc_html( (string) ( $missing_field['name'] ?? '' ) ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ( $show_reminder_button ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Reminder', 'volunteer-exchange-platform' ); ?></th>
                    <td>
                        <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=volunteer-exchange-events&action=view&id=' . $return_id . '&participant_action=send_reminder&participant_id=' . $participant_id ), 'vep_participant_send_reminder_' . $participant_id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to send this reminder?', 'volunteer-exchange-platform' ) ); ?>');">
                            <?php esc_html_e( 'Send Reminder', 'volunteer-exchange-platform' ); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ( ! empty( $participant->is_approved ) ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Edit Link', 'volunteer-exchange-platform' ); ?></th>
                    <td>
                        <?php
                            $randon_key = isset( $participant->randon_key ) ? sanitize_text_field( (string) $participant->randon_key ) : '';
                            if ( method_exists( $this->participant_service, 'ensure_randon_key' ) ) {
                                $resolved_randon_key = call_user_func( array( $this->participant_service, 'ensure_randon_key' ), $participant_id );
                                if ( is_string( $resolved_randon_key ) && '' !== $resolved_randon_key ) {
                                    $randon_key = $resolved_randon_key;
                                }
                            }
                            $edit_url = \VolunteerExchangePlatform\Frontend\UpdateParticipantPage::get_update_participant_url( $randon_key );
                        ?>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button type="button" class="button button-secondary" onclick="vepCopyEditLink(<?php echo esc_attr( $participant_id ); ?>, this)">
                                <?php esc_html_e( 'Copy', 'volunteer-exchange-platform' ); ?>
                            </button>
                            <code id="vep-edit-link-<?php echo esc_attr( $participant_id ); ?>" style="padding: 6px 8px; background-color: #f5f5f5; border-radius: 3px; font-size: 12px; word-break: break-all;">
                                <?php echo esc_html( $edit_url ); ?>
                            </code>
                        </div>
                        <script>
                            function vepCopyEditLink(participantId, button) {
                                const codeElement = document.getElementById('vep-edit-link-' + participantId);
                                if (!codeElement || !button) return;

                                const text = codeElement.textContent;
                                navigator.clipboard.writeText(text).then(() => {
                                    const originalText = button.textContent;
                                    button.textContent = '<?php esc_html_e( 'Copied!', 'volunteer-exchange-platform' ); ?>';
                                    setTimeout(() => {
                                        button.textContent = originalText;
                                    }, 2000);
                                });
                            }
                        </script>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e( 'We Offer', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( ! empty( $tags ) ? implode( ', ', array_map( static function ( $tag ) { return $tag->name; }, $tags ) ) : __( 'No tags', 'volunteer-exchange-platform' ) ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Status', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo ! empty( $participant->is_approved ) ? '<span style="color: green;">' . esc_html__( 'Approved', 'volunteer-exchange-platform' ) . '</span>' : '<span style="color: orange;">' . esc_html__( 'Pending Approval', 'volunteer-exchange-platform' ) . '</span>'; ?></td>
                </tr>
            </table>

            <p>
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-events&action=edit-participant&id=' . $participant_id . '&return_id=' . $return_id ) ); ?>"><?php esc_html_e( 'Edit', 'volunteer-exchange-platform' ); ?></a>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=volunteer-exchange-events&action=view&id=' . $return_id . '&participant_action=delete&participant_id=' . $participant_id ), 'vep_participant_delete_' . $participant_id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this participant?', 'volunteer-exchange-platform' ) ); ?>');"><?php esc_html_e( 'Delete', 'volunteer-exchange-platform' ); ?></a>
            </p>

            <h2><?php esc_html_e( 'Agreements', 'volunteer-exchange-platform' ); ?></h2>
            <?php $agreements_list_table->views(); ?>
            <form method="get" id="vep-participant-agreements-form" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="volunteer-exchange-events">
                <input type="hidden" name="action" value="view-participant">
                <input type="hidden" name="id" value="<?php echo esc_attr( (string) $participant_id ); ?>">
                <input type="hidden" name="return_id" value="<?php echo esc_attr( (string) $return_id ); ?>">
                <?php $agreements_list_table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render participant edit form for the current event context.
     *
     * @param int $participant_id Participant ID.
     * @return void
     */
    private function render_edit_participant( $participant_id ) {
        $return_id = absint( filter_input( INPUT_GET, 'return_id', FILTER_VALIDATE_INT ) );
        $participant = $participant_id > 0 ? $this->participant_service->get_by_id( $participant_id ) : null;

        if ( $participant_id > 0 && ! $participant ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Participant not found.', 'volunteer-exchange-platform' ) . '</p></div>';
            return;
        }

        if ( $return_id <= 0 && $participant && isset( $participant->event_id ) ) {
            $return_id = absint( $participant->event_id );
        }

        if ( $return_id <= 0 ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Event not found.', 'volunteer-exchange-platform' ) . '</p></div>';
            return;
        }

        $selected_tags = $participant_id > 0 ? $this->participant_service->get_tag_ids( $participant_id ) : array();
        $types = $this->type_service->get_all_for_select();
        $tags = $this->tag_service->get_all_for_select();
        $is_edit = $participant_id > 0;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $is_edit ? __( 'Edit Participant', 'volunteer-exchange-platform' ) : __( 'Add New Participant', 'volunteer-exchange-platform' ) ); ?></h1>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-events&action=view&id=' . $return_id ) ); ?>">&larr; <?php esc_html_e( 'Back to Events', 'volunteer-exchange-platform' ); ?></a></p>

            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'vep_participant_form', 'vep_participant_nonce' ); ?>
                <input type="hidden" name="participant_id" value="<?php echo esc_attr( $participant_id ); ?>">
                <input type="hidden" name="return_id" value="<?php echo esc_attr( $return_id ); ?>">
                <input type="hidden" name="event_id" value="<?php echo esc_attr( $return_id ); ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="organization_name"><?php esc_html_e( 'Organization Name', 'volunteer-exchange-platform' ); ?></label></th>
                        <td><input type="text" id="organization_name" name="organization_name" class="regular-text" value="<?php echo esc_attr( $participant->organization_name ?? '' ); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description"><?php esc_html_e( 'Organization Description', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <textarea id="description" name="description" rows="5" class="large-text"><?php echo esc_textarea( $participant->description ?? '' ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Describe what this organization does.', 'volunteer-exchange-platform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="expected_participants_count"><?php esc_html_e( 'Participants Expected', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <input type="number" id="expected_participants_count" name="expected_participants_count" class="small-text" min="0" step="1" value="<?php echo isset( $participant->expected_participants_count ) && null !== $participant->expected_participants_count && '' !== $participant->expected_participants_count ? esc_attr( $participant->expected_participants_count ) : ''; ?>">
                            <p class="description"><?php esc_html_e( 'The number of participants expected to attend the event.', 'volunteer-exchange-platform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="expected_participants_names"><?php esc_html_e( 'Participant Names', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <textarea id="expected_participants_names" name="expected_participants_names" rows="3" class="large-text"><?php echo esc_textarea( $participant->expected_participants_names ?? '' ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'The names of the participants expected to attend the event.', 'volunteer-exchange-platform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="participant_type_id"><?php esc_html_e( 'Participant Type', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <select id="participant_type_id" name="participant_type_id" required>
                                <option value=""><?php esc_html_e( 'Select Type', 'volunteer-exchange-platform' ); ?></option>
                                <?php foreach ( $types as $type ) : ?>
                                    <option value="<?php echo esc_attr( $type->id ); ?>" <?php selected( $participant->participant_type_id ?? 0, $type->id ); ?>><?php echo esc_html( $type->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="contact_person_name"><?php esc_html_e( 'Contact Person Name', 'volunteer-exchange-platform' ); ?></label></th>
                        <td><input type="text" id="contact_person_name" name="contact_person_name" class="regular-text" value="<?php echo esc_attr( $participant->contact_person_name ?? '' ); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="contact_email"><?php esc_html_e( 'Contact Email', 'volunteer-exchange-platform' ); ?></label></th>
                        <td><input type="email" id="contact_email" name="contact_email" class="regular-text" value="<?php echo esc_attr( $participant->contact_email ?? '' ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="contact_phone"><?php esc_html_e( 'Contact Phone', 'volunteer-exchange-platform' ); ?></label></th>
                        <td><input type="tel" id="contact_phone" name="contact_phone" class="regular-text" value="<?php echo esc_attr( $participant->contact_phone ?? '' ); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="link"><?php esc_html_e( 'Link to homepage', 'volunteer-exchange-platform' ); ?></label></th>
                        <td><input type="url" id="link" name="link" class="regular-text" value="<?php echo esc_attr( $participant->link ?? '' ); ?>" placeholder="https://"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="logo"><?php esc_html_e( 'Logo', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <?php if ( ! empty( $participant->logo_url ) ) : ?>
                                <p><img src="<?php echo esc_url( $participant->logo_url ); ?>" style="max-width: 150px; height: auto;"></p>
                            <?php endif; ?>
                            <input type="file" id="logo" name="logo" accept="image/*">
                            <p class="description"><?php esc_html_e( 'Upload a new logo image.', 'volunteer-exchange-platform' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'We Offer Tags', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <?php foreach ( $tags as $tag ) : ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="tags[]" value="<?php echo esc_attr( $tag->id ); ?>" <?php checked( in_array( $tag->id, $selected_tags, true ) ); ?>>
                                    <?php echo esc_html( $tag->name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="is_approved"><?php esc_html_e( 'Approved', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_approved" name="is_approved" value="1" <?php checked( ! empty( $participant->is_approved ) ); ?>>
                                <?php esc_html_e( 'Approve this participant', 'volunteer-exchange-platform' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr( $is_edit ? __( 'Update Participant', 'volunteer-exchange-platform' ) : __( 'Create Participant', 'volunteer-exchange-platform' ) ); ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-events&action=view&id=' . $return_id ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'volunteer-exchange-platform' ); ?></a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render read-only agreement view.
     *
     * @param int $agreement_id Agreement ID.
     * @return void
     */
    private function render_view_agreement( $agreement_id ) {
        $return_id = absint( filter_input( INPUT_GET, 'return_id', FILTER_VALIDATE_INT ) );
        $return_participant_id = absint( filter_input( INPUT_GET, 'return_participant_id', FILTER_VALIDATE_INT ) );

        $agreement = $this->event_service->get_agreement_by_id( $agreement_id );

        if ( ! $agreement ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Agreement not found.', 'volunteer-exchange-platform' ) . '</p></div>';
            return;
        }

        $event_id = isset( $agreement->event_id ) ? absint( $agreement->event_id ) : 0;
        if ( $return_id <= 0 ) {
            $return_id = $event_id;
        }

        if ( $return_participant_id > 0 ) {
            $back_url = admin_url( 'admin.php?page=volunteer-exchange-events&action=view-participant&id=' . $return_participant_id . '&return_id=' . $return_id );
        } else {
            $back_url = $return_id > 0
                ? admin_url( 'admin.php?page=volunteer-exchange-events&action=view&id=' . $return_id )
                : admin_url( 'admin.php?page=volunteer-exchange-events' );
        }

        $delete_url = wp_nonce_url(
            admin_url(
                'admin.php?page=volunteer-exchange-events&action=delete-agreement&id=' . (int) $agreement->id . '&return_id=' . $return_id . '&return_participant_id=' . $return_participant_id
            ),
            'vep_agreement_delete_' . (int) $agreement->id
        );

        $status = isset( $agreement->status ) ? sanitize_key( (string) $agreement->status ) : 'active';
        $status_labels = array(
            'active'    => __( 'Awaiting', 'volunteer-exchange-platform' ),
            'completed' => __( 'Completed', 'volunteer-exchange-platform' ),
            'cancelled' => __( 'Cancelled', 'volunteer-exchange-platform' ),
        );
        $status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'View Agreement', 'volunteer-exchange-platform' ); ?></h1>
            <p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to Events', 'volunteer-exchange-platform' ); ?></a></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th><?php esc_html_e( 'ID', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( $agreement->id ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Participant 1', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( $agreement->participant1_name ?? '' ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Participant 2', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( $agreement->participant2_name ?? '' ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Initiator', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( $agreement->initiator_name ?? '' ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Status', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( $status_label ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Created', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $agreement->created_at ) ) ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Updated', 'volunteer-exchange-platform' ); ?></th>
                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $agreement->updated_at ) ) ); ?></td>
                </tr>
            </table>

            <div class="card" style="max-width: 900px; padding: 16px;">
                <h2><?php esc_html_e( 'Description', 'volunteer-exchange-platform' ); ?></h2>
                <div><?php echo wpautop( esc_html( (string) ( $agreement->description ?? '' ) ) ); ?></div>
            </div>

            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-events&action=edit-agreement&id=' . (int) $agreement->id . '&return_id=' . $return_id . '&return_participant_id=' . $return_participant_id ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Edit', 'volunteer-exchange-platform' ); ?>
                </a>
                <a href="<?php echo esc_url( $delete_url ); ?>" class="button" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this agreement?', 'volunteer-exchange-platform' ) ); ?>');">
                    <?php esc_html_e( 'Delete', 'volunteer-exchange-platform' ); ?>
                </a>
            </p>
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
        $return_participant_id = absint( filter_input( INPUT_GET, 'return_participant_id', FILTER_VALIDATE_INT ) );
        $is_edit = $agreement_id > 0;
        $agreement = $is_edit ? $this->event_service->get_agreement_by_id( $agreement_id ) : null;

        if ( $is_edit && ! $agreement ) {
            echo '<div class="wrap"><p>' . esc_html__('Agreement not found.', 'volunteer-exchange-platform') . '</p></div>';
            return;
        }

        if ( $return_id <= 0 && $agreement && isset( $agreement->event_id ) ) {
            $return_id = absint( $agreement->event_id );
        }

        if ( ! $is_edit && $return_id <= 0 ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Event not found.', 'volunteer-exchange-platform' ) . '</p></div>';
            return;
        }

        if ( $return_participant_id > 0 ) {
            $cancel_url = admin_url( 'admin.php?page=volunteer-exchange-events&action=view-participant&id=' . $return_participant_id . '&return_id=' . $return_id );
        } else {
            $cancel_url = $return_id > 0
                ? admin_url( 'admin.php?page=volunteer-exchange-events&action=view&id=' . $return_id )
                : admin_url( 'admin.php?page=volunteer-exchange-events' );
        }

        $participants = $return_id > 0 ? $this->participant_service->get_for_event_select( $return_id ) : array();
        $description = $agreement->description ?? '';
        $selected_initiator_side = 'participant1';

        if ( $agreement && isset( $agreement->initiator_id, $agreement->participant2_id ) && (int) $agreement->initiator_id === (int) $agreement->participant2_id ) {
            $selected_initiator_side = 'participant2';
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $is_edit ? __( 'Edit Agreement', 'volunteer-exchange-platform' ) : __( 'Add New Agreement', 'volunteer-exchange-platform' ) ); ?></h1>
            <p><a href="<?php echo esc_url($cancel_url); ?>">&larr; <?php esc_html_e('Back to Events', 'volunteer-exchange-platform'); ?></a></p>

            <?php $this->render_notice_from_query(); ?>

            <form id="vep-agreement-edit-form" method="post" action="">
                <?php wp_nonce_field('vep_agreement_edit', 'vep_agreement_nonce'); ?>
                <input type="hidden" name="agreement_id" value="<?php echo esc_attr( $agreement ? (int) $agreement->id : 0 ); ?>">
                <input type="hidden" name="return_id" value="<?php echo esc_attr($return_id); ?>">
                <input type="hidden" name="return_participant_id" value="<?php echo esc_attr($return_participant_id); ?>">

                <table class="form-table">
                    <?php if ( $is_edit ) : ?>
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
                    <?php else : ?>
                    <tr>
                        <th><label for="participant1_id"><?php esc_html_e('Participant 1', 'volunteer-exchange-platform'); ?></label></th>
                        <td>
                            <select name="participant1_id" id="participant1_id" class="vep-choices">
                                <option value=""><?php esc_html_e( 'Select participant', 'volunteer-exchange-platform' ); ?></option>
                                <?php foreach ( $participants as $participant_option ) : ?>
                                    <option value="<?php echo esc_attr( (int) $participant_option->id ); ?>"><?php echo esc_html( ( ! empty( $participant_option->participant_number ) ? '#' . (int) $participant_option->participant_number . ' - ' : '' ) . ( $participant_option->organization_name ?? '' ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="participant2_id"><?php esc_html_e('Participant 2', 'volunteer-exchange-platform'); ?></label></th>
                        <td>
                            <select name="participant2_id" id="participant2_id" class="vep-choices">
                                <option value=""><?php esc_html_e( 'Select participant', 'volunteer-exchange-platform' ); ?></option>
                                <?php foreach ( $participants as $participant_option ) : ?>
                                    <option value="<?php echo esc_attr( (int) $participant_option->id ); ?>"><?php echo esc_html( ( ! empty( $participant_option->participant_number ) ? '#' . (int) $participant_option->participant_number . ' - ' : '' ) . ( $participant_option->organization_name ?? '' ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php esc_html_e('Initiator', 'volunteer-exchange-platform'); ?></th>
                        <td>
                            <?php if ( $is_edit ) : ?>
                                <select name="initiator_id" id="initiator_id">
                                    <option value="<?php echo esc_attr( (int) ( $agreement->participant1_id ?? 0 ) ); ?>" <?php selected( (int) ( $agreement->initiator_id ?? 0 ), (int) ( $agreement->participant1_id ?? 0 ) ); ?>>
                                        <?php echo esc_html($agreement->participant1_name ?? ''); ?>
                                    </option>
                                    <option value="<?php echo esc_attr( (int) ( $agreement->participant2_id ?? 0 ) ); ?>" <?php selected( (int) ( $agreement->initiator_id ?? 0 ), (int) ( $agreement->participant2_id ?? 0 ) ); ?>>
                                        <?php echo esc_html($agreement->participant2_name ?? ''); ?>
                                    </option>
                                </select>
                            <?php else : ?>
                                <fieldset>
                                    <label><input type="radio" name="initiator_side" value="participant1" <?php checked( $selected_initiator_side, 'participant1' ); ?>> <?php esc_html_e( 'Participant 1', 'volunteer-exchange-platform' ); ?></label><br>
                                    <label><input type="radio" name="initiator_side" value="participant2" <?php checked( $selected_initiator_side, 'participant2' ); ?>> <?php esc_html_e( 'Participant 2', 'volunteer-exchange-platform' ); ?></label>
                                </fieldset>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ( $is_edit ) : ?>
                    <tr>
                        <th><?php esc_html_e('Created', 'volunteer-exchange-platform'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($agreement->created_at))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><label for="description"><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></label></th>
                        <td>
                            <textarea id="description" name="description" rows="5" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                            <p class="description"><?php echo esc_html( $is_edit ? __( 'Update the description of this agreement.', 'volunteer-exchange-platform' ) : __( 'Add the description for this agreement.', 'volunteer-exchange-platform' ) ); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_attr( $is_edit ? __( 'Update Agreement', 'volunteer-exchange-platform' ) : __( 'Create Agreement', 'volunteer-exchange-platform' ) ); ?>">
                    <?php if ( ! $is_edit ) : ?>
                        <input type="submit" name="submit_create_new" class="button button-primary" value="<?php echo esc_attr__( 'Create & New Agreement', 'volunteer-exchange-platform' ); ?>">
                    <?php endif; ?>
                    <a href="<?php echo esc_url($cancel_url); ?>" class="button">
                        <?php esc_html_e('Cancel', 'volunteer-exchange-platform'); ?>
                    </a>
                </p>
            </form>
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
        $participant_nonce = isset( $_POST['vep_participant_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vep_participant_nonce'] ) ) : '';

        if ( ! empty( $participant_nonce ) ) {
            if ( ! wp_verify_nonce( $participant_nonce, 'vep_participant_form' ) ) {
                wp_die( esc_html__( 'Security check failed.', 'volunteer-exchange-platform' ) );
            }

            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to perform this action.', 'volunteer-exchange-platform' ) );
            }

            $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
            $return_id = isset( $_POST['return_id'] ) ? absint( wp_unslash( $_POST['return_id'] ) ) : 0;
            $expected_count_raw = isset( $_POST['expected_participants_count'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_participants_count'] ) ) : '';
            $expected_count = '' !== $expected_count_raw ? absint( $expected_count_raw ) : null;
            $data = array(
                'event_id'                     => isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : $return_id,
                'organization_name'            => isset( $_POST['organization_name'] ) ? sanitize_text_field( wp_unslash( $_POST['organization_name'] ) ) : '',
                'description'                  => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
                'expected_participants_count'  => $expected_count,
                'expected_participants_names'  => isset( $_POST['expected_participants_names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['expected_participants_names'] ) ) : '',
                'participant_type_id'          => isset( $_POST['participant_type_id'] ) ? absint( wp_unslash( $_POST['participant_type_id'] ) ) : 0,
                'contact_person_name'          => isset( $_POST['contact_person_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_person_name'] ) ) : '',
                'contact_email'                => isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '',
                'contact_phone'                => isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '',
                'link'                         => isset( $_POST['link'] ) ? esc_url_raw( wp_unslash( $_POST['link'] ) ) : '',
                'is_approved'                  => isset( $_POST['is_approved'] ) ? 1 : 0,
            );

            $logo_name = isset( $_FILES['logo']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['logo']['name'] ) ) : '';
            if ( '' !== $logo_name ) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_handle_upload expects the raw $_FILES entry.
                $upload = wp_handle_upload( $_FILES['logo'], array( 'test_form' => false ) );
                if ( isset( $upload['url'] ) ) {
                    $data['logo_url'] = $upload['url'];
                }
            }

            $saved_participant_id = $participant_id;
            if ( $participant_id > 0 ) {
                $saved = $this->participant_service->update_participant( $participant_id, $data );
            } else {
                $saved_participant_id = $this->participant_service->create( $data );
                $saved = false !== $saved_participant_id;
            }

            if ( ! $saved || $saved_participant_id <= 0 ) {
                $this->redirect_with_notice(
                    'error',
                    __( 'Could not save participant.', 'volunteer-exchange-platform' ),
                    array(
                        'action' => 'view',
                        'id'     => $return_id,
                    )
                );
            }

            $tag_ids = array();
            if ( isset( $_POST['tags'] ) && is_array( $_POST['tags'] ) ) {
                $tag_ids = array_map( 'absint', wp_unslash( $_POST['tags'] ) );
            }
            $this->participant_service->replace_tags( $saved_participant_id, $tag_ids );

            $this->redirect_with_notice(
                'success',
                $participant_id > 0 ? __( 'Participant saved successfully.', 'volunteer-exchange-platform' ) : __( 'Participant created successfully.', 'volunteer-exchange-platform' ),
                array(
                    'action' => 'view',
                    'id'     => $return_id,
                )
            );
        }

        if ( ! empty( $agreement_nonce ) ) {
            if ( ! wp_verify_nonce( $agreement_nonce, 'vep_agreement_edit' ) ) {
                wp_die(esc_html__('Security check failed.', 'volunteer-exchange-platform'));
            }
            
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
            }
            
            $agreement_id = isset( $_POST['agreement_id'] ) ? absint( wp_unslash( $_POST['agreement_id'] ) ) : 0;
            $return_id = isset( $_POST['return_id'] ) ? absint( wp_unslash( $_POST['return_id'] ) ) : 0;
            $return_participant_id = isset( $_POST['return_participant_id'] ) ? absint( wp_unslash( $_POST['return_participant_id'] ) ) : 0;
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
                if ( $return_participant_id > 0 ) {
                    $this->redirect_with_notice(
                        'success',
                        __( 'Agreement updated successfully.', 'volunteer-exchange-platform' ),
                        array(
                            'action'                => 'view-participant',
                            'id'                    => $return_participant_id,
                            'return_id'             => $return_id,
                        )
                    );
                }
                if ( $return_id > 0 ) {
                    $this->redirect_with_notice(
                        'success',
                        __( 'Agreement updated successfully.', 'volunteer-exchange-platform' ),
                        array(
                            'action' => 'view',
                            'id'     => $return_id,
                        )
                    );
                } else {
                    $this->redirect_with_notice( 'success', __( 'Agreement updated successfully.', 'volunteer-exchange-platform' ) );
                }
            }

            $participant1_id = isset( $_POST['participant1_id'] ) ? absint( wp_unslash( $_POST['participant1_id'] ) ) : 0;
            $participant2_id = isset( $_POST['participant2_id'] ) ? absint( wp_unslash( $_POST['participant2_id'] ) ) : 0;
            $initiator_side = isset( $_POST['initiator_side'] ) ? sanitize_key( wp_unslash( $_POST['initiator_side'] ) ) : 'participant1';
            $create_new_after_save = isset( $_POST['submit_create_new'] );
            $initiator_id = 'participant2' === $initiator_side ? $participant2_id : $participant1_id;
            $description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

            $created_agreement_id = $this->event_service->create_agreement(
                array(
                    'event_id'        => $return_id,
                    'participant1_id' => $participant1_id,
                    'participant2_id' => $participant2_id,
                    'initiator_id'    => $initiator_id,
                    'description'     => $description,
                )
            );

            if ( $created_agreement_id ) {
                if ( $create_new_after_save ) {
                    $this->redirect_with_notice(
                        'success',
                        __( 'Agreement created successfully.', 'volunteer-exchange-platform' ),
                        array(
                            'action'    => 'add-agreement',
                            'return_id' => $return_id,
                        )
                    );
                }

                $this->redirect_with_notice(
                    'success',
                    __( 'Agreement created successfully.', 'volunteer-exchange-platform' ),
                    array(
                        'action' => 'view',
                        'id'     => $return_id,
                    )
                );
            }

            $this->redirect_with_notice(
                'error',
                __( 'Could not create agreement.', 'volunteer-exchange-platform' ),
                array(
                    'action'    => 'add-agreement',
                    'return_id' => $return_id,
                )
            );
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
        $message = '';
        
        if ($event_id > 0) {
            // Update existing event
            $updated = $this->event_service->update_event(
                $event_id,
                array(
                    'name' => $name,
                    'description' => $description,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                )
            );

            if ( false === $updated ) {
                $message = __( 'An error occurred. Please try again.', 'volunteer-exchange-platform' );
                $this->redirect_with_notice( 'error', $message );
            }

            $message = __('Event updated successfully.', 'volunteer-exchange-platform');
        } else {
            $types_count = $this->type_service->count_all();
            $tags_count = $this->tag_service->count_all();
            if ($types_count < 1 || $tags_count < 1) {
                $message = __('Before you can create an event, you must create at least one Participant Type and at least one We Offer Tag.', 'volunteer-exchange-platform');
                $this->redirect_with_notice( 'error', $message );
            }

            $created_event_id = $this->event_service->create_active(
                array(
                    'name' => $name,
                    'description' => $description,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                )
            );

            if ( $created_event_id ) {
                $this->competition_service->ensure_default_competitions();
                $message = __('Event created successfully and set as active.', 'volunteer-exchange-platform');
            } else {
                $message = __('Could not create event.', 'volunteer-exchange-platform');
                $this->redirect_with_notice( 'error', $message );
            }
        }

        $this->redirect_with_notice( 'success', $message );
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
        $object_id = absint( wp_unslash( $_GET['id'] ) );
        $message = '';
        
        if ( isset( $_GET['participant_action'], $_GET['participant_id'] ) ) {
            $participant_action = sanitize_key( wp_unslash( $_GET['participant_action'] ) );
            $participant_id = absint( wp_unslash( $_GET['participant_id'] ) );

            if ( in_array( $participant_action, array( 'approve', 'unapprove', 'delete', 'send_reminder' ), true ) && $participant_id > 0 ) {
                $participant_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
                $nonce_action = 'vep_participant_' . $participant_action . '_' . $participant_id;

                if ( ! wp_verify_nonce( $participant_nonce, $nonce_action ) ) {
                    $message = __( 'Security check failed.', 'volunteer-exchange-platform' );
                    $this->redirect_with_notice(
                        'error',
                        $message,
                        array(
                            'action' => 'view',
                            'id'     => $object_id,
                        )
                    );
                }

                if ( 'approve' === $participant_action ) {
                    $result = $this->participant_service->approve_participant( $participant_id );
                    $message = $result ? __( 'Participant approved.', 'volunteer-exchange-platform' ) : __( 'Could not approve participant.', 'volunteer-exchange-platform' );
                    $notice = $result ? 'success' : 'error';
                } elseif ( 'unapprove' === $participant_action ) {
                    $result = $this->participant_service->unapprove_participant( $participant_id );
                    $message = $result ? __( 'Participant unapproved. Note: Participant number is retained.', 'volunteer-exchange-platform' ) : __( 'Could not unapprove participant.', 'volunteer-exchange-platform' );
                    $notice = $result ? 'success' : 'error';
                } elseif ( 'send_reminder' === $participant_action ) {
                    $result = $this->participant_service->queue_update_participant_reminder( $participant_id );
                    $message = $result ? __( 'Reminder queued for sending.', 'volunteer-exchange-platform' ) : __( 'Reminder was not sent (participant may already be fully updated or missing required data).', 'volunteer-exchange-platform' );
                    $notice = $result ? 'success' : 'error';
                } else {
                    $result = $this->participant_service->delete_participant( $participant_id );
                    $message = $result ? __( 'Participant deleted.', 'volunteer-exchange-platform' ) : __( 'Could not delete participant.', 'volunteer-exchange-platform' );
                    $notice = $result ? 'success' : 'error';
                }

                $this->redirect_with_notice(
                    $notice,
                    $message,
                    array(
                        'action' => 'view',
                        'id'     => $object_id,
                    )
                );
            }
        }

        if (!in_array($action, array('delete', 'deactivate', 'delete-agreement'))) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }

        $return_id = isset( $_GET['return_id'] ) ? absint( wp_unslash( $_GET['return_id'] ) ) : 0;
        $return_participant_id = isset( $_GET['return_participant_id'] ) ? absint( wp_unslash( $_GET['return_participant_id'] ) ) : 0;

        if ( 'delete-agreement' === $action && $return_id <= 0 ) {
            $agreement = $this->event_service->get_agreement_by_id( $object_id );
            $return_id = $agreement && isset( $agreement->event_id ) ? absint( $agreement->event_id ) : 0;
        }

        $nonce_action = 'delete-agreement' === $action
            ? 'vep_agreement_delete_' . $object_id
            : 'vep_event_' . $action . '_' . $object_id;

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
            $message = __('Security check failed.', 'volunteer-exchange-platform');
            if ( 'delete-agreement' === $action && $return_participant_id > 0 ) {
                $this->redirect_with_notice(
                    'error',
                    $message,
                    array(
                        'action'                => 'view-participant',
                        'id'                    => $return_participant_id,
                        'return_id'             => $return_id,
                    )
                );
            } elseif ( 'delete-agreement' === $action && $return_id > 0 ) {
                $this->redirect_with_notice(
                    'error',
                    $message,
                    array(
                        'action' => 'view',
                        'id'     => $return_id,
                    )
                );
            } else {
                $this->redirect_with_notice( 'error', $message );
            }
        }
        
        if ($action === 'delete') {
            $this->event_service->delete_event_with_related($object_id);
            
            $message = __('Event and all associated data deleted successfully.', 'volunteer-exchange-platform');
        } elseif ($action === 'deactivate') {
            $this->event_service->deactivate($object_id);
            $message = __('Event deactivated.', 'volunteer-exchange-platform');
        } elseif ($action === 'delete-agreement') {
            $result = $this->event_service->delete_agreement($object_id);
            $notice = $result ? 'success' : 'error';
            $message = $result ? __('Agreement deleted.', 'volunteer-exchange-platform') : __('Could not delete agreement.', 'volunteer-exchange-platform');
            if ( $return_participant_id > 0 ) {
                $this->redirect_with_notice(
                    $notice,
                    $message,
                    array(
                        'action'                => 'view-participant',
                        'id'                    => $return_participant_id,
                        'return_id'             => $return_id,
                    )
                );
            } elseif ( $return_id > 0 ) {
                $this->redirect_with_notice(
                    $notice,
                    $message,
                    array(
                        'action' => 'view',
                        'id'     => $return_id,
                    )
                );
            } else {
                $this->redirect_with_notice( $notice, $message );
            }
        }

        $this->redirect_with_notice( 'success', $message );
    }

    /**
     * Handle bulk participant actions on the event detail page.
     *
     * @return void
     */
    public function handle_bulk_action() {
        $page_raw = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $page = $page_raw ? sanitize_key( $page_raw ) : '';
        if ( 'volunteer-exchange-events' !== $page ) {
            return;
        }

        $event_id = absint( filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT ) );
        if ( $event_id <= 0 ) {
            return;
        }

        $bulk_action_raw = filter_input( INPUT_GET, 'participant_bulk_action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $bulk_action = $bulk_action_raw ? sanitize_key( $bulk_action_raw ) : '-1';
        if ( ! in_array( $bulk_action, array( 'bulk_approve', 'bulk_delete' ), true ) ) {
            $bulk_action2_raw = filter_input( INPUT_GET, 'participant_bulk_action2', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
            $bulk_action2 = $bulk_action2_raw ? sanitize_key( $bulk_action2_raw ) : '-1';
            if ( ! in_array( $bulk_action2, array( 'bulk_approve', 'bulk_delete' ), true ) ) {
                return;
            }
            $bulk_action = $bulk_action2;
        }

        $participant_ids_raw = filter_input( INPUT_GET, 'participant', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
        if ( ! is_array( $participant_ids_raw ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'volunteer-exchange-platform' ) );
        }

        $bulk_nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( empty( $bulk_nonce ) || ! wp_verify_nonce( $bulk_nonce, 'bulk-participants' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'volunteer-exchange-platform' ) );
        }

        $participant_ids = array_map( 'absint', $participant_ids_raw );
        $count = 0;
        foreach ( $participant_ids as $participant_id ) {
            if ( 'bulk_approve' === $bulk_action ) {
                $this->participant_service->approve_participant( $participant_id );
                $count++;
            } elseif ( 'bulk_delete' === $bulk_action ) {
                $this->participant_service->delete_participant( $participant_id );
                $count++;
            }
        }

        if ( 'bulk_approve' === $bulk_action ) {
            $message = 1 === $count
                ? __( '1 participant approved.', 'volunteer-exchange-platform' )
                : sprintf( __( '%d participants approved.', 'volunteer-exchange-platform' ), $count );
        } else {
            $message = 1 === $count
                ? __( '1 participant deleted.', 'volunteer-exchange-platform' )
                : sprintf( __( '%d participants deleted.', 'volunteer-exchange-platform' ), $count );
        }

        $this->redirect_with_notice(
            'success',
            $message,
            array(
                'action' => 'view',
                'id'     => $event_id,
            )
        );
    }

    /**
     * Get approved participant emails for an event via AJAX.
     *
     * @return void
     */
    public function handle_get_active_participant_emails() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'volunteer-exchange-platform' ) ) );
        }

        $event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( $event_id <= 0 || ! wp_verify_nonce( $nonce, 'vep_copy_active_emails_' . $event_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'volunteer-exchange-platform' ) ) );
        }

        $participants = $this->participant_service->get_approved_for_event_with_type( $event_id );
        $emails = array();

        foreach ( $participants as $participant ) {
            if ( empty( $participant->contact_email ) ) {
                continue;
            }

            $email = sanitize_email( (string) $participant->contact_email );

            if ( '' !== $email ) {
                $emails[] = strtolower( $email );
            }
        }

        $emails = array_values( array_unique( $emails ) );

        if ( empty( $emails ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'No active participant emails found.', 'volunteer-exchange-platform' ) ) );
        }

        wp_send_json_success(
            array(
                'emails' => $emails,
                'emails_text' => implode( '; ', $emails ),
                'count' => count( $emails ),
            )
        );
    }
}
