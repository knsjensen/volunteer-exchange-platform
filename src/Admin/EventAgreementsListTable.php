<?php
/**
 * Event agreements list table
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\AgreementStatus;
use VolunteerExchangePlatform\Services\EventService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Event agreements list table.
 */
class EventAgreementsListTable extends \WP_List_Table {
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
     * @var string
     */
    private $selected_status = 'all';

    /**
     * Constructor.
     *
     * @param int               $event_id Event ID.
     * @param EventService|null $event_service Event service instance.
     */
    public function __construct( $event_id, ?EventService $event_service = null ) {
        $this->event_id = absint( $event_id );
        $this->event_service = $event_service ?: new EventService();

        parent::__construct(
            array(
                'singular' => 'agreement',
                'plural'   => 'agreements',
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
            'id'             => __( 'ID', 'volunteer-exchange-platform' ),
            'participant_1'  => __( 'Participant 1', 'volunteer-exchange-platform' ),
            'participant_2'  => __( 'Participant 2', 'volunteer-exchange-platform' ),
            'initiator'      => __( 'Initiator', 'volunteer-exchange-platform' ),
            'description'    => __( 'Description', 'volunteer-exchange-platform' ),
            'status'         => __( 'Status', 'volunteer-exchange-platform' ),
            'created_at'     => __( 'Created', 'volunteer-exchange-platform' ),
        );
    }

    /**
     * Get sortable columns.
     *
     * @return array<string, array{0:string,1:bool}>
     */
    protected function get_sortable_columns() {
        return array(
            'id'            => array( 'id', true ),
            'participant_1' => array( 'participant_1', false ),
            'participant_2' => array( 'participant_2', false ),
            'initiator'     => array( 'initiator', false ),
            'status'        => array( 'status', false ),
            'created_at'    => array( 'created_at', false ),
        );
    }

    /**
     * Render the empty state.
     *
     * @return void
     */
    public function no_items() {
        if ( '' !== $this->get_search_term() || 'all' !== $this->get_status_filter() ) {
            esc_html_e( 'No agreements match your filters.', 'volunteer-exchange-platform' );
            return;
        }

        esc_html_e( 'No agreements yet.', 'volunteer-exchange-platform' );
    }

    /**
     * Prepare items.
     *
     * @return void
     */
    public function prepare_items() {
        $current_page = $this->get_pagenum();
        $search_term = $this->get_search_term();
        $status_filter = $this->get_status_filter();
        // Read directly from $_GET so that runtime injections (e.g. from the AJAX handler)
        // are visible — filter_input(INPUT_GET) reads the original request and ignores them.
        $orderby_raw  = isset( $_GET['orderby'] )  ? wp_unslash( $_GET['orderby'] )  : null;
        $order_raw    = isset( $_GET['order'] )    ? wp_unslash( $_GET['order'] )    : null;
        $per_page_raw = isset( $_GET['per_page'] ) ? wp_unslash( $_GET['per_page'] ) : null;
        $orderby = $orderby_raw ? sanitize_key( (string) $orderby_raw ) : 'id';
        $order = 'asc' === strtolower( (string) $order_raw ) ? 'asc' : 'desc';
        $per_page_value = $per_page_raw ? sanitize_key( $per_page_raw ) : '25';

        $items = $this->event_service->get_agreements_for_event( $this->event_id );
        $items = $this->filter_items_by_status( $items, $status_filter );
        $items = $this->filter_items( $items, $search_term );
        $items = $this->sort_items( $items, $orderby, $order );

        $total_items = count( $items );
        $allowed_per_page = array( '25', '50', '100', 'all' );
        $per_page_value = in_array( $per_page_value, $allowed_per_page, true ) ? $per_page_value : '25';
        $this->selected_per_page = $per_page_value;
        $this->selected_status = $status_filter;
        $per_page = 'all' === $per_page_value ? max( 1, $total_items ) : (int) $per_page_value;
        $offset = 'all' === $per_page_value ? 0 : ( $current_page - 1 ) * $per_page;
        $this->items = array_slice( $items, $offset, $per_page );

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => (int) ceil( $total_items / $per_page ),
            )
        );

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }

    /**
     * Render filter controls above the table.
     *
     * @return void
     */
    protected function get_views() {
        $counts = $this->get_status_counts();
        $views = array();

        foreach ( $this->get_status_options() as $status_value => $status_label ) {
            $url = add_query_arg(
                $this->get_query_args(
                    array(
                        'agreement_status' => 'all' === $status_value ? false : $status_value,
                        'paged'            => false,
                    )
                ),
                admin_url( 'admin.php' )
            );

            $views[ $status_value ] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url( $url ),
                $this->selected_status === $status_value ? ' class="current"' : '',
                esc_html( $status_label ),
                (int) ( $counts[ $status_value ] ?? 0 )
            );
        }

        return $views;
    }

    /**
     * Render the table tablenav with search on the left and pagination on the right.
     *
     * @param string $which Top or bottom tablenav.
     * @return void
     */
    public function display_tablenav( $which ) {
        if ( 'bottom' === $which && ! $this->has_items() ) {
            return;
        }

        ?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">
            <div class="alignleft actions">
                <?php if ( 'top' === $which ) : ?>
                    <label class="screen-reader-text" for="vep-agreements-search"><?php esc_html_e( 'Search agreements', 'volunteer-exchange-platform' ); ?></label>
                    <input
                        type="search"
                        id="vep-agreements-search"
                        name="s"
                        value="<?php echo esc_attr( $this->get_search_term() ); ?>"
                        placeholder="<?php esc_attr_e( 'Type search words here...', 'volunteer-exchange-platform' ); ?>"
                    >
                    <button type="submit" class="button"><?php esc_html_e( 'Search', 'volunteer-exchange-platform' ); ?></button>
                <?php endif; ?>
            </div>

            <div class="alignright actions vep-tablenav-right">
                <?php $this->render_per_page_control(); ?>
                <?php $this->pagination( $which ); ?>
            </div>

            <br class="clear" />
        </div>
        <?php
    }

    /**
     * Render default column value.
     *
     * @param object $item Item.
     * @param string $column_name Column name.
     * @return string
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'participant_1':
                return $this->render_participant_link(
                    isset( $item->participant1_id ) ? (int) $item->participant1_id : 0,
                    $this->format_participant_label( $item->participant1_number ?? 0, $item->participant1_name ?? '' )
                );

            case 'participant_2':
                return $this->render_participant_link(
                    isset( $item->participant2_id ) ? (int) $item->participant2_id : 0,
                    $this->format_participant_label( $item->participant2_number ?? 0, $item->participant2_name ?? '' )
                );

            case 'initiator':
                return $this->render_participant_link(
                    isset( $item->initiator_id ) ? (int) $item->initiator_id : 0,
                    (string) ( $item->initiator_name ?? '' )
                );

            case 'description':
                return esc_html( wp_trim_words( (string) ( $item->description ?? '' ), 10 ) );

            case 'status':
                $status = (string) ( $item->status ?? '' );
                $labels = $this->get_status_options();
                unset( $labels['all'] );
                return esc_html( $labels[ $status ] ?? $status );

            case 'created_at':
                $timestamp = strtotime( (string) ( $item->created_at ?? '' ) );
                // Vis både dato og tidspunkt
                return $timestamp ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ) : '';

            default:
                return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
        }
    }

    /**
     * Render the ID column with row actions.
     *
     * @param object $item Item.
     * @return string
     */
    protected function column_id( $item ) {
        $agreement_id = isset( $item->id ) ? (int) $item->id : 0;

        $actions = array(
            'view' => sprintf(
                '<a href="%s" data-agreement-view-modal="%d">%s</a>',
                esc_url( $this->get_view_url( $agreement_id ) ),
                $agreement_id,
                __( 'View', 'volunteer-exchange-platform' )
            ),
            'edit' => sprintf(
                '<a href="%s" data-agreement-edit-modal="%d">%s</a>',
                esc_url( $this->get_edit_url( $agreement_id ) ),
                $agreement_id,
                __( 'Edit', 'volunteer-exchange-platform' )
            ),
            'delete' => sprintf(
                '<a href="%s" data-agreement-action="delete" data-confirm-msg="%s">%s</a>',
                esc_url( $this->get_delete_url( $agreement_id ) ),
                esc_attr( __( 'Are you sure you want to delete this agreement?', 'volunteer-exchange-platform' ) ),
                __( 'Delete', 'volunteer-exchange-platform' )
            ),
        );

        return sprintf(
            '%1$s %2$s',
            '<a class="row-title" href="' . esc_url( $this->get_view_url( $agreement_id ) ) . '" data-agreement-view-modal="' . esc_attr( (string) $agreement_id ) . '">' . esc_html( (string) $agreement_id ) . '</a>',
            $this->row_actions( $actions )
        );
    }

    /**
     * Render a participant label linked to the participant view when possible.
     *
     * @param int    $participant_id Participant ID.
     * @param string $label Participant label.
     * @return string
     */
    private function render_participant_link( $participant_id, $label ) {
        $label = trim( (string) $label );
        if ( $participant_id <= 0 || '' === $label ) {
            return esc_html( $label );
        }

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url( $this->get_participant_view_url( $participant_id ) ),
            esc_html( $label )
        );
    }

    /**
     * Build search term.
     *
     * @return string
     */
    private function get_search_term() {
        $search_raw = isset( $_GET['s'] ) ? wp_unslash( $_GET['s'] ) : '';
        return is_string( $search_raw ) ? sanitize_text_field( $search_raw ) : '';
    }

    /**
     * Get selected status filter.
     *
     * @return string
     */
    private function get_status_filter() {
        $status_raw = isset( $_GET['agreement_status'] ) ? wp_unslash( $_GET['agreement_status'] ) : null;
        $status = $status_raw ? sanitize_key( $status_raw ) : 'all';
        $allowed_statuses = array_keys( $this->get_status_options() );

        return in_array( $status, $allowed_statuses, true ) ? $status : 'all';
    }

    /**
     * Filter items by search.
     *
     * @param array  $items Agreements.
     * @param string $search_term Search term.
     * @return array
     */
    private function filter_items( array $items, $search_term ) {
        if ( '' === $search_term ) {
            return $items;
        }

        $needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search_term ) : strtolower( $search_term );

        return array_values(
            array_filter(
                $items,
                function ( $agreement ) use ( $needle ) {
                    $haystack_parts = array(
                        (string) ( $agreement->id ?? '' ),
                        (string) ( $agreement->participant1_name ?? '' ),
                        (string) ( $agreement->participant2_name ?? '' ),
                        (string) ( $agreement->initiator_name ?? '' ),
                        (string) ( $agreement->description ?? '' ),
                        (string) ( $agreement->created_at ?? '' ),
                    );

                    $haystack = implode( ' ', $haystack_parts );
                    $haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );

                    return false !== strpos( $haystack, $needle );
                }
            )
        );
    }

    /**
     * Filter items by status.
     *
     * @param array  $items Agreements.
     * @param string $status_filter Status filter.
     * @return array
     */
    private function filter_items_by_status( array $items, $status_filter ) {
        if ( 'all' === $status_filter ) {
            return $items;
        }

        return array_values(
            array_filter(
                $items,
                function ( $agreement ) use ( $status_filter ) {
                    return (string) ( $agreement->status ?? '' ) === $status_filter;
                }
            )
        );
    }

    /**
     * Sort items.
     *
     * @param array  $items Agreements.
     * @param string $orderby Sort field.
     * @param string $order Sort direction.
     * @return array
     */
    private function sort_items( array $items, $orderby, $order ) {
        $allowed_sort_fields = array( 'id', 'participant_1', 'participant_2', 'initiator', 'status', 'created_at' );
        if ( ! in_array( $orderby, $allowed_sort_fields, true ) ) {
            $orderby = 'id';
        }

        usort(
            $items,
            function ( $left, $right ) use ( $orderby, $order ) {
                switch ( $orderby ) {
                    case 'participant_1':
                        $comparison = strnatcasecmp( (string) ( $left->participant1_name ?? '' ), (string) ( $right->participant1_name ?? '' ) );
                        break;

                    case 'participant_2':
                        $comparison = strnatcasecmp( (string) ( $left->participant2_name ?? '' ), (string) ( $right->participant2_name ?? '' ) );
                        break;

                    case 'initiator':
                        $comparison = strnatcasecmp( (string) ( $left->initiator_name ?? '' ), (string) ( $right->initiator_name ?? '' ) );
                        break;

                    case 'status':
                        $comparison = strcmp( (string) ( $left->status ?? '' ), (string) ( $right->status ?? '' ) );
                        break;

                    case 'created_at':
                        $comparison = strtotime( (string) ( $left->created_at ?? '' ) ) <=> strtotime( (string) ( $right->created_at ?? '' ) );
                        break;

                    case 'id':
                    default:
                        $comparison = (int) ( $left->id ?? 0 ) <=> (int) ( $right->id ?? 0 );
                        break;
                }

                if ( 0 === $comparison ) {
                    $comparison = (int) ( $left->id ?? 0 ) <=> (int) ( $right->id ?? 0 );
                }

                return 'desc' === $order ? -$comparison : $comparison;
            }
        );

        return $items;
    }

    /**
     * Format participant display label.
     *
     * @param mixed  $number Participant number.
     * @param string $name Participant name.
     * @return string
     */
    private function format_participant_label( $number, $name ) {
        $participant_name = trim( (string) $name );
        $participant_number = (int) $number;

        if ( $participant_number > 0 && '' !== $participant_name ) {
            return $participant_number . '# ' . $participant_name;
        }

        if ( $participant_number > 0 ) {
            return (string) $participant_number;
        }

        return $participant_name;
    }

    /**
     * Render per-page selector used in the top and bottom tablenav.
     *
     * @return void
     */
    private function render_per_page_control() {
        ?>
        <label for="vep-agreement-per-page-select"><?php esc_html_e( 'Rows per page', 'volunteer-exchange-platform' ); ?></label>
        <select class="vep-per-page-select" id="vep-agreement-per-page-select" data-per-page-control="1">
            <option value="25" <?php selected( $this->selected_per_page, '25' ); ?>>25</option>
            <option value="50" <?php selected( $this->selected_per_page, '50' ); ?>>50</option>
            <option value="100" <?php selected( $this->selected_per_page, '100' ); ?>>100</option>
            <option value="all" <?php selected( $this->selected_per_page, 'all' ); ?>><?php esc_html_e( 'All', 'volunteer-exchange-platform' ); ?></option>
        </select>
        <?php
    }

    /**
     * Get status option labels.
     *
     * @return array<string, string>
     */
    private function get_status_options() {
        return AgreementStatus::labels_with_all();
    }

    /**
     * Count agreements per status for the view links.
     *
     * @return array<string, int>
     */
    private function get_status_counts() {
        $counts = array_fill_keys( array_keys( $this->get_status_options() ), 0 );
        $counts['all'] = 0;

        foreach ( $this->event_service->get_agreements_for_event( $this->event_id ) as $agreement ) {
            $status = (string) ( $agreement->status ?? '' );
            $counts['all']++;

            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ]++;
            }
        }

        return $counts;
    }

    /**
     * Build query args for links in this table.
     *
     * @param array<string, mixed> $overrides Query overrides.
     * @return array<string, mixed>
     */
    private function get_query_args( array $overrides = array() ) {
        $args = array(
            'page'   => 'volunteer-exchange-events',
            'action' => 'view',
            'id'     => $this->event_id,
        );

        $status = $this->get_status_filter();
        $search = $this->get_search_term();
        $orderby_raw = isset( $_GET['orderby'] ) ? wp_unslash( $_GET['orderby'] ) : null;
        $order_raw   = isset( $_GET['order'] )   ? wp_unslash( $_GET['order'] )   : null;
        $paged_raw   = isset( $_GET['paged'] )   ? absint( wp_unslash( $_GET['paged'] ) ) : null;

        if ( 'all' !== $status ) {
            $args['agreement_status'] = $status;
        }

        if ( '' !== $search ) {
            $args['s'] = $search;
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
                continue;
            }

            $args[ $key ] = $value;
        }

        return $args;
    }

    /**
     * Get view URL.
     *
     * @param int $agreement_id Agreement ID.
     * @return string
     */
    private function get_view_url( $agreement_id ) {
        return add_query_arg(
            array(
                'page'      => 'volunteer-exchange-events',
                'action'    => 'view-agreement',
                'id'        => $agreement_id,
                'return_id' => $this->event_id,
            ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * Get edit URL.
     *
     * @param int $agreement_id Agreement ID.
     * @return string
     */
    private function get_edit_url( $agreement_id ) {
        return add_query_arg(
            array(
                'page'      => 'volunteer-exchange-events',
                'action'    => 'edit-agreement',
                'id'        => $agreement_id,
                'return_id' => $this->event_id,
            ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * Get delete URL.
     *
     * @param int $agreement_id Agreement ID.
     * @return string
     */
    private function get_delete_url( $agreement_id ) {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'page'      => 'volunteer-exchange-events',
                    'action'    => 'delete-agreement',
                    'id'        => $agreement_id,
                    'return_id' => $this->event_id,
                ),
                admin_url( 'admin.php' )
            ),
            'vep_agreement_delete_' . $agreement_id
        );
    }

    /**
     * Get participant view URL under the event page.
     *
     * @param int $participant_id Participant ID.
     * @return string
     */
    private function get_participant_view_url( $participant_id ) {
        return add_query_arg(
            array(
                'page'      => 'volunteer-exchange-events',
                'action'    => 'view-participant',
                'id'        => $participant_id,
                'return_id' => $this->event_id,
            ),
            admin_url( 'admin.php' )
        );
    }
}