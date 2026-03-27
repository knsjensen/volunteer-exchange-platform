<?php
/**
 * Participant types list table
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

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Participant Types list table
 * 
 * Extends WP_List_Table to display a sortable list of participant types
 * with quick action links.
 * 
 * @package VolunteerExchangePlatform\Admin
 */
class ParticipantTypesListTable extends \WP_List_Table {
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
        parent::__construct(array(
            'singular' => 'type',
            'plural' => 'types',
            'ajax' => false
        ));
    }

    /**
     * Build URL for row action with nonce.
     *
     * @param string $action Action name.
     * @param int    $type_id Participant type ID.
     * @return string
     */
    private function get_row_action_url( $action, $type_id ) {
        $url = add_query_arg(
            array(
                'page' => 'volunteer-exchange-participant-types',
                'action' => $action,
                'id' => (int) $type_id,
            ),
            admin_url( 'admin.php' )
        );

        return wp_nonce_url( $url, 'vep_participant_type_' . $action . '_' . (int) $type_id );
    }
    
    /**
        * Get table columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'icon' => __('Icon', 'volunteer-exchange-platform'),
            'name' => __('Name', 'volunteer-exchange-platform'),
            'description' => __('Description', 'volunteer-exchange-platform')
        );
    }
    
    /**
     * Get sortable columns
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return array(
            'name' => array('name', false)
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
            case 'description':
                return wp_trim_words($item->$column_name, 15);
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
        return sprintf('<input type="checkbox" name="type[]" value="%s" />', $item->id);
    }
    
    /**
     * Column name with actions
     *
     * @param object $item
     * @return string
     */
    protected function column_name($item) {
        $actions = array(
            'edit' => sprintf(
                '<a href="?page=%s&action=%s&id=%s">%s</a>',
                'volunteer-exchange-participant-types',
                'edit',
                $item->id,
                __('Edit', 'volunteer-exchange-platform')
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                esc_url( $this->get_row_action_url( 'delete', $item->id ) ),
                __('Are you sure you want to delete this participant type?', 'volunteer-exchange-platform'),
                __('Delete', 'volunteer-exchange-platform')
            )
        );
        
        return sprintf('%1$s %2$s', $item->name, $this->row_actions($actions));
    }

    /**
     * Column icon
     *
     * @param object $item
     * @return string
     */
    protected function column_icon($item) {
        if (empty($item->icon)) {
            return '';
        }

        $icon_value = $item->icon;
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

        if (strpos($icon_name, 'fa-') === 0) {
            $icon_name = substr($icon_name, 3);
        }

        return sprintf(
            '<i class="%s fa-%s" style="font-size: 20px;"></i>',
            esc_attr($icon_class),
            esc_attr($icon_name)
        );
    }
    
    /**
        * Prepare list table items.
     *
     * Fetches and prepares the list of participant types based on sorting
     *
     * @return void
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();

        $orderby_raw = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $order_raw = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $orderby = $orderby_raw ? sanitize_key( $orderby_raw ) : 'name';
        $order_param = $order_raw ? sanitize_key( $order_raw ) : '';
        $order = 'desc' === $order_param ? 'DESC' : 'ASC';

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