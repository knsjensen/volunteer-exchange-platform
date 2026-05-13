<?php
/**
 * Event repository
 *
 * @package VEP
 * @subpackage Database
 */

namespace VolunteerExchangePlatform\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EventRepository extends AbstractRepository {
    protected $table = 'vep_events';

    /**
     * Deactivate all events.
     *
     * @return int|false
     */
    public function deactivate_all() {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal static query against controlled table name.
        return $this->wpdb->query( "UPDATE {$this->table()} SET is_active = 0" );
    }

    /**
     * Get event by ID.
     *
     * @param int $event_id Event ID.
     * @return object|null
     */
    public function get_by_id( $event_id ) {
        return $this->get_row( "SELECT * FROM {$this->table()} WHERE id = %d", array( $event_id ) );
    }

    /**
     * Get active event.
     *
     * @return object|null
     */
    public function get_active_event() {
        return $this->get_row( "SELECT id, name, start_date, end_date FROM {$this->table()} WHERE is_active = 1 ORDER BY id DESC LIMIT 1" );
    }

    /**
     * Get paginated events.
     *
     * @param int    $per_page Items per page.
     * @param int    $offset Offset.
     * @param string $orderby Order by field.
     * @param string $order Sort order.
     * @return array
     */
    public function get_paginated( $per_page = 20, $offset = 0, $orderby = 'created_at', $order = 'DESC' ) {
        $allowed_orderby = array( 'name', 'start_date', 'created_at' );
        $orderby = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'created_at';
        $order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        return $this->get_results(
            "SELECT * FROM {$this->table()} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            array( $per_page, $offset )
        );
    }

    /**
     * Get all events for select controls.
     *
     * @return array
     */
    public function get_all_for_select() {
        return $this->get_results( "SELECT id, name FROM {$this->table()} ORDER BY created_at DESC" );
    }

    /**
     * Count all events.
     *
     * @return int
     */
    public function count_all() {
        return (int) $this->get_var( "SELECT COUNT(*) FROM {$this->table()}" );
    }

    /**
     * Create event.
     *
     * @param array $data Event data.
     * @return int|false
     */
    public function create( $data ) {
        return $this->insert( $data );
    }

    /**
     * Update event.
     *
     * @param int   $event_id Event ID.
     * @param array $data Event data.
     * @return int|false
     */
    public function update_event( $event_id, $data ) {
        return $this->update( $data, array( 'id' => $event_id ), array(), array( '%d' ) );
    }

