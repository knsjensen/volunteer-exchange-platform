<?php
/**
 * Abstract repository
 *
 * @package VEP
 * @subpackage Database
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class AbstractRepository {
    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    protected $wpdb;

    /**
     * Base table name without prefix.
     *
     * @var string
     */
    protected $table = '';

    /**
     * Constructor.
     *
     * @param \wpdb|null $wpdb Optional db instance.
     */
    public function __construct( $wpdb = null ) {
        if ( null !== $wpdb ) {
            $this->wpdb = $wpdb;
            return;
        }

        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get full table name.
     *
     * @return string
     */
    protected function table() {
        return $this->wpdb->prefix . $this->table;
    }

    /**
     * Get one row.
     *
     * @param string $sql SQL with placeholders.
     * @param array  $params Params.
     * @return object|null
     */
    protected function get_row( $sql, $params = array() ) {
        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Placeholder SQL is prepared here before execution.
            return $this->wpdb->get_row( $this->wpdb->prepare( $sql, $params ) );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query strings passed here are internal, static repository queries without user input.
        return $this->wpdb->get_row( $sql );
    }

    /**
     * Get multiple rows.
     *
     * @param string $sql SQL with placeholders.
     * @param array  $params Params.
     * @return array
     */
    protected function get_results( $sql, $params = array() ) {
        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Placeholder SQL is prepared here before execution.
            return $this->wpdb->get_results( $this->wpdb->prepare( $sql, $params ) );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query strings passed here are internal, static repository queries without user input.
        return $this->wpdb->get_results( $sql );
    }

    /**
     * Get single scalar value.
     *
     * @param string $sql SQL with placeholders.
     * @param array  $params Params.
     * @return string|null
     */
    protected function get_var( $sql, $params = array() ) {
        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Placeholder SQL is prepared here before execution.
            return $this->wpdb->get_var( $this->wpdb->prepare( $sql, $params ) );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query strings passed here are internal, static repository queries without user input.
        return $this->wpdb->get_var( $sql );
    }

    /**
     * Insert data.
     *
     * @param array $data Data.
     * @param array $format Format.
     * @return int|false
     */
    protected function insert( $data, $format = array() ) {
        return $this->wpdb->insert( $this->table(), $data, $format );
    }

    /**
     * Update data.
     *
     * @param array $data Data.
     * @param array $where Where.
     * @param array $format Format.
     * @param array $where_format Where format.
     * @return int|false
     */
    protected function update( $data, $where, $format = array(), $where_format = array() ) {
        return $this->wpdb->update( $this->table(), $data, $where, $format, $where_format );
    }

    /**
     * Delete data.
     *
     * @param array $where Where.
     * @param array $where_format Where format.
     * @return int|false
     */
    protected function delete( $where, $where_format = array() ) {
        return $this->wpdb->delete( $this->table(), $where, $where_format );
    }
}
