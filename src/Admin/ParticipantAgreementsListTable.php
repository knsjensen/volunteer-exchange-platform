<?php
/**
 * Participant agreements list table for the event admin participant view.
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\AgreementStatus;
use VolunteerExchangePlatform\Services\ParticipantService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class ParticipantAgreementsListTable extends \WP_List_Table {
    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * @var int
     */
    private $participant_id;

    /**
     * @var int
     */
    private $event_id;

    /**
     * @var string
     */
    private $selected_status = 'all';

    /**
     * Constructor.
     *
     * @param int                     $participant_id Participant ID.
     * @param int                     $event_id Event ID.
     * @param ParticipantService|null $participant_service Participant service instance.
     */
    public function __construct( $participant_id, $event_id, ?ParticipantService $participant_service = null ) {
        $this->participant_id = absint( $participant_id );
        $this->event_id = absint( $event_id );
        $this->participant_service = $participant_service ?: new ParticipantService();

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
            'id'                => __( 'Id', 'volunteer-exchange-platform' ),
            'other_participant' => __( 'Other Participant', 'volunteer-exchange-platform' ),
            'initiator'         => __( 'Initiator', 'volunteer-exchange-platform' ),
            'description'       => __( 'Description', 'volunteer-exchange-platform' ),
            'created_at'        => __( 'Created', 'volunteer-exchange-platform' ),
        );
    }

    /**
     * Get sortable columns.
     *
     * @return array<string, array{0:string,1:bool}>
     */
    protected function get_sortable_columns() {
        return array(
            'id'                => array( 'id', true ),
            'other_participant' => array( 'other_participant', false ),
            'initiator'         => array( 'initiator', false ),
            'created_at'        => array( 'created_at', false ),
        );
    }

    /**
     * Empty state.
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
        $search_term = $this->get_search_term();
        $status_filter = $this->get_status_filter();
        $orderby_raw = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $order_raw = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $orderby = $orderby_raw ? sanitize_key( $orderby_raw ) : 'id';
        $order = 'asc' === strtolower( (string) $order_raw ) ? 'asc' : 'desc';

        $items = $this->participant_service->get_agreements_for_participant( $this->participant_id );
        $items = $this->filter_items_by_status( $items, $status_filter );
        $items = $this->filter_items( $items, $search_term );
        $items = $this->sort_items( $items, $orderby, $order );
        $this->selected_status = $status_filter;
        $this->items = $items;

        $this->set_pagination_args(
            array(
                'total_items' => count( $items ),
                'per_page'    => count( $items ),
                'total_pages' => 1,
            )
        );

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );
    }

    /**
     * Render status views.
     *
     * @return array<string, string>
     */
    protected function get_views() {
        $counts = $this->get_status_counts();
        $views = array();

        foreach ( $this->get_status_options() as $status_value => $status_label ) {
            $url = add_query_arg(
                $this->get_query_args(
                    array(
                        'participant_agreement_status' => 'all' === $status_value ? false : $status_value,
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
     * Render the search box only on top.
     *
     * @param string $which Table position.
     * @return void
     */
    public function display_tablenav( $which ) {
        if ( 'bottom' === $which ) {
            return;
        }

        ?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">
            <div class="alignleft actions">
                <label class="screen-reader-text" for="vep-participant-agreements-search"><?php esc_html_e( 'Search agreements', 'volunteer-exchange-platform' ); ?></label>
                <input
                    type="search"
                    id="vep-participant-agreements-search"
                    name="agreement_s"
                    value="<?php echo esc_attr( $this->get_search_term() ); ?>"
                    placeholder="<?php esc_attr_e( 'Type search words here...', 'volunteer-exchange-platform' ); ?>"
                >
                <button type="submit" class="button"><?php esc_html_e( 'Search', 'volunteer-exchange-platform' ); ?></button>
            </div>
            <br class="clear" />
        </div>
        <?php
    }

    /**
     * Render the ID column.
     *
     * @param object $item Agreement row.
     * @return string
     */
    protected function column_id( $item ) {
        $agreement_id = isset( $item->id ) ? (int) $item->id : 0;

        $actions = array(
            'view' => sprintf(
                '<a href="%s" data-agreement-view-modal="%d">%s</a>',
                esc_url( $this->get_agreement_url( 'view-agreement', $agreement_id ) ),
                $agreement_id,
                __( 'View', 'volunteer-exchange-platform' )
            ),
            'edit' => sprintf(
                '<a href="%s" data-agreement-edit-modal="%d">%s</a>',
                esc_url( $this->get_agreement_url( 'edit-agreement', $agreement_id ) ),
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
            '<a class="row-title" href="' . esc_url( $this->get_agreement_url( 'view-agreement', $agreement_id ) ) . '" data-agreement-view-modal="' . esc_attr( (string) $agreement_id ) . '">' . esc_html( (string) $agreement_id ) . '</a>',
            $this->row_actions( $actions )
        );
    }

    /**
     * Render default column values.
     *
     * @param object $item Agreement row.
     * @param string $column_name Column name.
     * @return string
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'other_participant':
                return $this->render_participant_link(
                    isset( $item->other_participant_id ) ? (int) $item->other_participant_id : 0,
                    $this->format_participant_label( $item->other_participant_number ?? 0, $item->other_participant_name ?? '' )
                );
            case 'initiator':
                return esc_html( (string) ( $item->initiator_name ?? '' ) );
            case 'description':
                return esc_html( wp_trim_words( (string) ( $item->description ?? '' ), 10 ) );
            case 'created_at':
                $timestamp = strtotime( (string) ( $item->created_at ?? '' ) );
                // Vis både dato og tidspunkt
                return $timestamp ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ) : '';
            default:
                return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
        }
    }

    /**
     * Render a participant link when possible.
     *
     * @param int    $participant_id Participant ID.
     * @param string $label Label.
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
     * Format participant label.
     *
     * @param int    $participant_number Participant number.
     * @param string $participant_name Participant name.
     * @return string
     */
    private function format_participant_label( $participant_number, $participant_name ) {
        $participant_name = trim( (string) $participant_name );
        $participant_number = (int) $participant_number;

        if ( '' === $participant_name ) {
            return '';
        }

        if ( $participant_number > 0 ) {
            return sprintf( '#%1$d %2$s', $participant_number, $participant_name );
        }

        return $participant_name;
    }

    /**
     * Get search term.
     *
     * @return string
     */
    private function get_search_term() {
        $search_raw = filter_input( INPUT_GET, 'agreement_s', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        return $search_raw ? trim( sanitize_text_field( $search_raw ) ) : '';
    }

    /**
     * Get selected status filter.
     *
     * @return string
     */
    private function get_status_filter() {
        $status_raw = filter_input( INPUT_GET, 'participant_agreement_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $status = $status_raw ? sanitize_key( $status_raw ) : 'all';

        return array_key_exists( $status, $this->get_status_options() ) ? $status : 'all';
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
     * Count items per status.
     *
     * @return array<string, int>
     */
    private function get_status_counts() {
        $counts = array(
            'all'       => 0,
            'active'    => 0,
            'completed' => 0,
            'cancelled' => 0,
        );

        $items = $this->participant_service->get_agreements_for_participant( $this->participant_id );
        foreach ( $items as $item ) {
            $status = isset( $item->status ) ? sanitize_key( (string) $item->status ) : 'active';
            if ( ! isset( $counts[ $status ] ) ) {
                $status = 'active';
            }

            ++$counts['all'];
            ++$counts[ $status ];
        }

        return $counts;
    }

    /**
     * Filter items by status.
     *
     * @param array  $items Items.
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
                static function ( $item ) use ( $status_filter ) {
                    $status = isset( $item->status ) ? sanitize_key( (string) $item->status ) : 'active';
                    return $status === $status_filter;
                }
            )
        );
    }

    /**
     * Filter items by search term.
     *
     * @param array  $items Items.
     * @param string $search Search term.
     * @return array
     */
    private function filter_items( array $items, $search ) {
        if ( '' === $search ) {
            return $items;
        }

        $needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );

        return array_values(
            array_filter(
                $items,
                static function ( $item ) use ( $needle ) {
                    $fields = array(
                        (string) ( $item->id ?? '' ),
                        (string) ( $item->other_participant_name ?? '' ),
                        (string) ( $item->initiator_name ?? '' ),
                        (string) ( $item->description ?? '' ),
                    );

                    foreach ( $fields as $field ) {
                        $haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $field ) : strtolower( $field );
                        if ( false !== strpos( $haystack, $needle ) ) {
                            return true;
                        }
                    }

                    return false;
                }
            )
        );
    }

    /**
     * Sort items.
     *
     * @param array  $items Items.
     * @param string $orderby Order by field.
     * @param string $order Sort order.
     * @return array
     */
    private function sort_items( array $items, $orderby, $order ) {
        usort(
            $items,
            static function ( $left, $right ) use ( $orderby, $order ) {
                switch ( $orderby ) {
                    case 'other_participant':
                        $left_value = (string) ( $left->other_participant_name ?? '' );
                        $right_value = (string) ( $right->other_participant_name ?? '' );
                        break;
                    case 'initiator':
                        $left_value = (string) ( $left->initiator_name ?? '' );
                        $right_value = (string) ( $right->initiator_name ?? '' );
                        break;
                    case 'created_at':
                        $left_value = strtotime( (string) ( $left->created_at ?? '' ) );
                        $right_value = strtotime( (string) ( $right->created_at ?? '' ) );
                        break;
                    case 'id':
                    default:
                        $left_value = (int) ( $left->id ?? 0 );
                        $right_value = (int) ( $right->id ?? 0 );
                        break;
                }

                if ( $left_value === $right_value ) {
                    return 0;
                }

                $result = $left_value < $right_value ? -1 : 1;
                return 'asc' === $order ? $result : -$result;
            }
        );

        return $items;
    }

    /**
     * Build query args for links/forms.
     *
     * @param array<string, mixed> $overrides Overrides.
     * @return array<string, mixed>
     */
    private function get_query_args( array $overrides = array() ) {
        $args = array(
            'page'      => 'volunteer-exchange-events',
            'action'    => 'view-participant',
            'id'        => $this->participant_id,
            'return_id' => $this->event_id,
        );

        $search = $this->get_search_term();
        $status = $this->get_status_filter();
        $orderby_raw = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $order_raw = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        if ( '' !== $search ) {
            $args['agreement_s'] = $search;
        }

        if ( 'all' !== $status ) {
            $args['participant_agreement_status'] = $status;
        }

        if ( $orderby_raw ) {
            $args['orderby'] = sanitize_key( $orderby_raw );
        }

        if ( $order_raw ) {
            $args['order'] = sanitize_key( $order_raw );
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
     * Build agreement route URL.
     *
     * @param string $action Action.
     * @param int    $agreement_id Agreement ID.
     * @return string
     */
    private function get_agreement_url( $action, $agreement_id ) {
        return add_query_arg(
            array(
                'page'                  => 'volunteer-exchange-events',
                'action'                => $action,
                'id'                    => $agreement_id,
                'return_id'             => $this->event_id,
                'return_participant_id' => $this->participant_id,
            ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * Build delete URL.
     *
     * @param int $agreement_id Agreement ID.
     * @return string
     */
    private function get_delete_url( $agreement_id ) {
        return wp_nonce_url(
            $this->get_agreement_url( 'delete-agreement', $agreement_id ),
            'vep_agreement_delete_' . $agreement_id
        );
    }

    /**
     * Build participant view URL.
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
