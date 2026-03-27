<?php
/**
 * Participant type service
 *
 * @package VEP
 * @subpackage Services
 */

namespace VolunteerExchangePlatform\Services;

use VolunteerExchangePlatform\Database\ParticipantTypeRepository;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParticipantTypeService extends AbstractService {
    private $repository;

    /**
     * Constructor.
     *
     * @param ParticipantTypeRepository|null $repository Participant type repository instance.
     * @return void
     */
    public function __construct( ?ParticipantTypeRepository $repository = null ) {
        $this->repository = $repository ?: new ParticipantTypeRepository();
    }

    /**
     * Get participant type by ID.
     *
     * @param int $type_id Participant type ID.
     * @return object|null
     */
    public function get_by_id( $type_id ) {
        if ( ! $this->is_valid_id( $type_id ) ) {
            return null;
        }

        return $this->run_guarded(
            function () use ( $type_id ) {
                return $this->repository->get_by_id( (int) $type_id );
            },
            null
        );
    }

    /**
     * Get all participant types for select controls.
     *
     * @return array
     */
    public function get_all_for_select() {
        return $this->run_guarded(
            function () {
                return $this->repository->get_all_for_select();
            },
            array()
        );
    }

    /**
     * Get paginated participant types.
     *
     * @param int    $per_page Items per page.
     * @param int    $offset   Offset.
     * @param string $orderby  Order by field.
     * @param string $order    Sort order.
     * @return array
     */
    public function get_paginated( $per_page = 20, $offset = 0, $orderby = 'name', $order = 'ASC' ) {
        $per_page = max( 1, (int) $per_page );
        $offset = max( 0, (int) $offset );

        return $this->run_guarded(
            function () use ( $per_page, $offset, $orderby, $order ) {
                return $this->repository->get_paginated( $per_page, $offset, $orderby, $order );
            },
            array()
        );
    }

    /**
     * Count all participant types.
     *
     * @return int
     */
    public function count_all() {
        return (int) $this->run_guarded(
            function () {
                return $this->repository->count_all();
            },
            0
        );
    }

    private function normalize_data( $data ) {
        $name = sanitize_text_field( $data['name'] ?? '' );
        if ( '' === $name ) {
            return false;
        }

        return array(
            'name' => $name,
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'icon' => $this->sanitize_icon_value( $data['icon'] ?? '' ),
        );
    }

    private function is_name_unique( $name, $ignore_id = 0 ) {
        $existing = $this->get_all_for_select();
        $needle = strtolower( trim( (string) $name ) );

        foreach ( $existing as $row ) {
            $row_id = isset( $row->id ) ? (int) $row->id : 0;
            $row_name = isset( $row->name ) ? strtolower( trim( (string) $row->name ) ) : '';
            if ( $row_id !== (int) $ignore_id && '' !== $row_name && $row_name === $needle ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create participant type.
     *
     * @param array $data Participant type data.
     * @return int|false
     */
    public function create( $data ) {
        $normalized = $this->normalize_data( $data );
        if ( false === $normalized ) {
            return false;
        }

        if ( ! $this->is_name_unique( $normalized['name'] ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $normalized ) {
                return $this->repository->create( $normalized );
            },
            false
        );
    }

    /**
     * Update participant type.
     *
     * @param int   $type_id Participant type ID.
     * @param array $data Participant type data.
     * @return bool
     */
    public function update_type( $type_id, $data ) {
        if ( ! $this->is_valid_id( $type_id ) ) {
            return false;
        }

        $normalized = $this->normalize_data( $data );
        if ( false === $normalized ) {
            return false;
        }

        if ( ! $this->is_name_unique( $normalized['name'], (int) $type_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $type_id, $normalized ) {
                return $this->repository->update_type( (int) $type_id, $normalized );
            },
            false
        );
    }

    /**
     * Delete participant type.
     *
     * @param int $type_id Participant type ID.
     * @return bool
     */
    public function delete_type( $type_id ) {
        if ( ! $this->is_valid_id( $type_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $type_id ) {
                return $this->repository->delete_type( (int) $type_id );
            },
            false
        );
    }
}
