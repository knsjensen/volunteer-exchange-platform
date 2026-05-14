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
            'type'              => 'custom',
            'title'             => '',
            'description'       => '',
            'is_active'         => 1,
            'winner_input_type' => 'dropdown',
            'sort_order'        => 0,
            'custom_data'       => null,
        );
        
        $data = wp_parse_args( $data, $defaults );

        $winner_input_type = in_array( $data['winner_input_type'], array( 'dropdown', 'text' ), true ) ? $data['winner_input_type'] : 'dropdown';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert is required for repository pattern
        $result = $wpdb->insert(
            $table,
            array(
                'type'              => sanitize_text_field( $data['type'] ),
                'title'             => sanitize_text_field( $data['title'] ),
                'description'       => sanitize_textarea_field( $data['description'] ),
                'is_active'         => (int) $data['is_active'],
                'winner_input_type' => $winner_input_type,
                'sort_order'        => (int) $data['sort_order'],
                'custom_data'       => $data['custom_data'],
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
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
     * Get all global competitions (not tied to any event)
     *
     * @return array
     */
    public function get_all() {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        $results = $wpdb->get_results(
            "SELECT * FROM $table ORDER BY sort_order ASC, id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is controlled from $wpdb->prefix.
        );
        
        return $results ?: array();
    }

    /**
     * Get all active global competitions
     *
     * @return array
     */
    public function get_active() {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        $results = $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 ORDER BY sort_order ASC, id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is controlled from $wpdb->prefix.
        );
        
        return $results ?: array();
    }

    /**
     * Get all inactive global competitions
     *
     * @return array
     */
    public function get_inactive() {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        $results = $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 0 ORDER BY sort_order ASC, id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is controlled from $wpdb->prefix.
        );
        
        return $results ?: array();
    }

    /**
     * Get a competition by type (returns first match)
     *
     * @param string $type Competition type.
     * @return object|null
     */
    public function get_by_type( $type ) {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE type = %s LIMIT 1",
                sanitize_text_field( $type )
            )
        );
    }

    /**
     * Get a competition by type and title
     *
     * Used for custom competitions where multiple can share the same type.
     *
     * @param string $type  Competition type.
     * @param string $title Competition title.
     * @return object|null
     */
    public function get_by_type_and_title( $type, $title ) {
        global $wpdb;
        
        $table = $wpdb->prefix . $this->table_name;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query is required for repository pattern
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE type = %s AND title = %s LIMIT 1",
                sanitize_text_field( $type ),
                sanitize_text_field( $title )
            )
        );
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

}

