<?php
/**
 * Tag service
 *
 * @package VEP
 * @subpackage Services
 */

namespace VolunteerExchangePlatform\Services;

use VolunteerExchangePlatform\Database\TagRepository;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TagService extends AbstractService {
    private $repository;

    /**
     * Constructor.
     *
     * @param TagRepository|null $repository Tag repository instance.
     * @return void
     */
    public function __construct( ?TagRepository $repository = null ) {
        $this->repository = $repository ?: new TagRepository();
    }

    /**
     * Get tag by ID.
     *
     * @param int $tag_id Tag ID.
     * @return object|null
     */
    public function get_by_id( $tag_id ) {
        if ( ! $this->is_valid_id( $tag_id ) ) {
            return null;
        }

        return $this->run_guarded(
            function () use ( $tag_id ) {
                return $this->repository->get_by_id( (int) $tag_id );
            },
            null
        );
    }

    /**
     * Get all tags for select controls.
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
     * Get paginated tags.
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
     * Count all tags.
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
     * Create tag.
     *
     * @param array $data Tag data.
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
     * Update tag.
     *
     * @param int   $tag_id Tag ID.
     * @param array $data Tag data.
     * @return bool
     */
    public function update_tag( $tag_id, $data ) {
        if ( ! $this->is_valid_id( $tag_id ) ) {
            return false;
        }

        $normalized = $this->normalize_data( $data );
        if ( false === $normalized ) {
            return false;
        }

        if ( ! $this->is_name_unique( $normalized['name'], (int) $tag_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $tag_id, $normalized ) {
                return $this->repository->update_tag( (int) $tag_id, $normalized );
            },
            false
        );
    }

    /**
     * Delete tag.
     *
     * @param int $tag_id Tag ID.
     * @return bool
     */
    public function delete_tag( $tag_id ) {
        if ( ! $this->is_valid_id( $tag_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $tag_id ) {
                return $this->repository->delete_tag( (int) $tag_id );
            },
            false
        );
    }
}
