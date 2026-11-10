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

    /**
     * @var int  0 = all
     */
    private $per_page_option;

    /**
     * @var string  YYYY-MM-DD or empty
     */
    private $date_from;

    /**
     * @var string  YYYY-MM-DD or empty
     */
    private $date_to;

    /**
     * @var string
     */
    private $search_email;

    /**
     * @var int
     */
    private $total_items = 0;

    public function __construct( ?\wpdb $wpdb_instance = null ) {
        global $wpdb;

        $this->wpdb = $wpdb_instance ? $wpdb_instance : $wpdb;
        $this->table = $this->wpdb->prefix . 'vep_transactional_emails';

        $allowed_statuses = array( 'all', 'pending', 'processing', 'sent', 'failed' );
        $status_filter = isset( $_GET['vep_email_status'] ) ? sanitize_key( wp_unslash( $_GET['vep_email_status'] ) ) : 'all';
        $this->status_filter = in_array( $status_filter, $allowed_statuses, true ) ? $status_filter : 'all';

        $allowed_per_page = array( 50, 100, 500, 0 );
        $per_page_raw     = isset( $_GET['vep_per_page'] ) ? (int) $_GET['vep_per_page'] : 50;
        $this->per_page_option = in_array( $per_page_raw, $allowed_per_page, true ) ? $per_page_raw : 50;

        $date_from_raw   = isset( $_GET['vep_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['vep_date_from'] ) ) : '';
        $date_to_raw     = isset( $_GET['vep_date_to'] )   ? sanitize_text_field( wp_unslash( $_GET['vep_date_to'] ) )   : '';
        $date_pattern    = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/';
        $this->date_from = preg_match( $date_pattern, $date_from_raw ) ? $date_from_raw : '';
        $this->date_to   = preg_match( $date_pattern, $date_to_raw )   ? $date_to_raw   : '';

        $this->search_email = isset( $_GET['vep_search_email'] )
            ? sanitize_text_field( wp_unslash( $_GET['vep_search_email'] ) )
            : '';

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
            'id'                  => 'Id',
            'status'              => __( 'Status', 'volunteer-exchange-platform' ),
            'date'                => __( 'Date', 'volunteer-exchange-platform' ),
            'to'                  => __( 'To', 'volunteer-exchange-platform' ),
            'template_key'        => __( 'Template Key', 'volunteer-exchange-platform' ),
            'subject'             => __( 'Subject', 'volunteer-exchange-platform' ),
            'attempts'            => __( 'Attempts', 'volunteer-exchange-platform' ),
            'provider_message_id' => __( 'Provider Message ID', 'volunteer-exchange-platform' ),
            'last_error'          => __( 'Last Error', 'volunteer-exchange-platform' ),
            'payload'             => __( 'Payload', 'volunteer-exchange-platform' ),
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
     * Render rows-per-page dropdown above the table.
     *
     * @param string $which top|bottom.
     * @return void
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }
        // Hide WP's built-in item count – we render it ourselves on the right.
        echo '<style>.tablenav.top .tablenav-pages .displaying-num { display:none; }</style>';
        $options = array(
            50  => '50',
            100 => '100',
            500 => '500',
            0   => __( 'All', 'volunteer-exchange-platform' ),
        );
        echo '<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:4px;">';

        // Date + search filter – left.
        echo '<div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">';
        echo '<label for="vep-date-from">' . esc_html__( 'From', 'volunteer-exchange-platform' ) . ':</label>';
        printf(
            '<input type="datetime-local" id="vep-date-from" name="vep_date_from" value="%s" style="padding:2px 4px;">',
            esc_attr( $this->date_from )
        );
        echo '<label for="vep-date-to">' . esc_html__( 'To', 'volunteer-exchange-platform' ) . ':</label>';
        printf(
            '<input type="datetime-local" id="vep-date-to" name="vep_date_to" value="%s" style="padding:2px 4px;">',
            esc_attr( $this->date_to )
        );
        printf(
            '<input type="search" id="vep-search-email" name="vep_search_email" value="%s" placeholder="%s" style="padding:2px 6px; min-width:200px;">',
            esc_attr( $this->search_email ),
            esc_attr__( 'Search email addresses', 'volunteer-exchange-platform' )
        );
        echo '<button type="submit" class="button">' . esc_html__( 'Filter', 'volunteer-exchange-platform' ) . '</button>';
        if ( $this->date_from || $this->date_to || '' !== $this->search_email ) {
            $clear_url = add_query_arg(
                array(
                    'page'         => 'vep-settings',
                    'tab'          => 'transactional-emails',
                    'vep_per_page' => $this->per_page_option,
                ),
                admin_url( 'admin.php' )
            );
            echo ' <a href="' . esc_url( $clear_url ) . '" class="button">' . esc_html__( 'Clear', 'volunteer-exchange-platform' ) . '</a>';
        }
        echo ' <button type="button" id="vep-refresh-emails-btn" class="button">&#x21bb; ' . esc_html__( 'Update', 'volunteer-exchange-platform' ) . '</button>';
        echo '</div>';

        // Rows per page + total count – right.
        echo '<div style="display:flex; align-items:center; gap:8px;">';
        echo '<label for="vep-per-page">' . esc_html__( 'Rows per page', 'volunteer-exchange-platform' ) . ':</label>';
        echo '<select id="vep-per-page" name="vep_per_page" onchange="this.form.submit()">';
        foreach ( $options as $value => $label ) {
            printf(
                '<option value="%d"%s>%s</option>',
                (int) $value,
                selected( $this->per_page_option, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
        echo '<span style="color:#666;">' . sprintf(
            /* translators: %d: number of items */
            esc_html( _n( '%d item', '%d items', $this->total_items, 'volunteer-exchange-platform' ) ),
            (int) $this->total_items
        ) . '</span>';
        echo '</div>';

        echo '</div>';
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
                    'page'             => 'vep-settings',
                    'tab'              => 'transactional-emails',
                    'vep_email_status' => $key,
                    'vep_per_page'     => $this->per_page_option,
                    'vep_date_from'    => $this->date_from,
                    'vep_date_to'      => $this->date_to,
                    'vep_search_email' => $this->search_email,
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
        $current_page = ( 0 === $this->per_page_option ) ? 1 : $this->get_pagenum();

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
        $where_parts = array();
        if ( 'all' !== $this->status_filter ) {
            $where_parts[] = 'status = %s';
            $where_args[]  = $this->status_filter;
        }
        if ( '' !== $this->date_from ) {
            $where_parts[] = 'COALESCE(sent_at, scheduled_at) >= %s';
            $where_args[]  = get_gmt_from_date( str_replace( 'T', ' ', $this->date_from ) . ':00' );
        }
        if ( '' !== $this->date_to ) {
            $where_parts[] = 'COALESCE(sent_at, scheduled_at) <= %s';
            $where_args[]  = get_gmt_from_date( str_replace( 'T', ' ', $this->date_to ) . ':59' );
        }
        if ( '' !== $this->search_email ) {
            $where_parts[] = "JSON_SEARCH(payload, 'one', %s, NULL, '$.to[*]') IS NOT NULL";
            $where_args[]  = '%' . $this->wpdb->esc_like( $this->search_email ) . '%';
        }
        if ( ! empty( $where_parts ) ) {
            $where_sql = ' WHERE ' . implode( ' AND ', $where_parts );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is controlled from $wpdb->prefix.
        $count_sql = "SELECT COUNT(*) FROM {$this->table}{$where_sql}";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared before execution.
        $prepared_count_sql = empty( $where_args ) ? $count_sql : $this->wpdb->prepare( $count_sql, $where_args );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin listing query.
        $total_items = (int) $this->wpdb->get_var( $prepared_count_sql );
        $this->total_items = $total_items;

        $per_page = ( 0 === $this->per_page_option ) ? max( $total_items, 1 ) : $this->per_page_option;
        $offset   = ( $current_page - 1 ) * $per_page;

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
            // Stored as UTC; display in site-local time.
            if ( '' !== $date && '0000-00-00 00:00:00' !== $date ) {
                $date = get_date_from_gmt( $date, 'Y-m-d H:i:s' );
            }

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
                'payload_json'        => (string) $row->payload,
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
     * Render the ID column with a payload viewer button.
     *
     * @param object $item Item.
     * @return string
     */
    protected function column_id( $item ) {
        return esc_html( (string) (int) $item->id );
    }

    /**
     * Render the payload column with a viewer button.
     *
     * @param object $item Item.
     * @return string
     */
    protected function column_payload( $item ) {
        $payload_json = isset( $item->payload_json ) ? (string) $item->payload_json : '{}';

        $decoded = json_decode( $payload_json, true );
        $pretty  = is_array( $decoded )
            ? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
            : $payload_json;

        return sprintf(
            '<button type="button" class="button button-small vep-payload-view-btn" data-payload="%s" aria-label="%s">%s</button>',
            esc_attr( $pretty ),
            esc_attr__( 'View payload', 'volunteer-exchange-platform' ),
            esc_html__( 'Vis payload', 'volunteer-exchange-platform' )
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
