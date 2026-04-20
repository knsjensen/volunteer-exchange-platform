<?php
/**
 * Participant repository
 *
 * @package VEP
 * @subpackage Database
 */

namespace VolunteerExchangePlatform\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParticipantRepository extends AbstractRepository {
    protected $table = 'vep_participants';

    /**
     * Get approval context for participant.
     *
     * @param int $participant_id Participant ID.
     * @return object|null
     */
    public function get_approval_context( $participant_id ) {
        return $this->get_row(
            "SELECT event_id, is_approved FROM {$this->table()} WHERE id = %d",
            array( $participant_id )
        );
    }

    /**
     * Get next participant number for event.
     *
     * @param int $event_id Event ID.
     * @return int
     */
    public function get_next_participant_number( $event_id ) {
        $numbers = $this->get_results(
            "SELECT participant_number
             FROM {$this->table()}
             WHERE event_id = %d
               AND participant_number IS NOT NULL
               AND participant_number > 0
             ORDER BY participant_number ASC",
            array( $event_id )
        );

        $next_number = 1;

        foreach ( $numbers as $row ) {
            $participant_number = isset( $row->participant_number ) ? (int) $row->participant_number : 0;

            if ( $participant_number < $next_number ) {
                continue;
            }

            if ( $participant_number > $next_number ) {
                return $next_number;
            }

            ++$next_number;
        }

        return $next_number;
    }

    /**
     * Set participant approval status.
     *
     * @param int      $participant_id Participant ID.
     * @param int|bool $is_approved Approval flag.
     * @param int|null $participant_number Optional participant number.
     * @return int|false
     */
    public function set_approval( $participant_id, $is_approved, $participant_number = null ) {
        $data = array(
            'is_approved' => (int) $is_approved,
        );
        $format = array( '%d' );

        if ( null !== $participant_number ) {
            $data['participant_number'] = (int) $participant_number;
            $format[] = '%d';
        }

        return $this->update( $data, array( 'id' => $participant_id ), $format, array( '%d' ) );
    }

    /**
     * Get approved participants for event including type.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_approved_for_event_with_type( $event_id ) {
        $types_table = $this->wpdb->prefix . 'vep_participant_types';

        return $this->get_results(
            "SELECT p.*, pt.name as type_name, pt.color as type_color
             FROM {$this->table()} p
             LEFT JOIN {$types_table} pt ON p.participant_type_id = pt.id
             WHERE p.event_id = %d AND p.is_approved = 1
             ORDER BY p.organization_name",
            array( $event_id )
        );
    }

    /**
     * Get participant by ID.
     *
     * @param int $participant_id Participant ID.
     * @return object|null
     */
    public function get_by_id( $participant_id ) {
        return $this->get_row( "SELECT * FROM {$this->table()} WHERE id = %d", array( $participant_id ) );
    }

    /**
     * Get participant by random key.
     *
     * @param string $randon_key Random participant key.
     * @return object|null
     */
    public function get_by_randon_key( $randon_key ) {
        return $this->get_row( "SELECT * FROM {$this->table()} WHERE randon_key = %s", array( $randon_key ) );
    }

    /**
     * Get participant by ID with joined details.
     *
     * @param int $participant_id Participant ID.
     * @return object|null
     */
    public function get_by_id_with_details( $participant_id ) {
        $types_table = $this->wpdb->prefix . 'vep_participant_types';
        $events_table = $this->wpdb->prefix . 'vep_events';

        return $this->get_row(
            "SELECT p.*, pt.name as type_name, e.name as event_name, e.start_date as event_date
             FROM {$this->table()} p
             LEFT JOIN {$types_table} pt ON p.participant_type_id = pt.id
             LEFT JOIN {$events_table} e ON p.event_id = e.id
             WHERE p.id = %d",
            array( $participant_id )
        );
    }

