<?php
/**
 * Event participants list table.
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Participant list scoped to a single event view.
 */
class EventParticipantsListTable extends \WP_List_Table {
    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * @var EventService
     */
    private $event_service;

    /**
     * @var int
     */
    private $event_id;

    /**
     * @var string
     */
    private $selected_per_page = '25';

    /**
     * Constructor.
     *
     * @param int                     $event_id Event ID.
     * @param ParticipantService|null $participant_service Participant service instance.
     * @param EventService|null       $event_service Event service instance.
     */
    public function __construct( $event_id, ?ParticipantService $participant_service = null, ?EventService $event_service = null ) {
        $this->event_id = absint( $event_id );
        $this->participant_service = $participant_service ?: new ParticipantService();
        $this->event_service = $event_service ?: new EventService();

        parent::__construct(
            array(
                'singular' => 'participant',
                'plural'   => 'participants',
                'ajax'     => true,
            )
        );
    }

    /**
     * Get columns.
     *
     * @return array<string, string>
     */
    public function get_columns() {
        return array(
            'cb'                          => '<input type="checkbox" />',
            'participant_number'          => __( '#', 'volunteer-exchange-platform' ),
            'update_progress'             => __( 'Updated', 'volunteer-exchange-platform' ),
            'organization_name'           => __( 'Organization', 'volunteer-exchange-platform' ),
            'expected_participants_count' => __( 'Participants', 'volunteer-exchange-platform' ),
            'contact_person_name'         => __( 'Contact Person', 'volunteer-exchange-platform' ),
            'participant_type'            => __( 'Type', 'volunteer-exchange-platform' ),
            'event_name'                  => __( 'Event', 'volunteer-exchange-platform' ),
            'agreements_count'            => __( 'Agreements Made', 'volunteer-exchange-platform' ),
            'is_approved'                 => __( 'Approved', 'volunteer-exchange-platform' ),
            'created_at'                  => __( 'Created', 'volunteer-exchange-platform' ),
        );
    }

    /**
     * Get sortable columns.
     *
     * @return array<string, array{0:string,1:bool}>
     */
    protected function get_sortable_columns() {
        return array(
            'participant_number' => array( 'participant_number', false ),
            'update_progress'    => array( 'update_progress', false ),
            'organization_name'  => array( 'organization_name', false ),
            'contact_person_name'=> array( 'contact_person_name', false ),
            'participant_type'   => array( 'participant_type', false ),
            'agreements_count'   => array( 'agreements_count', false ),
            'is_approved'        => array( 'is_approved', false ),
            'created_at'         => array( 'created_at', true ),
        );
    }

    /**
     * No bulk actions on event page.
     *
     * @return array<string, string>
     */
    protected function get_bulk_actions() {
        return array(
            'bulk_approve' => __( 'Approve', 'volunteer-exchange-platform' ),
            'bulk_delete'  => __( 'Delete', 'volunteer-exchange-platform' ),
        );
    }

