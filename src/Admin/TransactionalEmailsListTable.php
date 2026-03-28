<?php
/**
 * Transactional emails list table.
 *
 * @package VEP
 * @subpackage Admin
 */

namespace VolunteerExchangePlatform\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TransactionalEmailsListTable extends \WP_List_Table {
    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $status_filter;

    public function __construct( ?\wpdb $wpdb_instance = null ) {
        global $wpdb;

        $this->wpdb = $wpdb_instance ? $wpdb_instance : $wpdb;
        $this->table = $this->wpdb->prefix . 'vep_transactional_emails';

        $allowed_statuses = array( 'all', 'pending', 'processing', 'sent', 'failed' );
        $status_filter = isset( $_GET['vep_email_status'] ) ? sanitize_key( wp_unslash( $_GET['vep_email_status'] ) ) : 'all';
        $this->status_filter = in_array( $status_filter, $allowed_statuses, true ) ? $status_filter : 'all';

        parent::__construct(
            array(
                'singular' => 'transactional_email',
                'plural'   => 'transactional_emails',
                'ajax'     => false,
            )
        );
    }

    /**
     * Get columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'id'                  => __( 'ID', 'volunteer-exchange-platform' ),
            'status'              => __( 'Status', 'volunteer-exchange-platform' ),
            'date'                => __( 'Date', 'volunteer-exchange-platform' ),
            'to'                  => __( 'To', 'volunteer-exchange-platform' ),
            'template_key'        => __( 'Template Key', 'volunteer-exchange-platform' ),
            'subject'             => __( 'Subject', 'volunteer-exchange-platform' ),
            'attempts'            => __( 'Attempts', 'volunteer-exchange-platform' ),
            'provider_message_id' => __( 'Provider Message ID', 'volunteer-exchange-platform' ),
            'last_error'          => __( 'Last Error', 'volunteer-exchange-platform' ),
        );
    }

    /**
     * Get sortable columns.
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return array(
            'id'   => array( 'id', true ),
            'date' => array( 'date', false ),
        );
    }

    /**
     * Get view filters above table.
     *
     * @return array
     */
    protected function get_views() {
        $counts = array(
            'all'        => 0,
            'pending'    => 0,
            'processing' => 0,
            'sent'       => 0,
            'failed'     => 0,
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is controlled from $wpdb->prefix.
        $count_sql = "SELECT status, COUNT(*) as c FROM {$this->table} GROUP BY status";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin listing query.
        $rows = $this->wpdb->get_results( $count_sql );

        $total = 0;
        foreach ( $rows as $row ) {
            $status = isset( $row->status ) ? (string) $row->status : '';
            $count = isset( $row->c ) ? (int) $row->c : 0;
            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ] = $count;
            }
            $total += $count;
        }
        $counts['all'] = $total;

        $labels = array(
            'all'        => __( 'All', 'volunteer-exchange-platform' ),
            'pending'    => __( 'Pending', 'volunteer-exchange-platform' ),
            'processing' => __( 'Processing', 'volunteer-exchange-platform' ),
            'sent'       => __( 'Sent', 'volunteer-exchange-platform' ),
            'failed'     => __( 'Failed', 'volunteer-exchange-platform' ),
        );

        $views = array();
        foreach ( $labels as $key => $label ) {
            $url = add_query_arg(
                array(
                    'page'             => 'vep-email-settings',
                    'tab'              => 'transactional-emails',
                    'vep_email_status' => $key,
                ),
                admin_url( 'admin.php' )
            );

            $class = $this->status_filter === $key ? ' class="current"' : '';
            $text = sprintf( '%s <span class="count">(%d)</span>', esc_html( $label ), (int) $counts[ $key ] );
            $views[ $key ] = '<a href="' . esc_url( $url ) . '"' . $class . '>' . $text . '</a>';
        }

        return $views;
    }

    /**
     * Prepare list items.
     *
     * @return void
     */
    public function prepare_items() {
        $per_page = 50;
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
        $order = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';

        $allowed_orderby = array(
            'id'   => 'id',
            'date' => 'COALESCE(sent_at, scheduled_at)',
        );
        $orderby_sql = isset( $allowed_orderby[ $orderby ] ) ? $allowed_orderby[ $orderby ] : 'id';
        $order_sql = 'asc' === strtolower( $order ) ? 'ASC' : 'DESC';

        $where_sql = '';
        $where_args = array();
        if ( 'all' !== $this->status_filter ) {
            $where_sql = ' WHERE status = %s';
            $where_args[] = $this->status_filter;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is controlled from $wpdb->prefix.
        $count_sql = "SELECT COUNT(*) FROM {$this->table}{$where_sql}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared before execution.
        $prepared_count_sql = empty( $where_args ) ? $count_sql : $this->wpdb->prepare( $count_sql, $where_args );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin listing query.
        $total_items = (int) $this->wpdb->get_var( $prepared_count_sql );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and order clause are controlled.
        $sql = "SELECT id, status, attempts, max_attempts, scheduled_at, sent_at, provider_message_id, last_error, payload
                FROM {$this->table}
                {$where_sql}
                ORDER BY {$orderby_sql} {$order_sql}
                LIMIT %d OFFSET %d";

        $query_args = array_merge( $where_args, array( $per_page, $offset ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared before execution.
        $prepared_sql = $this->wpdb->prepare( $sql, $query_args );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin listing query.
        $rows = $this->wpdb->get_results( $prepared_sql );

        $items = array();
        foreach ( $rows as $row ) {
            $payload = json_decode( (string) $row->payload, true );
            if ( ! is_array( $payload ) ) {
                $payload = array();
            }

            if ( isset( $payload['to'] ) && is_array( $payload['to'] ) ) {
                $recipients = implode( ', ', array_map( 'strval', $payload['to'] ) );
            } else {
                $recipients = isset( $payload['to'] ) ? (string) $payload['to'] : '';
            }

            $template_key = isset( $payload['template_key'] )
                ? (string) $payload['template_key']
                : ( isset( $payload['template'] ) ? (string) $payload['template'] : '' );

            $subject = isset( $payload['subject'] ) ? (string) $payload['subject'] : '';
            $date = ! empty( $row->sent_at ) ? (string) $row->sent_at : (string) $row->scheduled_at;

            $items[] = (object) array(
                'id'                  => (int) $row->id,
                'status'              => (string) $row->status,
                'date'                => $date,
                'to'                  => $recipients,
                'template_key'        => $template_key,
                'subject'             => $subject,
                'attempts'            => (int) $row->attempts . '/' . (int) $row->max_attempts,
                'provider_message_id' => (string) $row->provider_message_id,
                'last_error'          => (string) $row->last_error,
            );
        }

        $this->items = $items;

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
     * Render default column values.
     *
     * @param object $item Item.
     * @param string $column_name Column key.
     * @return string
     */
    protected function column_default( $item, $column_name ) {
        if ( ! isset( $item->{$column_name} ) ) {
            return '';
        }

        return esc_html( (string) $item->{$column_name} );
    }

    /**
     * No items text.
     *
     * @return void
     */
    public function no_items() {
        esc_html_e( 'No transactional emails found.', 'volunteer-exchange-platform' );
    }
}