    /**
     * Deactivate event.
     *
     * @param int $event_id Event ID.
     * @return int|false
     */
    public function deactivate( $event_id ) {
        return $this->update(
            array( 'is_active' => 0 ),
            array( 'id' => $event_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Get participant IDs belonging to an event.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_participant_ids_for_event( $event_id ) {
        $participants_table = $this->wpdb->prefix . 'vep_participants';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholder SQL is prepared before execution; interpolated table name is controlled.
        $prepared_sql = $this->wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated table name is controlled.
            "SELECT id FROM {$participants_table} WHERE event_id = %d",
            $event_id
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared SQL is executed here.
        return $this->wpdb->get_col( $prepared_sql );
    }

    /**
     * Delete participant tag links by participant IDs.
     *
     * @param array $participant_ids Participant IDs.
     * @return int|false
     */
    public function delete_participant_tags_by_participant_ids( $participant_ids ) {
        if ( empty( $participant_ids ) ) {
            return 0;
        }

        $participant_tags_table = $this->wpdb->prefix . 'vep_participant_tags';
        $placeholders = implode( ',', array_fill( 0, count( $participant_ids ), '%d' ) );
        $sql = "DELETE FROM {$participant_tags_table} WHERE participant_id IN ({$placeholders})";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholder SQL is prepared before execution; interpolated table name is controlled.
        $prepared_sql = $this->wpdb->prepare( $sql, $participant_ids );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared SQL is executed here.
        return $this->wpdb->query( $prepared_sql );
    }

    /**
     * Delete agreements for event.
     *
     * @param int $event_id Event ID.
     * @return int|false
     */
    public function delete_agreements_for_event( $event_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Uses wpdb::delete with controlled table name and where format.
        return $this->wpdb->delete( $agreements_table, array( 'event_id' => $event_id ), array( '%d' ) );
    }

    /**
     * Delete participants for event.
     *
     * @param int $event_id Event ID.
     * @return int|false
     */
    public function delete_participants_for_event( $event_id ) {
        $participants_table = $this->wpdb->prefix . 'vep_participants';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Uses wpdb::delete with controlled table name and where format.
        return $this->wpdb->delete( $participants_table, array( 'event_id' => $event_id ), array( '%d' ) );
    }

    /**
     * Delete event by ID.
     *
     * @param int $event_id Event ID.
     * @return int|false
     */
    public function delete_event( $event_id ) {
        return $this->delete( array( 'id' => $event_id ), array( '%d' ) );
    }

    /**
     * Get agreements for event with participant names.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_agreements_for_event( $event_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        $participants_table = $this->wpdb->prefix . 'vep_participants';

        return $this->get_results(
            "SELECT a.*,
                    p1.participant_number as participant1_number,
                    p1.organization_name as participant1_name,
                    p2.participant_number as participant2_number,
                    p2.organization_name as participant2_name,
                    pi.organization_name as initiator_name
             FROM {$agreements_table} a
             LEFT JOIN {$participants_table} p1 ON a.participant1_id = p1.id
             LEFT JOIN {$participants_table} p2 ON a.participant2_id = p2.id
             LEFT JOIN {$participants_table} pi ON a.initiator_id = pi.id
             WHERE a.event_id = %d
             ORDER BY a.created_at DESC",
            array( $event_id )
        );
    }

    /**
     * Get agreement by ID.
     *
     * @param int $agreement_id Agreement ID.
     * @return object|null
     */
    public function get_agreement_by_id( $agreement_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        $participants_table = $this->wpdb->prefix . 'vep_participants';

        return $this->get_row(
            "SELECT a.*,
                    p1.participant_number as participant1_number,
                    p1.organization_name as participant1_name,
                    p2.participant_number as participant2_number,
                    p2.organization_name as participant2_name,
                    pi.organization_name as initiator_name
             FROM {$agreements_table} a
             LEFT JOIN {$participants_table} p1 ON a.participant1_id = p1.id
             LEFT JOIN {$participants_table} p2 ON a.participant2_id = p2.id
             LEFT JOIN {$participants_table} pi ON a.initiator_id = pi.id
             WHERE a.id = %d",
            array( $agreement_id )
        );
    }

    /**
     * Update agreement.
     *
     * @param int   $agreement_id Agreement ID.
     * @param array $data Agreement data to update.
     * @return int|false
     */
    public function update_agreement( $agreement_id, array $data ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        
        // Only allow updating description, status, and initiator
        $allowed_fields = array('description', 'status', 'initiator_id');
        $update_data = array();
        $format = array();
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if ( 'initiator_id' === $field ) {
                    $update_data[$field] = (int) $data[$field];
                    $format[] = '%d';
                } else {
                    $update_data[$field] = $data[$field];
                    $format[] = '%s';
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }

        return $this->wpdb->update(
            $agreements_table,
            $update_data,
            array('id' => $agreement_id),
            $format,
            array('%d')
        );
    }

    /**
     * Delete agreement by ID.
     *
     * @param int $agreement_id Agreement ID.
     * @return int|false
     */
    public function delete_agreement( $agreement_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Uses wpdb::delete with controlled table name and where format.
        return $this->wpdb->delete( $agreements_table, array( 'id' => $agreement_id ), array( '%d' ) );
    }

    /**
     * Create agreement.
     *
     * @param array $data Agreement data.
     * @return int|false Agreement ID on success, false on failure.
     */
    public function create_agreement( array $data ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';

        $insert_data = array(
            'event_id' => isset($data['event_id']) ? absint($data['event_id']) : 0,
            'participant1_id' => isset($data['participant1_id']) ? absint($data['participant1_id']) : 0,
            'participant2_id' => isset($data['participant2_id']) ? absint($data['participant2_id']) : 0,
            'initiator_id' => isset($data['initiator_id']) ? absint($data['initiator_id']) : 0,
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active',
        );

        $result = $this->wpdb->insert(
            $agreements_table,
            $insert_data,
            array('%d', '%d', '%d', '%d', '%s', '%s')
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get aggregate event statistics.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_event_stats( $event_id ) {
        $participants_table = $this->wpdb->prefix . 'vep_participants';

        $expected_total = (int) $this->get_var(
            "SELECT COALESCE(SUM(COALESCE(expected_participants_count, 0)), 0)
             FROM {$participants_table}
             WHERE event_id = %d AND is_approved = 1",
            array( $event_id )
        );

        $approved_count = (int) $this->get_var(
            "SELECT COUNT(*)
             FROM {$participants_table}
             WHERE event_id = %d AND is_approved = 1",
            array( $event_id )
        );

        $expected_count_rows = (int) $this->get_var(
            "SELECT COUNT(*)
             FROM {$participants_table}
             WHERE event_id = %d
               AND is_approved = 1
               AND expected_participants_count IS NOT NULL",
            array( $event_id )
        );

        return array(
            'expected_total' => $expected_total,
            'approved_count' => $approved_count,
            'expected_count_rows' => $expected_count_rows,
        );
    }

    /**
     * Count agreements for event.
     *
     * @param int $event_id Event ID.
     * @return int
     */
    public function count_agreements_for_event( $event_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';

        return (int) $this->get_var(
            "SELECT COUNT(*) FROM {$agreements_table} WHERE event_id = %d",
            array( $event_id )
        );
    }

    /**
     * Get event leaderboard.
     *
     * @param int $event_id Event ID.
     * @param int $limit Row limit.
     * @return array
     */
    public function get_event_leaderboard( $event_id, $limit = 10 ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        $participants_table = $this->wpdb->prefix . 'vep_participants';

        return $this->get_results(
            "SELECT p.id, p.organization_name, p.logo_url, COUNT(DISTINCT a.id) as agreement_count
             FROM {$participants_table} p
             LEFT JOIN {$agreements_table} a ON (a.participant1_id = p.id OR a.participant2_id = p.id) AND a.event_id = %d
             WHERE p.event_id = %d AND p.is_approved = 1
             GROUP BY p.id, p.organization_name, p.logo_url
             HAVING agreement_count > 0
             ORDER BY agreement_count DESC, p.organization_name ASC
             LIMIT %d",
            array( $event_id, $event_id, (int) $limit )
        );
    }

    /**
     * Get latest agreements for event display.
     *
     * @param int $event_id Event ID.
     * @param int $limit Row limit.
     * @return array
     */
    public function get_recent_agreements( $event_id, $limit = 10 ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        $participants_table = $this->wpdb->prefix . 'vep_participants';

        return $this->get_results(
            "SELECT
                a.id,
                a.created_at,
                a.participant1_id,
                a.participant2_id,
                a.initiator_id,
                p1.organization_name as participant1_name,
                p2.organization_name as participant2_name
             FROM {$agreements_table} a
             LEFT JOIN {$participants_table} p1 ON a.participant1_id = p1.id
             LEFT JOIN {$participants_table} p2 ON a.participant2_id = p2.id
             WHERE a.event_id = %d
             ORDER BY a.created_at DESC
             LIMIT %d",
            array( $event_id, (int) $limit )
        );
    }

    /**
     * Get first agreement for an event (after event start).
     *
     * @param int $event_id Event ID.
     * @return object|null
     */
    public function get_first_agreement( $event_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        $participants_table = $this->wpdb->prefix . 'vep_participants';

        return $this->get_row(
            "SELECT a.*, p1.organization_name as participant1_name, p2.organization_name as participant2_name
             FROM {$agreements_table} a
             LEFT JOIN {$participants_table} p1 ON a.participant1_id = p1.id
             LEFT JOIN {$participants_table} p2 ON a.participant2_id = p2.id
             WHERE a.event_id = %d
             ORDER BY a.created_at ASC LIMIT 1",
            array( $event_id )
        );
    }

    /**
     * Get last agreement for an event (created before event end).
     *
     * @param int $event_id Event ID.
     * @return object|null
     */
    public function get_last_agreement( $event_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        $participants_table = $this->wpdb->prefix . 'vep_participants';

        return $this->get_row(
            "SELECT a.*, p1.organization_name as participant1_name, p2.organization_name as participant2_name
             FROM {$agreements_table} a
             LEFT JOIN {$participants_table} p1 ON a.participant1_id = p1.id
             LEFT JOIN {$participants_table} p2 ON a.participant2_id = p2.id
             WHERE a.event_id = %d
             ORDER BY a.created_at DESC LIMIT 1",
            array( $event_id )
        );
    }

    /**
     * Get agreement created within last minute before event end (deadline rush).
     *
     * @param int $event_id Event ID.
     * @return object|null
     */
    public function get_deadline_rush_agreement( $event_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        $participants_table = $this->wpdb->prefix . 'vep_participants';
        $events_table = $this->table();

        return $this->get_row(
            "SELECT a.*, p1.organization_name as participant1_name, p2.organization_name as participant2_name
             FROM {$agreements_table} a
             LEFT JOIN {$participants_table} p1 ON a.participant1_id = p1.id
             LEFT JOIN {$participants_table} p2 ON a.participant2_id = p2.id
             INNER JOIN {$events_table} e ON a.event_id = e.id
             WHERE a.event_id = %d
             AND a.created_at >= DATE_SUB(e.end_date, INTERVAL 1 MINUTE)
             AND a.created_at <= e.end_date
             ORDER BY a.created_at DESC LIMIT 1",
            array( $event_id )
        );
    }

    /**
     * Get participant with most agreements for an event.
     *
     * @param int $event_id Event ID.
     * @return object|null
     */
    public function get_participant_with_most_agreements( $event_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        $participants_table = $this->wpdb->prefix . 'vep_participants';

        return $this->get_row(
            "SELECT p.*, COUNT(DISTINCT a.id) as agreement_count
             FROM {$participants_table} p
             LEFT JOIN {$agreements_table} a ON (a.participant1_id = p.id OR a.participant2_id = p.id) AND a.event_id = %d
             WHERE p.event_id = %d AND p.is_approved = 1
             GROUP BY p.id
             ORDER BY agreement_count DESC LIMIT 1",
            array( $event_id, $event_id )
        );
    }

    /**
     * Get participant with shortest time between their agreements.
     *
     * @param int $event_id Event ID.
     * @return object|null
     */
    public function get_participant_shortest_time_between_agreements( $event_id ) {
        $agreements_table = $this->wpdb->prefix . 'vep_agreements';
        $participants_table = $this->wpdb->prefix . 'vep_participants';

        return $this->get_row(
            "SELECT p.*
             FROM {$participants_table} p
             WHERE p.event_id = %d AND p.is_approved = 1
             AND p.id IN (
                SELECT a1.participant1_id
                FROM {$agreements_table} a1
                WHERE a1.event_id = %d
                UNION
                SELECT a2.participant2_id
                FROM {$agreements_table} a2
                WHERE a2.event_id = %d
             )
             ORDER BY (
                SELECT MIN(TIMESTAMPDIFF(SECOND, 
                    (SELECT MAX(created_at) FROM {$agreements_table} WHERE event_id = %d AND (participant1_id = p.id OR participant2_id = p.id) AND created_at < a.created_at),
                    a.created_at
                ))
                FROM {$agreements_table} a
                WHERE a.event_id = %d AND (a.participant1_id = p.id OR a.participant2_id = p.id)
             ) ASC
             LIMIT 1",
            array( $event_id, $event_id, $event_id, $event_id, $event_id )
        );
    }
}