    /**
     * Get views.
     *
     * @return array<string, string>
     */
    protected function get_views() {
        $status_raw = filter_input( INPUT_GET, 'approval_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $status_filter = $status_raw ? sanitize_key( $status_raw ) : 'all';
        $total_count = $this->participant_service->count_with_filters( 'all', $this->event_id );
        $pending_count = $this->participant_service->count_with_filters( 'pending', $this->event_id );
        $approved_count = $this->participant_service->count_with_filters( 'approved', $this->event_id );

        return array(
            'all' => sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( $this->build_view_url( array( 'approval_status' => false, 'paged' => false ) ) ),
                'all' === $status_filter ? ' class="current"' : '',
                __( 'All', 'volunteer-exchange-platform' ),
                $total_count
            ),
            'pending' => sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( $this->build_view_url( array( 'approval_status' => 'pending', 'paged' => false ) ) ),
                'pending' === $status_filter ? ' class="current"' : '',
                __( 'Pending Approval', 'volunteer-exchange-platform' ),
                $pending_count
            ),
            'approved' => sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( $this->build_view_url( array( 'approval_status' => 'approved', 'paged' => false ) ) ),
                'approved' === $status_filter ? ' class="current"' : '',
                __( 'Approved', 'volunteer-exchange-platform' ),
                $approved_count
            ),
        );
    }

    /**
     * Render checkbox column.
     *
     * @param object $item Participant item.
     * @return string
     */
    protected function column_cb( $item ) {
        $email = isset( $item->contact_email ) ? sanitize_email( (string) $item->contact_email ) : '';

        return sprintf(
            '<input type="checkbox" name="participant[]" value="%1$s" data-contact-email="%2$s" />',
            (int) $item->id,
            esc_attr( strtolower( $email ) )
        );
    }

    /**
     * Render organization column.
     *
     * @param object $item Participant item.
     * @return string
     */
    protected function column_organization_name( $item ) {
        $participant_id = isset( $item->id ) ? (int) $item->id : 0;
        $view_url = $this->get_participant_page_url( 'view-participant', $participant_id );

        $actions = array(
            'view' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $view_url ),
                __( 'View', 'volunteer-exchange-platform' )
            ),
            'edit' => sprintf(
                '<a href="%s" data-participant-edit-modal="%d">%s</a>',
                esc_url( $this->get_participant_page_url( 'edit-participant', $participant_id ) ),
                $participant_id,
                __( 'Edit', 'volunteer-exchange-platform' )
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                esc_url( $this->get_participant_quick_action_url( 'delete', $participant_id ) ),
                esc_js( __( 'Are you sure you want to delete this participant?', 'volunteer-exchange-platform' ) ),
                __( 'Delete', 'volunteer-exchange-platform' )
            ),
        );

        return sprintf(
            '%1$s %2$s',
            '<a class="row-title" href="' . esc_url( $view_url ) . '">' . esc_html( (string) $item->organization_name ) . '</a>',
            $this->row_actions( $actions )
        );
    }

    /**
     * Render default column.
     *
     * @param object $item Participant item.
     * @param string $column_name Column name.
     * @return string
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'participant_number':
                return $item->participant_number ? '<strong>' . esc_html( $item->participant_number ) . '</strong>' : '<span style="color: #999;">—</span>';
            case 'expected_participants_count':
                return ( null !== $item->expected_participants_count && '' !== $item->expected_participants_count )
                    ? esc_html( $item->expected_participants_count )
                    : '<span style="color: #999;">—</span>';
            case 'participant_type':
            case 'event_name':
            case 'contact_person_name':
                return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
            case 'agreements_count':
                return isset( $item->agreements_count ) ? (string) (int) $item->agreements_count : '0';
            case 'created_at':
                // Vis både dato og tidspunkt
                return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $item->$column_name ) ) );
            case 'is_approved':
                return $this->render_approval_column( $item );
            case 'update_progress':
                return $this->render_update_progress( $item );
            default:
                return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
        }
    }

    /**
     * Render top filter toolbar.
     *
     * @param string $which Table position.
     * @return void
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }
        ?>
        <button type="button" class="button button-primary vep-print-selected-button" id="vep-print-selected-participants">
            <?php esc_html_e( 'Print selected', 'volunteer-exchange-platform' ); ?>
        </button>
        <button type="button" class="button" id="vep-copy-selected-emails">
            <?php esc_html_e( 'Copy selected emails', 'volunteer-exchange-platform' ); ?>
        </button>
        <?php
    }

    /**
     * Render table navigation.
     *
     * @param string $which Top or bottom.
     * @return void
     */
    public function display_tablenav( $which ) {
        if ( ! $this->has_items() ) {
            return;
        }
        ?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">
            <?php if ( 'top' === $which ) : ?>
                <div class="alignleft actions vep-participants-toolbar">
                    <?php $this->render_bulk_actions_control( $which ); ?>
                    <?php $this->extra_tablenav( $which ); ?>
                </div>
            <?php endif; ?>

            <div class="alignright actions vep-tablenav-right">
                <?php $this->render_per_page_control(); ?>
                <?php $this->pagination( $which ); ?>
            </div>

            <br class="clear" />
        </div>
        <?php
    }

    /**
     * Render custom bulk actions controls without colliding with the page action route.
     *
     * @param string $which Top or bottom.
     * @return void
     */
    private function render_bulk_actions_control( $which ) {
        $actions = $this->get_bulk_actions();
        if ( empty( $actions ) ) {
            return;
        }

        $field_name = 'top' === $which ? 'participant_bulk_action' : 'participant_bulk_action2';
        $button_id = 'top' === $which ? 'doaction' : 'doaction2';
        ?>
        <div class="bulkactions">
            <label for="bulk-action-selector-<?php echo esc_attr( $which ); ?>" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'volunteer-exchange-platform' ); ?></label>
            <select name="<?php echo esc_attr( $field_name ); ?>" id="bulk-action-selector-<?php echo esc_attr( $which ); ?>">
                <option value="-1"><?php esc_html_e( 'Bulk actions', 'volunteer-exchange-platform' ); ?></option>
                <?php foreach ( $actions as $action => $label ) : ?>
                    <option value="<?php echo esc_attr( $action ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" id="<?php echo esc_attr( $button_id ); ?>" class="button action" value="<?php esc_attr_e( 'Apply', 'volunteer-exchange-platform' ); ?>">
            <?php if ( 'top' === $which ) : ?>
                <?php wp_nonce_field( 'bulk-participants' ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Prepare items.
     *
     * @return void
     */
    public function prepare_items() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        // Read directly from $_GET so that runtime injections (e.g. from the AJAX handler)
        // are visible — filter_input(INPUT_GET) reads the original request and ignores them.
        $orderby_raw         = isset( $_GET['orderby'] )         ? wp_unslash( $_GET['orderby'] )         : null;
        $order_raw           = isset( $_GET['order'] )           ? wp_unslash( $_GET['order'] )           : null;
        $approval_status_raw = isset( $_GET['approval_status'] ) ? wp_unslash( $_GET['approval_status'] ) : null;
        $per_page_raw        = isset( $_GET['per_page'] )        ? wp_unslash( $_GET['per_page'] )        : null;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $orderby = $orderby_raw ? sanitize_key( $orderby_raw ) : 'created_at';
        $order_param = $order_raw ? sanitize_key( $order_raw ) : '';
        $order = 'asc' === $order_param ? 'ASC' : 'DESC';
        $approval_status = $approval_status_raw ? sanitize_key( $approval_status_raw ) : 'all';
        $per_page_value = $per_page_raw ? sanitize_key( $per_page_raw ) : '25';

        $total_items = $this->participant_service->count_with_filters( $approval_status, $this->event_id );
        $allowed_per_page = array( '25', '50', '100', 'all' );
        $per_page_value = in_array( $per_page_value, $allowed_per_page, true ) ? $per_page_value : '25';
        $this->selected_per_page = $per_page_value;
        $per_page = 'all' === $per_page_value ? max( 1, $total_items ) : (int) $per_page_value;
        $current_page = $this->get_pagenum();
        $offset = 'all' === $per_page_value ? 0 : ( $current_page - 1 ) * $per_page;

        $this->items = $this->participant_service->get_paginated_with_filters(
            $per_page,
            $offset,
            $orderby,
            $order,
            $approval_status,
            $this->event_id
        );

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil( $total_items / $per_page ),
            )
        );

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }

    /**
     * Render per-page selector.
     *
     * @return void
     */
    private function render_per_page_control() {
        ?>
        <label for="vep-event-participants-per-page-select"><?php esc_html_e( 'Rows per page', 'volunteer-exchange-platform' ); ?></label>
        <select class="vep-per-page-select" id="vep-event-participants-per-page-select" data-per-page-control="1">
            <option value="25" <?php selected( $this->selected_per_page, '25' ); ?>>25</option>
            <option value="50" <?php selected( $this->selected_per_page, '50' ); ?>>50</option>
            <option value="100" <?php selected( $this->selected_per_page, '100' ); ?>>100</option>
            <option value="all" <?php selected( $this->selected_per_page, 'all' ); ?>><?php esc_html_e( 'All', 'volunteer-exchange-platform' ); ?></option>
        </select>
        <?php
    }

    /**
     * Render approval status column.
     *
     * @param object $item Participant item.
     * @return string
     */
    private function render_approval_column( $item ) {
        $participant_id = isset( $item->id ) ? (int) $item->id : 0;

        if ( ! empty( $item->is_approved ) ) {
            $url = wp_nonce_url( $this->build_view_url( array( 'participant_action' => 'unapprove', 'participant_id' => $participant_id ) ), 'vep_participant_unapprove_' . $participant_id );
            return '<span style="color: green;">✓ <a href="' . esc_url( $url ) . '" style="color: #d63638; font-weight: 600; margin-left: 8px;">' . esc_html__( 'Remove approval', 'volunteer-exchange-platform' ) . '</a></span>';
        }

        $url = wp_nonce_url( $this->build_view_url( array( 'participant_action' => 'approve', 'participant_id' => $participant_id ) ), 'vep_participant_approve_' . $participant_id );
        return '<span style="color: orange;">⏳ <a href="' . esc_url( $url ) . '" style="color: #00a32a; font-weight: 600; margin-left: 8px;">' . esc_html__( 'Approve', 'volunteer-exchange-platform' ) . '</a></span>';
    }

    /**
     * Render update progress indicator.
     *
     * @param object $item Participant item.
     * @return string
     */
    private function render_update_progress( $item ) {
        $missing_fields = $this->participant_service->get_update_reminder_missing_fields( $item );
        $missing_count = count( $missing_fields );
        $total_part_two_fields = 5;

        if ( $missing_count <= 0 ) {
            $label = __( 'Complete', 'volunteer-exchange-platform' );
            $color = '#00a32a';
        } elseif ( $missing_count >= $total_part_two_fields ) {
            $label = __( 'Not started', 'volunteer-exchange-platform' );
            $color = '#d63638';
        } else {
            $label = __( 'Partially complete', 'volunteer-exchange-platform' );
            $color = '#dba617';
        }

        return '<span title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '" style="display:inline-block;width:10px;height:10px;border-radius:50%;background-color:' . esc_attr( $color ) . ';"></span>';
    }

    /**
     * Build event view URL preserving table state.
     *
     * @param array<string, mixed> $overrides Query arg overrides.
     * @return string
     */
    private function build_view_url( array $overrides = array() ) {
        $args = array(
            'page'   => 'volunteer-exchange-events',
            'action' => 'view',
            'id'     => $this->event_id,
        );

        $approval_status_raw = filter_input( INPUT_GET, 'approval_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $orderby_raw = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $order_raw = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $paged_raw = filter_input( INPUT_GET, 'paged', FILTER_VALIDATE_INT );

        if ( $approval_status_raw ) {
            $args['approval_status'] = sanitize_key( $approval_status_raw );
        }
        if ( $this->selected_per_page ) {
            $args['per_page'] = $this->selected_per_page;
        }
        if ( $orderby_raw ) {
            $args['orderby'] = sanitize_key( $orderby_raw );
        }
        if ( $order_raw ) {
            $args['order'] = sanitize_key( $order_raw );
        }
        if ( $paged_raw ) {
            $args['paged'] = absint( (int) $paged_raw );
        }

        foreach ( $overrides as $key => $value ) {
            if ( false === $value || '' === $value || null === $value ) {
                unset( $args[ $key ] );
            } else {
                $args[ $key ] = $value;
            }
        }

        return admin_url( 'admin.php?' . http_build_query( $args, '', '&' ) );
    }

    /**
     * Build URL for participant subpages under the event page.
     *
     * @param string $action Action name.
     * @param int    $participant_id Participant ID.
     * @return string
     */
    private function get_participant_page_url( $action, $participant_id ) {
        return admin_url(
            'admin.php?' . http_build_query(
                array(
                    'page'      => 'volunteer-exchange-events',
                    'action'    => $action,
                    'id'        => (int) $participant_id,
                    'return_id' => $this->event_id,
                ),
                '',
                '&'
            )
        );
    }

    /**
     * Build URL for participant quick actions on the event page.
     *
     * @param string $action Action name.
     * @param int    $participant_id Participant ID.
     * @return string
     */
    private function get_participant_quick_action_url( $action, $participant_id ) {
        return wp_nonce_url(
            $this->build_view_url(
                array(
                    'participant_action' => $action,
                    'participant_id'     => (int) $participant_id,
                )
            ),
            'vep_participant_' . $action . '_' . (int) $participant_id
        );
    }

    /**
     * Output the full WP_List_Table markup with a unique id for AJAX replacement.
     */
    public function display() {
        // Output the full WP_List_Table markup (toolbars, table, paging, etc.)
        parent::display();
        // Add table id via JS or filter if needed for AJAX targeting
    }
}