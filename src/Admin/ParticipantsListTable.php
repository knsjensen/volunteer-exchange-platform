<?php
/**
 * Participants list table
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Participants list table
 * 
 * Extends WP_List_Table to display a sortable, filterable list of participants
 * with bulk actions and quick action links.
 * 
 * @package VolunteerExchangePlatform\Admin
 */
class ParticipantsListTable extends \WP_List_Table {
    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * @var array<int, array{state:string,label:string,color:string}>
     */
    private $update_progress_cache = array();

    /**
     * @var EventService
     */
    private $event_service;
    
    /**
     * Get default event ID (cached)
     *
     * @return int
     */
    protected function get_default_event_id() {
        static $cached_event_id = null;
        if ($cached_event_id !== null) {
            return $cached_event_id;
        }

        $active_event = $this->event_service->get_active_event();
        $cached_event_id = $active_event ? (int) $active_event->id : 0;
        return $cached_event_id;
    }
    
    /**
        * Constructor.
        *
        * @param ParticipantService|null $participant_service Participant service instance.
        * @param EventService|null       $event_service Event service instance.
        * @return void
     */
    public function __construct( ?ParticipantService $participant_service = null, ?EventService $event_service = null ) {
        $this->participant_service = $participant_service ?: new ParticipantService();
        $this->event_service = $event_service ?: new EventService();

        parent::__construct(array(
            'singular' => 'participant',
            'plural' => 'participants',
            'ajax' => false
        ));
    }
    
    /**
        * Get table columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'participant_number' => __('#', 'volunteer-exchange-platform'),
            'update_progress' => __('Updated', 'volunteer-exchange-platform'),
            'organization_name' => __('Organization', 'volunteer-exchange-platform'),
            'expected_participants_count' => __('Participants', 'volunteer-exchange-platform'),
            'contact_person_name' => __('Contact Person', 'volunteer-exchange-platform'),
            'participant_type' => __('Type', 'volunteer-exchange-platform'),
            'event_name' => __('Event', 'volunteer-exchange-platform'),
            'is_approved' => __('Approved', 'volunteer-exchange-platform'),
            'created_at' => __('Created', 'volunteer-exchange-platform')
        );
    }

    /**
     * Get update progress status for participant.
     *
     * Uses the same missing-fields logic as reminder emails.
     *
     * @param object $item Participant row.
     * @return array{state:string,label:string,color:string}
     */
    private function get_update_progress_status( $item ) {
        $participant_id = isset( $item->id ) ? (int) $item->id : 0;
        if ( $participant_id > 0 && isset( $this->update_progress_cache[ $participant_id ] ) ) {
            return $this->update_progress_cache[ $participant_id ];
        }

        $missing_fields = $this->participant_service->get_update_reminder_missing_fields( $item );
        $missing_count = count( $missing_fields );
        $total_part_two_fields = 5;

        if ( $missing_count <= 0 ) {
            $status = array(
                'state' => 'complete',
                'label' => __( 'Complete', 'volunteer-exchange-platform' ),
                'color' => '#00a32a',
            );
        } elseif ( $missing_count >= $total_part_two_fields ) {
            $status = array(
                'state' => 'not_started',
                'label' => __( 'Not started', 'volunteer-exchange-platform' ),
                'color' => '#d63638',
            );
        } else {
            $status = array(
                'state' => 'in_progress',
                'label' => __( 'Partially complete', 'volunteer-exchange-platform' ),
                'color' => '#dba617',
            );
        }

        if ( $participant_id > 0 ) {
            $this->update_progress_cache[ $participant_id ] = $status;
        }

        return $status;
    }
    
