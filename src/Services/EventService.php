<?php
/**
 * Event service
 *
 * @package VEP
 * @subpackage Services
 */

namespace VolunteerExchangePlatform\Services;

use VolunteerExchangePlatform\Services\AbstractService;
use VolunteerExchangePlatform\Services\AgreementStatus;
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

        try {
            $date = new \DateTimeImmutable( $value, $timezone );
            return $date->format( 'Y-m-d H:i:s' );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Normalize a datetime value into MySQL datetime format.
     *
     * Shared helper for admin and service layers.
     *
     * @param string $value Datetime input value.
     * @return string|false
     */
    public function normalize_datetime_value( $value ) {
        return $this->normalize_event_datetime( $value );
    }

    /**
     * Convert a stored datetime value to datetime-local input format.
     *
     * @param string $value Stored datetime value.
     * @return string
     */
    public function to_datetime_local_value( $value ) {
        $normalized = $this->normalize_event_datetime( $value );
        if ( false === $normalized ) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $normalized, wp_timezone() );
        if ( ! ( $date instanceof \DateTimeImmutable ) ) {
            return '';
        }

        return $date->format( 'Y-m-d\\TH:i' );
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
                $this->repository->delete_competition_winners_for_event( $event_id );
                $this->repository->delete_reminders_by_participant_ids( $participant_ids );
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

        $status = isset( $data['status'] ) && in_array( $data['status'], AgreementStatus::all(), true )
            ? $data['status']
            : AgreementStatus::AGREED;

        return $this->run_guarded(
            function () use ( $event_id, $participant1_id, $participant2_id, $initiator_id, $description, $status ) {
                return $this->repository->create_agreement(
                    array(
                        'event_id'        => $event_id,
                        'participant1_id'  => $participant1_id,
                        'participant2_id'  => $participant2_id,
                        'initiator_id'     => $initiator_id,
                        'description'      => $description,
                        'status'           => $status,
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

    /**
     * Get statistics for post-event display.
     *
     * Returns per-minute agreement counts, total, average per minute,
     * and the highest agreement count by a single participant.
     *
     * @param int $event_id Event ID.
     * @return array|null null on invalid input or error.
     */
    public function get_display_statistics( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return null;
        }

        return $this->run_guarded(
            function () use ( $event_id ) {
                $event_id = (int) $event_id;
                $event    = $this->repository->get_by_id( $event_id );

                if ( ! $event ) {
                    return null;
                }

                $timezone            = wp_timezone();
                $start_dt            = new \DateTimeImmutable( $event->start_date, $timezone );
                $end_dt              = new \DateTimeImmutable( $event->end_date, $timezone );
                // Only the event end_date limits which agreements count (it is final;
                // agreements in the registration grace window after it don't count).
                // There is deliberately no lower bound on start_date: all agreements
                // of the event count, and ones registered before the start are put
                // in the chart's first minute.
                $duration_seconds    = max( 1, $end_dt->getTimestamp() - $start_dt->getTimestamp() );
                $duration_minutes    = max( 1, (int) ceil( $duration_seconds / 60 ) );

                $rows       = $this->repository->get_agreements_over_time(
                    $event_id,
                    $event->start_date
                );
                $per_minute = array();

                foreach ( $rows as $row ) {
                    $minute = (int) $row->minute_offset;
                    $minute = max( 0, min( $duration_minutes - 1, $minute ) );

                    if ( ! isset( $per_minute[ $minute ] ) ) {
                        $per_minute[ $minute ] = 0;
                    }

                    $per_minute[ $minute ] += (int) $row->cnt;
                }

                $total       = array_sum( $per_minute );
                $avg         = $duration_minutes > 0 ? round( $total / $duration_minutes, 2 ) : 0;
                $leaderboard = $this->repository->get_event_leaderboard( $event_id, 1 );
                $max_actor   = ! empty( $leaderboard ) ? (int) $leaderboard[0]->agreement_count : 0;

                $first_seconds = $this->repository->get_first_agreement_offset_seconds(
                    $event_id,
                    $event->start_date
                );

                return array(
                    'total'                   => $total,
                    'avg_per_minute'          => $avg,
                    'max_by_actor'            => $max_actor,
                    'first_agreement_seconds' => $first_seconds,
                    'per_minute'              => $per_minute,
                    'duration_minutes'        => $duration_minutes,
                );
            },
            null
        );
    }

    /**
     * Set the agreement_overview_sent flag on an event.
     *
     * @param int $event_id Event ID.
     * @param int $value    1 to mark sent, 0 to clear.
     * @return bool
     */
    public function set_agreement_overview_sent( $event_id, $value ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return false;
        }
        return $this->run_guarded(
            function () use ( $event_id, $value ) {
                return $this->repository->set_agreement_overview_sent( (int) $event_id, (int) $value );
            },
            false
        );
    }

    /**
     * Get all eligible participants with their agreements for the overview email.
     *
     * @param int $event_id Event ID.
     * @return array Participant objects each with ->agreements array.
     */
    public function get_participants_with_agreements_for_overview( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return array();
        }
        return $this->run_guarded(
            function () use ( $event_id ) {
                return $this->repository->get_participants_with_agreements_for_overview( (int) $event_id );
            },
            array()
        );
    }

    /**
     * Get agreements for a single participant on a given event.
     *
     * @param int $participant_id Participant ID.
     * @param int $event_id       Event ID.
     * @return array
     */
    public function get_agreements_for_participant_on_event( $participant_id, $event_id ) {
        if ( ! $this->is_valid_id( $participant_id ) || ! $this->is_valid_id( $event_id ) ) {
            return array();
        }
        return $this->run_guarded(
            function () use ( $participant_id, $event_id ) {
                return $this->repository->get_agreements_for_participant_on_event( (int) $participant_id, (int) $event_id );
            },
            array()
        );
    }

    /**
     * Atomically claim the agreement overview send for an event.
     *
     * @param int $event_id Event ID.
     * @return bool True when claimed by this call, false when already sent/claimed.
     */
    public function claim_agreement_overview_sent( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return false;
        }
        return $this->run_guarded(
            function () use ( $event_id ) {
                return $this->repository->claim_agreement_overview_sent( (int) $event_id );
            },
            false
        );
    }

    /**
     * Maximum rows shown per top list in the live statistics panel (ties included).
     */
    const LIVE_STATS_LIMIT = 5;

    /**
     * Hard cap on rows per list when many entries tie for the last place.
     */
    const LIVE_STATS_HARD_CAP = 10;

    /**
     * Length of the "hot right now" window in minutes.
     */
    const LIVE_STATS_HOT_MINUTES = 15;

    /**
     * Get live statistics for the admin presentation page.
     *
     * Business rules: end_date is final (only agreements with created_at <= end_date
     * count); start_date is only a lower bound for the "first agreement" stat;
     * agreements count regardless of status and for both participants.
     *
     * @param int $event_id Event ID.
     * @return array|null null on invalid input, unknown event or error.
     */
    public function get_live_event_stats( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return null;
        }

        return $this->run_guarded(
            function () use ( $event_id ) {
                $event_id = (int) $event_id;
                $event    = $this->repository->get_by_id( $event_id );

                if ( ! $event ) {
                    return null;
                }

                return $this->compute_live_event_stats(
                    $this->repository->get_counted_agreements_for_stats( $event_id ),
                    $this->repository->get_participant_names_for_stats( $event_id ),
                    (string) $event->start_date,
                    (string) $event->end_date,
                    current_time( 'mysql' )
                );
            },
            null
        );
    }

    /**
     * Compute the live statistics from already fetched rows (no database access).
     *
     * All datetimes are WP-local wall-clock 'Y-m-d H:i:s' strings. They are only
     * compared/subtracted as wall-clock values, so no timezone conversion is needed.
     *
     * @param array  $agreements Rows (id, participant1_id, participant2_id, initiator_id, created_at) with created_at <= end_date, ordered by created_at.
     * @param array  $participants Rows (id, organization_name, is_approved).
     * @param string $start_date Event start (local).
     * @param string $end_date   Event end (local).
     * @param string $now        Current local time.
     * @return array
     */
    public function compute_live_event_stats( array $agreements, array $participants, $start_date, $end_date, $now ) {
        $names    = array();
        $approved = array();
        foreach ( $participants as $participant ) {
            $pid           = (int) $participant->id;
            $names[ $pid ] = (string) $participant->organization_name;
            if ( ! empty( $participant->is_approved ) ) {
                $approved[ $pid ] = true;
            }
        }

        $to_ts = static function ( $value ) {
            return strtotime( $value . ' UTC' );
        };

        $window_end = $now < $end_date ? $now : $end_date;
        $window_ts  = $to_ts( $window_end );
        $hot_from   = gmdate( 'Y-m-d H:i:s', $window_ts - self::LIVE_STATS_HOT_MINUTES * 60 );
        $after_end  = $now >= $end_date;

        $total       = 0;
        $counts      = array();
        $initiatives = array();
        $hot         = array();
        $partners    = array();
        $times       = array();
        $per_minute  = array();
        $first_time  = null;
        $first_rows  = array();
        $last_time   = null;
        $last_rows   = array();

        foreach ( $agreements as $row ) {
            $p1      = (int) $row->participant1_id;
            $p2      = (int) $row->participant2_id;
            $created = (string) $row->created_at;
            ++$total;

            $is_hot = $created >= $hot_from && $created <= $window_end;
            foreach ( array_unique( array( $p1, $p2 ) ) as $pid ) {
                $counts[ $pid ] = ( isset( $counts[ $pid ] ) ? $counts[ $pid ] : 0 ) + 1;
                if ( $is_hot ) {
                    $hot[ $pid ] = ( isset( $hot[ $pid ] ) ? $hot[ $pid ] : 0 ) + 1;
                }
                $times[ $pid ][] = $to_ts( $created );
            }

            if ( $p1 !== $p2 ) {
                $partners[ $p1 ][ $p2 ] = true;
                $partners[ $p2 ][ $p1 ] = true;
            }

            $initiator = (int) $row->initiator_id;
            if ( $initiator > 0 && ( $initiator === $p1 || $initiator === $p2 ) ) {
                $initiatives[ $initiator ] = ( isset( $initiatives[ $initiator ] ) ? $initiatives[ $initiator ] : 0 ) + 1;
            }

            $minute                = substr( $created, 11, 5 );
            $per_minute[ $minute ] = ( isset( $per_minute[ $minute ] ) ? $per_minute[ $minute ] : 0 ) + 1;

            // Rows are ordered by created_at ASC: first qualifying row(s) and last row(s).
            if ( $created >= $start_date ) {
                if ( null === $first_time ) {
                    $first_time = $created;
                }
                if ( $created === $first_time ) {
                    $first_rows[] = $row;
                }
            }
            if ( $created !== $last_time ) {
                $last_time = $created;
                $last_rows = array();
            }
            $last_rows[] = $row;
        }

        // Approved participants with at least one counted agreement.
        $active = 0;
        foreach ( $approved as $pid => $unused ) {
            if ( isset( $counts[ $pid ] ) ) {
                ++$active;
            }
        }

        // Shortest gap between two consecutive agreements per participant.
        // Same-second agreements (gap 0) are ignored, like the competition logic.
        $gaps = array();
        foreach ( $times as $pid => $list ) {
            sort( $list );
            $min = null;
            for ( $i = 1, $n = count( $list ); $i < $n; $i++ ) {
                $gap = $list[ $i ] - $list[ $i - 1 ];
                if ( $gap > 0 && ( null === $min || $gap < $min ) ) {
                    $min = $gap;
                }
            }
            if ( null !== $min ) {
                $gaps[ $pid ] = $min;
            }
        }

        $distinct = array();
        foreach ( $partners as $pid => $set ) {
            $distinct[ $pid ] = count( $set );
        }

        $only_approved = static function ( array $values ) use ( $approved ) {
            return array_intersect_key( $values, $approved );
        };

        $name_of = static function ( $pid ) use ( $names ) {
            return isset( $names[ $pid ] ) ? $names[ $pid ] : '';
        };

        $describe = static function ( array $rows ) use ( $name_of ) {
            $items = array();
            foreach ( array_slice( $rows, 0, EventService::LIVE_STATS_LIMIT ) as $row ) {
                $items[] = array(
                    'org1' => $name_of( (int) $row->participant1_id ),
                    'org2' => $name_of( (int) $row->participant2_id ),
                    'time' => substr( (string) $row->created_at, 11, 8 ),
                );
            }
            return array(
                'items' => $items,
                'more'  => max( 0, count( $rows ) - count( $items ) ),
            );
        };

        // Busiest minute(s).
        $max_minute = $per_minute ? max( $per_minute ) : 0;
        $busiest    = array();
        foreach ( $per_minute as $minute => $cnt ) {
            if ( $cnt === $max_minute ) {
                $busiest[] = (string) $minute;
            }
        }
        sort( $busiest );

        return array(
            'generated_at'    => substr( $now, 11, 8 ),
            'summary'         => array(
                'total'                        => $total,
                'participants_with_agreements' => $active,
                'participants_total'           => count( $approved ),
            ),
            'first'           => $describe( $first_rows ),
            'last'            => $describe( $last_rows ),
            'most_agreements' => $this->rank_live_stats( $only_approved( $counts ), $names, true ),
            'shortest_gap'    => $this->rank_live_stats( $only_approved( $gaps ), $names, false ),
            'hot'             => array_merge(
                $this->rank_live_stats( $only_approved( $hot ), $names, true ),
                array(
                    'minutes'   => self::LIVE_STATS_HOT_MINUTES,
                    'after_end' => $after_end,
                    'from'      => substr( $hot_from, 11, 5 ),
                    'to'        => substr( $window_end, 11, 5 ),
                )
            ),
            'initiatives'     => $this->rank_live_stats( $only_approved( $initiatives ), $names, true ),
            'partners'        => $this->rank_live_stats( $only_approved( $distinct ), $names, true ),
            'busiest_minute'  => array(
                'count' => $max_minute,
                'items' => array_slice( $busiest, 0, self::LIVE_STATS_LIMIT ),
                'more'  => max( 0, count( $busiest ) - self::LIVE_STATS_LIMIT ),
            ),
        );
    }

    /**
     * Rank participants into a Top N list that keeps ties together.
     *
     * Takes the first LIVE_STATS_LIMIT entries, then keeps adding entries that tie
     * with the last place (up to LIVE_STATS_HARD_CAP rows). Remaining tied entries
     * are reported as "more". Every row whose value equals another row's value
     * (including entries cut off by the cap) is flagged as tied.
     *
     * @param array $values     participant_id => numeric value (zero values are skipped).
     * @param array $names      participant_id => name.
     * @param bool  $descending True when the highest value ranks first.
     * @return array {items: [{name, value, tie}], more: int}
     */
    private function rank_live_stats( array $values, array $names, $descending ) {
        $entries = array();
        foreach ( $values as $pid => $value ) {
            if ( $value > 0 ) {
                $entries[] = array(
                    'name'  => isset( $names[ $pid ] ) ? (string) $names[ $pid ] : '',
                    'value' => (int) $value,
                );
            }
        }

        usort(
            $entries,
            static function ( $a, $b ) use ( $descending ) {
                if ( $a['value'] !== $b['value'] ) {
                    return $descending ? $b['value'] - $a['value'] : $a['value'] - $b['value'];
                }
                return strcasecmp( $a['name'], $b['name'] );
            }
        );

        $total = count( $entries );
        $shown = min( self::LIVE_STATS_LIMIT, $total );
        if ( $shown > 0 ) {
            $last_value = $entries[ $shown - 1 ]['value'];
            while ( $shown < $total && $shown < self::LIVE_STATS_HARD_CAP && $entries[ $shown ]['value'] === $last_value ) {
                ++$shown;
            }
        }

        $items = array_slice( $entries, 0, $shown );
        $more  = 0;
        if ( $shown > 0 ) {
            $last_value = $items[ $shown - 1 ]['value'];
            for ( $i = $shown; $i < $total && $entries[ $i ]['value'] === $last_value; $i++ ) {
                ++$more;
            }
        }

        $frequency = array();
        foreach ( $items as $item ) {
            $frequency[ $item['value'] ] = ( isset( $frequency[ $item['value'] ] ) ? $frequency[ $item['value'] ] : 0 ) + 1;
        }
        foreach ( $items as $index => $item ) {
            $tie = $frequency[ $item['value'] ] > 1
                || ( $more > 0 && $item['value'] === $items[ $shown - 1 ]['value'] );
            $items[ $index ]['tie'] = $tie;
        }

        return array(
            'items' => $items,
            'more'  => $more,
        );
    }
}
