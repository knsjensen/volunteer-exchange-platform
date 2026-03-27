<?php
/**
 * Events list table
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\EventService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Events list table
 * 
 * Extends WP_List_Table to display a sortable list of events
 * with quick action links.
 * 
 * @package VolunteerExchangePlatform\Admin
 */
class EventsListTable extends \WP_List_Table {
    /**
     * @var EventService
     */
    private $service;
    
    /**
        * Constructor.
        *
        * @param EventService|null $service Event service instance.
        * @return void
     */
    public function __construct( ?EventService $service = null ) {
        $this->service = $service ?: new EventService();
        parent::__construct(array(
            'singular' => 'event',
            'plural' => 'events',
            'ajax' => false
        ));
    }

    /**
     * Build URL for row action with nonce.
     *
     * @param string $action Action name.
     * @param int    $event_id Event ID.
     * @return string
     */
    private function get_row_action_url( $action, $event_id ) {
        $url = add_query_arg(
            array(
                'page' => 'volunteer-exchange-events',
                'action' => $action,
                'id' => (int) $event_id,
            ),
            admin_url( 'admin.php' )
        );

        return wp_nonce_url( $url, 'vep_event_' . $action . '_' . (int) $event_id );
    }
    
    /**
        * Get table columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'is_active' => __('Active', 'volunteer-exchange-platform'),
            'name' => __('Name', 'volunteer-exchange-platform'),
            'start_date' => __('Start Date', 'volunteer-exchange-platform'),
            'end_date' => __('End Date', 'volunteer-exchange-platform'),            
            'created_at' => __('Created', 'volunteer-exchange-platform')
        );
    }
    
    /**
     * Get sortable columns
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return array(
            'name' => array('name', false),
            'start_date' => array('start_date', false),
            'created_at' => array('created_at', true)
        );
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
            case 'start_date':
            case 'end_date':
                return date_i18n(get_option('date_format'), strtotime($item->$column_name));
            case 'created_at':
                return date_i18n(get_option('date_format'), strtotime($item->$column_name));
            case 'is_active':
                return $item->is_active ? '<span style="color: green;">●</span>' : '<span style="color: gray;">○</span>';
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
        return sprintf('<input type="checkbox" name="event[]" value="%s" />', $item->id);
    }
    
    /**
     * Column name with actions
     *
     * @param object $item
     * @return string
     */
    protected function column_name($item) {
        $actions = array(
            'view' => sprintf(
                '<a href="?page=%s&action=%s&id=%s">%s</a>',
                'volunteer-exchange-events',
                'view',
                $item->id,
                __('View', 'volunteer-exchange-platform')
            ),
            'edit' => sprintf(
                '<a href="?page=%s&action=%s&id=%s">%s</a>',
                'volunteer-exchange-events',
                'edit',
                $item->id,
                __('Edit', 'volunteer-exchange-platform')
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                esc_url( $this->get_row_action_url( 'delete', $item->id ) ),
                __('Are you sure you want to delete this event?', 'volunteer-exchange-platform'),
                __('Delete', 'volunteer-exchange-platform')
            )
        );
        
        if ($item->is_active) {
            $actions['deactivate'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url( $this->get_row_action_url( 'deactivate', $item->id ) ),
                __('Deactivate', 'volunteer-exchange-platform')
            );
        }
        
        return sprintf('%1$s %2$s', $item->name, $this->row_actions($actions));
    }
    
    /**
        * Prepare list table items.
     *
     * Fetches and prepares the list of events based on sorting
     *
     * @return void
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();

        $orderby_raw = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $order_raw = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $orderby = $orderby_raw ? sanitize_key( $orderby_raw ) : 'created_at';
        $order_param = $order_raw ? sanitize_key( $order_raw ) : '';
        $order = 'asc' === $order_param ? 'ASC' : 'DESC';

        $offset = ($current_page - 1) * $per_page;

        $this->items = $this->service->get_paginated($per_page, $offset, $orderby, $order);
        $total_items = $this->service->count_all();
        
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
