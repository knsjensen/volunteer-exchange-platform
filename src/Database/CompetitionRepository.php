<?php
/**
 * Competition Repository
 *
 * @package VEP
 * @subpackage Database
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Database;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Competition Repository class
 *
 * Handles database operations for competitions.
 *
 * @package VolunteerExchangePlatform\Database
 */
class CompetitionRepository extends AbstractRepository {
    
    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'vep_competitions';

    /**
     * Table name (legacy internal usage).
     *
     * @var string
     */
    protected $table_name = 'vep_competitions';

    /**
     * Create a new competition
     *
     * @param array $data Competition data
     * @return int|false Competition ID or false on failure
     */
    public function create( array $data ) {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        $defaults = array(
            'event_id'    => 0,
            'type'        => 'custom',
            'title'       => '',
            'description' => '',
            'is_active'   => 1,
            'winner_id'   => null,
            'sort_order'  => 0,
            'custom_data' => null,
        );
        
        $data = wp_parse_args( $data, $defaults );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert is required for repository pattern
        $result = $wpdb->insert(
            $table,
            array(
                'event_id'    => (int) $data['event_id'],
                'type'        => sanitize_text_field( $data['type'] ),
                'title'       => sanitize_text_field( $data['title'] ),
                'description' => sanitize_textarea_field( $data['description'] ),
                'is_active'   => (int) $data['is_active'],
                'winner_id'   => $data['winner_id'] ? (int) $data['winner_id'] : null,
                'sort_order'  => (int) $data['sort_order'],
                'custom_data' => $data['custom_data'],
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
        );
        
        if ( $result ) {
            return $wpdb->insert_id; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct access is safe here
        }
        
        return false;
    }

    /**
     * Update a competition
     *
     * @param array $data Competition data to update
     * @param array $where Where clause
     * @param array $format Format specifiers
     * @param array $where_format Where format specifiers
     * @return int|false Number of rows updated or false on failure
     */
    public function update( $data, $where = array(), $format = array(), $where_format = array() ) {
        return parent::update( $data, $where, $format, $where_format );
    }

    /**
     * Delete a competition by ID (convenience method)
     *
     * @param int $id Competition ID
     * @return int|false
     */
    public function delete_by_id( $id ) {
        return $this->delete(
            array( 'id' => (int) $id ),
            array( '%d' )
        );
    }

    /**
     * Delete competitions
     *
     * @param array $where Where clause
     * @param array $where_format Where format specifiers
     * @return int|false Number of rows deleted or false on failure
     */
    public function delete( $where = array(), $where_format = array() ) {
        return parent::delete( $where, $where_format );
    }

    /**
     * Get competition by ID
     *
     * @param int $id Competition ID
     * @return object|null
     */
    public function get_by_id( $id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                (int) $id
            )
        );
        
        return $result;
    }

    /**
     * Get all competitions for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public function get_all_for_event( $event_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE event_id = %d ORDER BY sort_order ASC, id ASC",
                (int) $event_id
            )
        );
        
        return $results ?: array();
    }

    /**
     * Get active competitions for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public function get_active_for_event( $event_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE event_id = %d AND is_active = 1 ORDER BY sort_order ASC, id ASC",
                (int) $event_id
            )
        );
        
        return $results ?: array();
    }

    /**
     * Get inactive competitions for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public function get_inactive_for_event( $event_id ) {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE event_id = %d AND is_active = 0 ORDER BY sort_order ASC, id ASC",
                (int) $event_id
            )
        );
        
        return $results ?: array();
    }

    /**
     * Update sort order for multiple competitions
     *
     * @param array $order_map Array of competition_id => sort_order
     * @return bool
     */
    public function update_sort_order( array $order_map ) {
        $global_wpdb = $GLOBALS['wpdb'];
        $table = $global_wpdb->prefix . $this->table_name;
        
        foreach ( $order_map as $competition_id => $sort_order ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct update is required for repository pattern
            $global_wpdb->update(
                $table,
                array( 'sort_order' => (int) $sort_order ),
                array( 'id' => (int) $competition_id ),
                array( '%d' ),
                array( '%d' )
            );
        }
        
        return true;
    }

    /**
     * Set winner for a competition
     *
     * @param int      $id Competition ID
     * @param int|null $winner_id Participant ID (or null to unset)
     * @return bool
     */
    public function set_winner( $id, $winner_id = null ) {
        $global_wpdb = $GLOBALS['wpdb'];
        $table = $global_wpdb->prefix . $this->table_name;
        
        $result = $global_wpdb->update(
            $table,
            array( 'winner_id' => $winner_id ? (int) $winner_id : null ),
            array( 'id' => (int) $id ),
            array( '%d' ),
            array( '%d' )
        );
        
        return $result !== false;
    }

    /**
     * Get competition by event and type
     *
     * @param int    $event_id Event ID
     * @param string $type Competition type
     * @return object|null
     */
    public function get_by_event_and_type( $event_id, $type ) {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE event_id = %d AND type = %s LIMIT 1",
                (int) $event_id,
                sanitize_text_field( $type )
            )
        );
        
        return $result;
    }

    /**
     * Get competition by event, type, and title
     *
     * Used for custom competitions where multiple can have the same type
     *
     * @param int    $event_id Event ID
     * @param string $type Competition type
     * @param string $title Competition title
     * @return object|null Competition object or null
     */
    public function get_by_event_and_type_and_title( $event_id, $type, $title ) {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE event_id = %d AND type = %s AND title = %s LIMIT 1",
                (int) $event_id,
                sanitize_text_field( $type ),
                sanitize_text_field( $title )
            )
        );
        
        return $result;
    }
}