    /**
     * Get approved participants who should receive a reminder on a target event date.
     *
     * A participant is considered incomplete (eligible for reminder) when one or more
     * of these are missing: logo_url, description, at least one we_offer tag.
     *
     * @param string $target_date Date in Y-m-d format.
     * @return array
     */
    public function get_reminder_candidates_by_event_date( $target_date ) {
        $events_table = $this->wpdb->prefix . 'vep_events';
        $participant_tags_table = $this->wpdb->prefix . 'vep_participant_tags';

        return $this->get_results(
            "SELECT
                p.id,
                p.organization_name,
                p.contact_person_name,
                p.contact_email,
                p.logo_url,
                p.description,
                p.randon_key,
                e.name as event_name,
                e.start_date as event_date,
                COUNT(pt.tag_id) as tag_count
             FROM {$this->table()} p
             INNER JOIN {$events_table} e ON p.event_id = e.id
             LEFT JOIN {$participant_tags_table} pt ON p.id = pt.participant_id
             WHERE p.is_approved = 1
               AND p.contact_email <> ''
               AND DATE(e.start_date) = %s
             GROUP BY p.id
             HAVING (
                (p.logo_url IS NULL OR p.logo_url = '')
                OR (p.description IS NULL OR p.description = '')
                OR COUNT(pt.tag_id) = 0
             )",
            array( $target_date )
        );
    }

    /**
     * Get approved participant by ID with joined details.
     *
     * @param int $participant_id Participant ID.
     * @return object|null
     */
    public function get_approved_by_id_with_details( $participant_id ) {
        $types_table = $this->wpdb->prefix . 'vep_participant_types';
        $events_table = $this->wpdb->prefix . 'vep_events';

        return $this->get_row(
            "SELECT p.*, pt.name as type_name, pt.color as type_color, e.name as event_name
             FROM {$this->table()} p
             LEFT JOIN {$types_table} pt ON p.participant_type_id = pt.id
             LEFT JOIN {$events_table} e ON p.event_id = e.id
             WHERE p.id = %d AND p.is_approved = 1",
            array( $participant_id )
        );
    }

    /**
     * Get approved participants for select controls.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_approved_for_event_select( $event_id ) {
        return $this->get_results(
            "SELECT id, participant_number, organization_name
             FROM {$this->table()}
             WHERE event_id = %d AND is_approved = 1
             ORDER BY participant_number",
            array( $event_id )
        );
    }

    /**
     * Get all participants for select controls.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_for_event_select( $event_id ) {
        return $this->get_results(
            "SELECT id, organization_name
             FROM {$this->table()}
             WHERE event_id = %d
             ORDER BY organization_name",
            array( $event_id )
        );
    }

    /**
     * Get tag IDs for participant.
     *
     * @param int $participant_id Participant ID.
     * @return array
     */
    public function get_tag_ids( $participant_id ) {
        $participant_tags_table = $this->wpdb->prefix . 'vep_participant_tags';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholder SQL is prepared before execution; interpolated table name is controlled.
        $prepared_sql = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
            "SELECT tag_id FROM {$participant_tags_table} WHERE participant_id = %d",
            $participant_id
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared SQL is executed here.
        return $this->wpdb->get_col( $prepared_sql );
    }

    /**
     * Get tag names for participant.
     *
     * @param int $participant_id Participant ID.
     * @return array
     */
    public function get_tags_for_participant( $participant_id ) {
        $tags_table = $this->wpdb->prefix . 'vep_we_offer_tags';
        $participant_tags_table = $this->wpdb->prefix . 'vep_participant_tags';

        return $this->get_results(
            "SELECT t.name
             FROM {$tags_table} t
             INNER JOIN {$participant_tags_table} pt ON t.id = pt.tag_id
             WHERE pt.participant_id = %d
             ORDER BY t.name",
            array( $participant_id )
        );
    }

    /**
     * Get agreements for participant.
     *
     * @param int $participant_id Participant ID.
     * @return array
     */
    public function get_agreements_for_participant( $participant_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';

        return $this->get_results(
            "SELECT a.*,
                    CASE
                        WHEN a.participant1_id = %d THEN p2.organization_name
                        ELSE p1.organization_name
                    END as other_participant_name,
                    pi.organization_name as initiator_name
             FROM {$agreements_table} a
             LEFT JOIN {$this->table()} p1 ON a.participant1_id = p1.id
             LEFT JOIN {$this->table()} p2 ON a.participant2_id = p2.id
             LEFT JOIN {$this->table()} pi ON a.initiator_id = pi.id
             WHERE a.participant1_id = %d OR a.participant2_id = %d
             ORDER BY a.created_at DESC",
            array( $participant_id, $participant_id, $participant_id )
        );
    }

