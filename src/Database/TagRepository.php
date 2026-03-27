<?php
/**
 * Tag repository
 *
 * @package VEP
 * @subpackage Database
 */

namespace VolunteerExchangePlatform\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TagRepository extends AbstractRepository {
    protected $table = 'vep_we_offer_tags';

    /**
     * Get tag by ID.
     *
     * @param int $tag_id Tag ID.
     * @return object|null
     */
    public function get_by_id( $tag_id ) {
        return $this->get_row( "SELECT * FROM {$this->table()} WHERE id = %d", array( $tag_id ) );
    }

    /**
     * Get all tags for select controls.
     *
     * @return array
     */
    public function get_all_for_select() {
        return $this->get_results( "SELECT id, name FROM {$this->table()} ORDER BY name" );
    }

    /**
     * Get paginated tags.
     *
     * @param int    $per_page Items per page.
     * @param int    $offset Offset.
     * @param string $orderby Order by field.
     * @param string $order Sort order.
     * @return array
     */
    public function get_paginated( $per_page = 20, $offset = 0, $orderby = 'name', $order = 'ASC' ) {
        $allowed_orderby = array( 'name' );
        $orderby = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'name';
        $order = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';

        return $this->get_results(
            "SELECT * FROM {$this->table()} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            array( $per_page, $offset )
        );
    }

    /**
     * Count all tags.
     *
     * @return int
     */
    public function count_all() {
        return (int) $this->get_var( "SELECT COUNT(*) FROM {$this->table()}" );
    }

    /**
     * Create tag.
     *
     * @param array $data Tag data.
     * @return int|false
     */
    public function create( $data ) {
        return $this->insert( $data );
    }

    /**
     * Update tag.
     *
     * @param int   $tag_id Tag ID.
     * @param array $data Tag data.
     * @return int|false
     */
    public function update_tag( $tag_id, $data ) {
        return $this->update( $data, array( 'id' => $tag_id ), array(), array( '%d' ) );
    }

    /**
     * Delete tag.
     *
     * @param int $tag_id Tag ID.
     * @return int|false
     */
    public function delete_tag( $tag_id ) {
        return $this->delete( array( 'id' => $tag_id ), array( '%d' ) );
    }
}
