<?php
/**
 * Event service
 *
 * @package VEP
 * @subpackage Services
 */

namespace VolunteerExchangePlatform\Services;

use VolunteerExchangePlatform\Services\AbstractService;
use VolunteerExchangePlatform\Database\EventRepository;
use VolunteerExchangePlatform\Database\ParticipantRepository;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EventService extends AbstractService {
    private $repository;
    private $participant_repository;

    /**
     * Constructor.
     *
     * @param EventRepository|null       $repository Event repository instance.
     * @param ParticipantRepository|null $participant_repository Participant repository instance.
     * @return void
     */
    public function __construct( ?EventRepository $repository = null, ?ParticipantRepository $participant_repository = null ) {
        $this->repository = $repository ?: new EventRepository();
        $this->participant_repository = $participant_repository ?: new ParticipantRepository();
    }

    /**
     * Get event by ID.
     *
     * @param int $event_id Event ID.
     * @return object|null
     */
    public function get_by_id( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return null;
        }

        return $this->run_guarded(
            function () use ( $event_id ) {
                return $this->repository->get_by_id( (int) $event_id );
            },
            null
        );
    }

    /**
     * Get currently active event.
     *
     * @return object|null
     */
    public function get_active_event() {
        return $this->run_guarded(
            function () {
                return $this->repository->get_active_event();
            },
            null
        );
    }

    /**
     * Get paginated events.
     *
     * @param int    $per_page Items per page.
     * @param int    $offset   Offset.
     * @param string $orderby  Order by field.
     * @param string $order    Sort order.
     * @return array
     */
    public function get_paginated( $per_page = 20, $offset = 0, $orderby = 'created_at', $order = 'DESC' ) {
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
     * Get all events for select controls.
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
     * Count all events.
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

    /**
     * Normalize event datetime input into MySQL datetime format.
     *
     * Accepts values from datetime-local fields (with T) and SQL-like datetimes.
     *
     * @param string $value Datetime input value.
     * @return string|false
     */
    private function normalize_event_datetime( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return false;
        }

        $timezone = wp_timezone();
        $formats = array(
            'Y-m-d\\TH:i',
            'Y-m-d\\TH:i:s',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
        );

        foreach ( $formats as $format ) {
            $date = \DateTimeImmutable::createFromFormat( $format, $value, $timezone );
            if ( ! ( $date instanceof \DateTimeImmutable ) ) {
                continue;
            }

            $errors = \DateTimeImmutable::getLastErrors();
            if ( false === $errors || ( 0 === (int) $errors['warning_count'] && 0 === (int) $errors['error_count'] ) ) {
                return $date->format( 'Y-m-d H:i:s' );
            }
        }

        $timestamp = strtotime( $value );
        if ( false === $timestamp ) {
            return false;
        }

        return wp_date( 'Y-m-d H:i:s', $timestamp, $timezone );
    }

    private function normalize_event_data( $data ) {
        $name = sanitize_text_field( $data['name'] ?? '' );
        if ( '' === $name ) {
            return false;
        }

        $start_date = isset( $data['start_date'] ) ? sanitize_text_field( (string) $data['start_date'] ) : '';
        $end_date = isset( $data['end_date'] ) ? sanitize_text_field( (string) $data['end_date'] ) : '';
        if ( '' === $start_date || '' === $end_date ) {
            return false;
        }

        $start_date_mysql = $this->normalize_event_datetime( $start_date );
        $end_date_mysql = $this->normalize_event_datetime( $end_date );
        if ( false === $start_date_mysql || false === $end_date_mysql ) {
            return false;
        }

        $start_ts = strtotime( $start_date_mysql );
        $end_ts = strtotime( $end_date_mysql );
        if ( false === $start_ts || false === $end_ts || $start_ts > $end_ts ) {
            return false;
        }

        return array(
            'name' => $name,
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'start_date' => $start_date_mysql,
            'end_date' => $end_date_mysql,
        );
    }

    /**
     * Create a new active event.
     *
     * @param array $data Event data.
     * @return int|false
     */
    public function create_active( $data ) {
        $normalized = $this->normalize_event_data( $data );
        if ( false === $normalized ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $normalized ) {
                $this->repository->deactivate_all();
                $normalized['is_active'] = 1;
                return $this->repository->create( $normalized );
            },
            false
        );
    }

    /**
     * Update an existing event.
     *
     * @param int   $event_id Event ID.
     * @param array $data Event data.
     * @return bool
     */
    public function update_event( $event_id, $data ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return false;
        }

        $normalized = $this->normalize_event_data( $data );
        if ( false === $normalized ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $event_id, $normalized ) {
                return $this->repository->update_event( (int) $event_id, $normalized );
            },
            false
        );
    }

    /**
     * Deactivate an event.
     *
     * @param int $event_id Event ID.
     * @return bool
     */
    public function deactivate( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $event_id ) {
                return $this->repository->deactivate( (int) $event_id );
            },
            false
        );
    }

    /**
     * Get agreements for an event.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_agreements_for_event( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return array();
        }

        return $this->run_guarded(
            function () use ( $event_id ) {
                return $this->repository->get_agreements_for_event( (int) $event_id );
            },
            array()
        );
    }

    /**
     * Get agreement by ID.
     *
     * @param int $agreement_id Agreement ID.
     * @return object|null
     */
    public function get_agreement_by_id( $agreement_id ) {
        if ( ! $this->is_valid_id( $agreement_id ) ) {
            return null;
        }

        return $this->run_guarded(
            function () use ( $agreement_id ) {
                return $this->repository->get_agreement_by_id( (int) $agreement_id );
            },
            null
        );
    }

    /**
     * Update agreement.
     *
     * @param int   $agreement_id Agreement ID.
     * @param array $data Agreement data.
     * @return bool
     */
    public function update_agreement( $agreement_id, array $data ) {
        if ( ! $this->is_valid_id( $agreement_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $agreement_id, $data ) {
                return $this->repository->update_agreement( (int) $agreement_id, $data ) !== false;
            },
            false
        );
    }

    /**
     * Delete agreement.
     *
     * @param int $agreement_id Agreement ID.
     * @return bool
     */
    public function delete_agreement( $agreement_id ) {
        if ( ! $this->is_valid_id( $agreement_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $agreement_id ) {
                return $this->repository->delete_agreement( (int) $agreement_id ) !== false;
            },
            false
        );
    }

    /**
     * Get aggregate statistics for an event.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_event_stats( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return array(
                'expected_total' => 0,
                'approved_count' => 0,
                'expected_count_rows' => 0,
            );
        }

        return $this->run_guarded(
            function () use ( $event_id ) {
                return $this->repository->get_event_stats( (int) $event_id );
            },
            array(
                'expected_total' => 0,
                'approved_count' => 0,
                'expected_count_rows' => 0,
            )
        );
    }

    /**
     * Delete an event and related records.
     *
     * @param int $event_id Event ID.
     * @return bool
     */
    public function delete_event_with_related( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $event_id ) {
                $event_id = (int) $event_id;
                $participant_ids = $this->repository->get_participant_ids_for_event( $event_id );
                $this->repository->delete_participant_tags_by_participant_ids( $participant_ids );
                $this->repository->delete_agreements_for_event( $event_id );
                $this->repository->delete_participants_for_event( $event_id );
                return $this->repository->delete_event( $event_id );
            },
            false
        );
    }

    /**
     * Create a new agreement for an event.
     *
     * @param array $data Agreement payload.
     * @return int|false
     */
    public function create_agreement( $data ) {
        $event_id = isset( $data['event_id'] ) ? (int) $data['event_id'] : 0;
        $participant1_id = isset( $data['participant1_id'] ) ? (int) $data['participant1_id'] : 0;
        $participant2_id = isset( $data['participant2_id'] ) ? (int) $data['participant2_id'] : 0;
        $initiator_id = isset( $data['initiator_id'] ) ? (int) $data['initiator_id'] : 0;
        $description = sanitize_textarea_field( $data['description'] ?? '' );

        if ( ! $this->is_valid_id( $event_id ) || ! $this->is_valid_id( $participant1_id ) || ! $this->is_valid_id( $participant2_id ) || ! $this->is_valid_id( $initiator_id ) ) {
            return false;
        }
        if ( $participant1_id === $participant2_id || '' === trim( $description ) ) {
            return false;
        }

        if ( $initiator_id !== $participant1_id && $initiator_id !== $participant2_id ) {
            return false;
        }

        $event = $this->repository->get_by_id( $event_id );
        if ( ! $event ) {
            return false;
        }

        $participant1 = $this->participant_repository->get_by_id( $participant1_id );
        $participant2 = $this->participant_repository->get_by_id( $participant2_id );
        if ( ! $participant1 || ! $participant2 ) {
            return false;
        }

        if ( (int) $participant1->event_id !== $event_id || (int) $participant2->event_id !== $event_id ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $event_id, $participant1_id, $participant2_id, $initiator_id, $description ) {
                return $this->repository->create_agreement(
                    array(
                        'event_id' => $event_id,
                        'participant1_id' => $participant1_id,
                        'participant2_id' => $participant2_id,
                        'initiator_id' => $initiator_id,
                        'description' => $description,
                        'status' => 'active',
                    )
                );
            },
            false
        );
    }

    /**
     * Count agreements for an event.
     *
     * @param int $event_id Event ID.
     * @return int
     */
    public function count_agreements_for_event( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return 0;
        }

        return (int) $this->run_guarded(
            function () use ( $event_id ) {
                return $this->repository->count_agreements_for_event( (int) $event_id );
            },
            0
        );
    }

    /**
     * Get leaderboard data for an event.
     *
     * @param int $event_id Event ID.
     * @param int $limit Maximum number of rows.
     * @return array
     */
    public function get_event_leaderboard( $event_id, $limit = 10 ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return array();
        }

        $limit = max( 1, (int) $limit );

        return $this->run_guarded(
            function () use ( $event_id, $limit ) {
                return $this->repository->get_event_leaderboard( (int) $event_id, $limit );
            },
            array()
        );
    }

    /**
     * Get latest agreements for an event.
     *
     * @param int $event_id Event ID.
     * @param int $limit Maximum number of rows.
     * @return array
     */
    public function get_recent_agreements( $event_id, $limit = 10 ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return array();
        }

        $limit = max( 1, (int) $limit );

        return $this->run_guarded(
            function () use ( $event_id, $limit ) {
                return $this->repository->get_recent_agreements( (int) $event_id, $limit );
            },
            array()
        );
    }
}