    /**
     * Get paginated participants with filters.
     *
     * @param int    $per_page Items per page.
     * @param int    $offset Offset.
     * @param string $orderby Order by field.
     * @param string $order Sort order.
     * @param string $approval_status Approval filter.
     * @param int    $event_id Event ID filter.
     * @return array
     */
    public function get_paginated_with_filters( $per_page, $offset, $orderby, $order, $approval_status = 'all', $event_id = 0 ) {
        $types_table = $this->wpdb->prefix . 'vep_participant_types';
        $events_table = $this->wpdb->prefix . 'vep_events';

        $allowed_orderby = array(
            'participant_number' => 'p.participant_number',
            'organization_name' => 'p.organization_name',
            'contact_person_name' => 'p.contact_person_name',
            'participant_type'  => 'pt.name',
            'is_approved'      => 'p.is_approved',
            'created_at'        => 'p.created_at',
            'p.created_at'      => 'p.created_at',
        );
        $orderby_sql = isset( $allowed_orderby[ $orderby ] ) ? $allowed_orderby[ $orderby ] : 'p.created_at';
        $order_sql = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        $where = array();
        $params = array();

        if ( 'pending' === $approval_status ) {
            $where[] = 'p.is_approved = 0';
        } elseif ( 'approved' === $approval_status ) {
            $where[] = 'p.is_approved = 1';
        }

        if ( $event_id > 0 ) {
            $where[] = 'p.event_id = %d';
            $params[] = $event_id;
        }

        $where_clause = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        return $this->get_results(
            "SELECT p.*,
                    pt.name as participant_type,
                    e.name as event_name
             FROM {$this->table()} p
             LEFT JOIN {$types_table} pt ON p.participant_type_id = pt.id
             LEFT JOIN {$events_table} e ON p.event_id = e.id
             {$where_clause}
             ORDER BY {$orderby_sql} {$order_sql}
             LIMIT %d OFFSET %d",
            array_merge( $params, array( $per_page, $offset ) )
        );
    }

    /**
     * Count participants with filters.
     *
     * @param string $approval_status Approval filter.
     * @param int    $event_id Event ID filter.
     * @return int
     */
    public function count_with_filters( $approval_status = 'all', $event_id = 0 ) {
        $where = array();
        $params = array();

        if ( 'pending' === $approval_status ) {
            $where[] = 'is_approved = 0';
        } elseif ( 'approved' === $approval_status ) {
            $where[] = 'is_approved = 1';
        }

        if ( $event_id > 0 ) {
            $where[] = 'event_id = %d';
            $params[] = $event_id;
        }

        $where_clause = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        return (int) $this->get_var(
            "SELECT COUNT(*) FROM {$this->table()} {$where_clause}",
            $params
        );
    }

    /**
     * Create participant.
     *
     * @param array $data Participant data.
     * @return int|false
     */
    public function create( $data ) {
        $result = $this->insert( $data );
        if ( false === $result ) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update participant.
     *
     * @param int   $participant_id Participant ID.
     * @param array $data Participant data.
     * @return int|false
     */
    public function update_participant( $participant_id, $data ) {
        return $this->update( $data, array( 'id' => $participant_id ), array(), array( '%d' ) );
    }

    /**
     * Delete participant and related tag links.
     *
     * @param int $participant_id Participant ID.
     * @return int|false
     */
    public function delete_participant( $participant_id ) {
        $participant_tags_table = $this->wpdb->prefix . 'vep_participant_tags';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Uses wpdb::delete with controlled table name and where format.
        $this->wpdb->delete( $participant_tags_table, array( 'participant_id' => $participant_id ), array( '%d' ) );

        return $this->delete( array( 'id' => $participant_id ), array( '%d' ) );
    }

    /**
     * Replace participant tags.
     *
     * @param int   $participant_id Participant ID.
     * @param array $tag_ids Tag IDs.
     * @return void
     */
    public function replace_tags( $participant_id, $tag_ids ) {
        $participant_tags_table = $this->wpdb->prefix . 'vep_participant_tags';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Uses wpdb::delete with controlled table name and where format.
        $this->wpdb->delete( $participant_tags_table, array( 'participant_id' => $participant_id ), array( '%d' ) );

        foreach ( $tag_ids as $tag_id ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Uses wpdb::insert with controlled table name.
            $this->wpdb->insert(
                $participant_tags_table,
                array(
                    'participant_id' => (int) $participant_id,
                    'tag_id'         => (int) $tag_id,
                ),
                array( '%d', '%d' )
            );
        }
    }

}
