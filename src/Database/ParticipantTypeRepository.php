<?php
/**
 * Participant type repository
 *
 * @package VEP
 * @subpackage Database
 */

namespace VolunteerExchangePlatform\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParticipantTypeRepository extends AbstractRepository {
    protected $table = 'vep_participant_types';

    /**
     * Get participant type by ID.
     *
     * @param int $type_id Participant type ID.
     * @return object|null
     */
    public function get_by_id( $type_id ) {
        return $this->get_row( "SELECT * FROM {$this->table()} WHERE id = %d", array( $type_id ) );
    }

    /**
     * Get all participant types for select controls.
     *
     * @return array
     */
    public function get_all_for_select() {
        return $this->get_results( "SELECT id, name FROM {$this->table()} ORDER BY name" );
    }

    /**
     * Get paginated participant types.
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
     * Count all participant types.
     *
     * @return int
     */
    public function count_all() {
        return (int) $this->get_var( "SELECT COUNT(*) FROM {$this->table()}" );
    }

    /**
     * Create participant type.
     *
     * @param array $data Participant type data.
     * @return int|false
     */
    public function create( $data ) {
        return $this->insert( $data );
    }

    /**
     * Update participant type.
     *
     * @param int   $type_id Participant type ID.
     * @param array $data Participant type data.
     * @return int|false
     */
    public function update_type( $type_id, $data ) {
        return $this->update( $data, array( 'id' => $type_id ), array(), array( '%d' ) );
    }

    /**
     * Delete participant type.
     *
     * @param int $type_id Participant type ID.
     * @return int|false
     */
    public function delete_type( $type_id ) {
        return $this->delete( array( 'id' => $type_id ), array( '%d' ) );
    }
}