    /**
     * Get sortable columns
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return array(
            'participant_number' => array('participant_number', false),
            'update_progress' => array('update_progress', false),
            'organization_name' => array('organization_name', false),
            'contact_person_name' => array('contact_person_name', false),
            'participant_type' => array('participant_type', false),
            'is_approved' => array('is_approved', false),
            'created_at' => array('created_at', true)
        );
    }
    
    /**
     * Get bulk actions
     *
     * @return array
     */
    protected function get_bulk_actions() {
        return array(
            'bulk_approve' => __('Approve', 'volunteer-exchange-platform'),
            'bulk_delete' => __('Delete', 'volunteer-exchange-platform')
        );
    }
    
    /**
     * Get views (status filters)
     *
     * @return array
     */
    protected function get_views() {
        $status_raw = filter_input( INPUT_GET, 'approval_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $status_filter = $status_raw ? sanitize_key( $status_raw ) : 'all';

        $event_id_param_present = filter_has_var( INPUT_GET, 'event_id' );
        $event_id_raw = filter_input( INPUT_GET, 'event_id', FILTER_VALIDATE_INT );
        $event_id = $event_id_param_present ? absint( (int) $event_id_raw ) : $this->get_default_event_id();

        $total_count = $this->participant_service->count_with_filters('all', $event_id);
        $pending_count = $this->participant_service->count_with_filters('pending', $event_id);
        $approved_count = $this->participant_service->count_with_filters('approved', $event_id);

        // Preserve the event filter in status view links only if it was explicitly selected.
        $event_query_arg = $event_id_param_present ? '&event_id=' . $event_id : '';
        
        $views = array(
            'all' => sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                admin_url('admin.php?page=volunteer-exchange-participants' . $event_query_arg),
                $status_filter === 'all' ? ' class="current"' : '',
                __('All', 'volunteer-exchange-platform'),
                $total_count
            ),
            'pending' => sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                admin_url('admin.php?page=volunteer-exchange-participants&approval_status=pending' . $event_query_arg),
                $status_filter === 'pending' ? ' class="current"' : '',
                __('Pending Approval', 'volunteer-exchange-platform'),
                $pending_count
            ),
            'approved' => sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                admin_url('admin.php?page=volunteer-exchange-participants&approval_status=approved' . $event_query_arg),
                $status_filter === 'approved' ? ' class="current"' : '',
                __('Approved', 'volunteer-exchange-platform'),
                $approved_count
            )
        );
        
        return $views;
    }

    /**
     * Build URL for row action with nonce
     *
     * @param string $action
     * @param int    $participant_id
     * @return string
     */
    private function get_row_action_url( $action, $participant_id ) {
        $url = add_query_arg(
            array(
                'page' => 'volunteer-exchange-participants',
                'action' => $action,
                'id' => (int) $participant_id,
            ),
            admin_url( 'admin.php' )
        );

        return wp_nonce_url( $url, 'vep_participant_' . $action . '_' . (int) $participant_id );
    }
    
    /**
     * Column default
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'participant_number':
                return $item->participant_number ? '<strong>' . esc_html($item->participant_number) . '</strong>' : '<span style="color: #999;">—</span>';
            case 'expected_participants_count':
                return ($item->expected_participants_count !== null && $item->expected_participants_count !== '')
                    ? esc_html($item->expected_participants_count)
                    : '<span style="color: #999;">—</span>';
            case 'participant_type':
            case 'event_name':
            case 'contact_person_name':
                return isset($item->$column_name) ? $item->$column_name : '';
            case 'created_at':
                return date_i18n(get_option('date_format'), strtotime($item->$column_name));
            case 'is_approved':
                if ($item->is_approved) {
                    $url = $this->get_row_action_url('unapprove', $item->id);
                    return '<span style="color: green;">✓ <a href="' . esc_url($url) . '" style="color: #d63638; font-weight: 600; margin-left: 8px;">' . esc_html__('Remove approval', 'volunteer-exchange-platform') . '</a></span>';
                }

                $url = $this->get_row_action_url('approve', $item->id);
                return '<span style="color: orange;">⏳ <a href="' . esc_url($url) . '" style="color: #00a32a; font-weight: 600; margin-left: 8px;">' . esc_html__('Approve', 'volunteer-exchange-platform') . '</a></span>';
            case 'update_progress':
                $status = $this->get_update_progress_status( $item );
                return '<span title="' . esc_attr( $status['label'] ) . '" aria-label="' . esc_attr( $status['label'] ) . '" style="display:inline-block;width:10px;height:10px;border-radius:50%;background-color:' . esc_attr( $status['color'] ) . ';"></span>';
            default:
                return isset($item->$column_name) ? $item->$column_name : '';
        }
    }
    
    /**
     * Column checkbox
     *
     * @param object $item
     * @return string
     */
    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="participant[]" value="%s" />', $item->id);
    }
    
    /**
     * Column name with actions
     *
     * @param object $item
     * @return string
     */
    protected function column_organization_name($item) {
        $actions = array(
            'view' => sprintf(
                '<a href="?page=%s&action=%s&id=%s">%s</a>',
                'volunteer-exchange-participants',
                'view',
                $item->id,
                __('View', 'volunteer-exchange-platform')
            ),
            'edit' => sprintf(
                '<a href="?page=%s&action=%s&id=%s">%s</a>',
                'volunteer-exchange-participants',
                'edit',
                $item->id,
                __('Edit', 'volunteer-exchange-platform')
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                esc_url($this->get_row_action_url('delete', $item->id)),
                __('Are you sure you want to delete this participant?', 'volunteer-exchange-platform'),
                __('Delete', 'volunteer-exchange-platform')
            )
        );
        
        return sprintf('%1$s %2$s', $item->organization_name, $this->row_actions($actions));
    }

    /**
     * Extra controls above/below the table
     *
     * @param string $which
     * @return void
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $events = $this->event_service->get_all_for_select();

        $event_id_param_present = filter_has_var( INPUT_GET, 'event_id' );
        $selected_event_raw = filter_input( INPUT_GET, 'event_id', FILTER_VALIDATE_INT );
        $selected_event_id = $event_id_param_present
            ? absint( (int) $selected_event_raw )
            : $this->get_default_event_id();
        ?>
        <div class="alignleft actions">
            <label class="screen-reader-text" for="filter-by-event"><?php esc_html_e('Filter by event', 'volunteer-exchange-platform'); ?></label>
            <select name="event_id" id="filter-by-event">
                <option value="0"><?php esc_html_e('All Events', 'volunteer-exchange-platform'); ?></option>
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo esc_attr($event->id); ?>" <?php selected($selected_event_id, (int) $event->id); ?>>
                        <?php echo esc_html($event->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php submit_button(__('Filter', 'volunteer-exchange-platform'), '', 'filter_action', false); ?>
        </div>
        <?php
    }
    
    /**
        * Prepare list table items.
     *
     * Fetches and prepares the list of participants based on filters and sorting
     *
     * @return void
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();

        $orderby_raw = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $order_raw = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $approval_status_raw = filter_input( INPUT_GET, 'approval_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $orderby = $orderby_raw ? sanitize_key( $orderby_raw ) : 'created_at';
        $order_param = $order_raw ? sanitize_key( $order_raw ) : '';
        $order = 'asc' === $order_param ? 'ASC' : 'DESC';

        $offset = ($current_page - 1) * $per_page;

        $approval_status = $approval_status_raw ? sanitize_key( $approval_status_raw ) : 'all';

        // Event filter (default to active event when not explicitly provided)
        $event_id_param_present = filter_has_var( INPUT_GET, 'event_id' );
        $event_id_raw = filter_input( INPUT_GET, 'event_id', FILTER_VALIDATE_INT );
        $event_id = $event_id_param_present ? absint( (int) $event_id_raw ) : $this->get_default_event_id();

        $this->items = $this->participant_service->get_paginated_with_filters(
            $per_page,
            $offset,
            $orderby,
            $order,
            $approval_status,
            $event_id
        );

        $total_items = $this->participant_service->count_with_filters($approval_status, $event_id);
        
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        );
    }
}
