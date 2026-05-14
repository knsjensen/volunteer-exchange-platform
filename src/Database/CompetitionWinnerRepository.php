<?php
/**
 * Competition Winner Repository
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
 * Competition Winner Repository class
 *
 * Handles database operations for the per-event competition winners table.
 * Competitions are global; this table stores which participant won each
 * competition for a specific event.
 *
 * @package VolunteerExchangePlatform\Database
 */
class CompetitionWinnerRepository extends AbstractRepository {

    /**
     * Table name without prefix.
     *
     * @var string
     */
    protected $table = 'vep_competition_winners';

    /**
     * Get the winner record for a specific event + competition pair.
     *
     * @param int $event_id       Event ID.
     * @param int $competition_id Competition ID.
     * @return object|null
     */
    public function get_by_event_and_competition( $event_id, $competition_id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for repository pattern
        return $this->get_row(
            'SELECT * FROM ' . $this->table() . ' WHERE event_id = %d AND competition_id = %d LIMIT 1',
            array( (int) $event_id, (int) $competition_id )
        );
    }

    /**
     * Get all winner records for an event, keyed by competition_id.
     *
     * @param int $event_id Event ID.
     * @return array Map of competition_id (int) => winner object.
     */
    public function get_all_for_event_keyed( $event_id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for repository pattern
        $rows = $this->get_results(
            'SELECT * FROM ' . $this->table() . ' WHERE event_id = %d',
            array( (int) $event_id )
        );

        $keyed = array();
        foreach ( $rows as $row ) {
            $keyed[ (int) $row->competition_id ] = $row;
        }

        return $keyed;
    }

    /**
     * Insert or update a winner record for an event + competition pair.
     *
     * Uses UPDATE if a record already exists, INSERT otherwise.
     * Pass null for both winner_id and winner_text to store a "blank" winner row
     * (prefer reset_for_event_and_competition() to delete instead).
     *
     * @param int         $event_id       Event ID.
     * @param int         $competition_id Competition ID.
     * @param int|null    $winner_id      Participant ID or null.
     * @param string|null $winner_text    Free-text winner value or null.
     * @return bool True on success.
     */
    public function upsert( $event_id, $competition_id, $winner_id = null, $winner_text = null ) {
        $event_id       = (int) $event_id;
        $competition_id = (int) $competition_id;
        $winner_id_val  = $winner_id ? (int) $winner_id : null;
        $winner_text_val = ( null !== $winner_text && '' !== $winner_text )
            ? sanitize_text_field( (string) $winner_text )
            : null;

        $existing = $this->get_by_event_and_competition( $event_id, $competition_id );

        if ( $existing ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct update required for repository pattern
            $result = $this->update(
                array(
                    'winner_id'   => $winner_id_val,
                    'winner_text' => $winner_text_val,
                    'updated_at'  => current_time( 'mysql' ),
                ),
                array(
                    'event_id'       => $event_id,
                    'competition_id' => $competition_id,
                ),
                array( '%d', '%s', '%s' ),
                array( '%d', '%d' )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert required for repository pattern
            $result = $this->insert(
                array(
                    'event_id'       => $event_id,
                    'competition_id' => $competition_id,
                    'winner_id'      => $winner_id_val,
                    'winner_text'    => $winner_text_val,
                ),
                array( '%d', '%d', '%d', '%s' )
            );
        }

        return false !== $result;
    }

    /**
     * Delete the winner record for a specific event + competition pair.
     *
     * @param int $event_id       Event ID.
     * @param int $competition_id Competition ID.
     * @return bool
     */
    public function reset_for_event_and_competition( $event_id, $competition_id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct delete required for repository pattern
        $result = $this->delete(
            array(
                'event_id'       => (int) $event_id,
                'competition_id' => (int) $competition_id,
            ),
            array( '%d', '%d' )
        );

        return false !== $result;
    }

    /**
     * Delete all winner records for an event.
     *
     * @param int $event_id Event ID.
     * @return bool
     */
    public function reset_all_for_event( $event_id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct delete required for repository pattern
        $result = $this->delete(
            array( 'event_id' => (int) $event_id ),
            array( '%d' )
        );

        return false !== $result;
    }
}
