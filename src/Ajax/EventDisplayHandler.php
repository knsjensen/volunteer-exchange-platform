<?php
/**
 * Event Display AJAX handler
 *
 * @package VEP
 * @subpackage Ajax
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Services\CompetitionService;
use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event Display AJAX handler
 * 
 * Handles AJAX requests for retrieving agreement count and leaderboard data
 * 
 * @package VolunteerExchangePlatform\Ajax
 */
class EventDisplayHandler {
    
    /**
     * @var EventService
     */
    private $event_service;

    /**
     * @var CompetitionService
     */
    private $competition_service;

    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * Constructor.
     *
     * @param EventService|null       $event_service Event service instance.
     * @param CompetitionService|null $competition_service Competition service instance.
     * @param ParticipantService|null $participant_service Participant service instance.
     * @return void
     */
    public function __construct( ?EventService $event_service = null, ?CompetitionService $competition_service = null, ?ParticipantService $participant_service = null ) {
        $this->event_service = $event_service ?: new EventService();
        $this->competition_service = $competition_service ?: new CompetitionService();
        $this->participant_service = $participant_service ?: new ParticipantService();
        add_action('wp_ajax_vep_get_agreement_count', array($this, 'get_agreement_count'));
        add_action('wp_ajax_vep_get_event_statistics', array($this, 'get_event_statistics'));
        add_action('wp_ajax_vep_get_event_competitions', array($this, 'get_event_competitions'));
        add_action('wp_ajax_vep_activate_competition_winners', array($this, 'activate_competition_winners'));
        add_action('wp_ajax_vep_display_get_state', array($this, 'display_get_state'));
        add_action('wp_ajax_vep_display_send_command', array($this, 'display_send_command'));
        add_action('wp_ajax_vep_display_poll_command', array($this, 'display_poll_command'));
        add_action('wp_ajax_vep_display_clear_state', array($this, 'display_clear_state'));
        add_action('wp_ajax_vep_display_auto_transition', array($this, 'display_auto_transition'));
        add_action('wp_ajax_vep_display_screen_leave', array($this, 'display_screen_leave'));
    }
    
    /**
     * Get agreement count and leaderboard via AJAX
     *
     * Returns total agreement count and top participants list for display
     *
     * @return void
     */
    public function get_agreement_count() {
        check_ajax_referer('vep_admin_nonce', 'nonce');

        // Get event ID from request
        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        
        if (!$event_id) {
            wp_send_json_error(array('message' => 'No event ID provided'));
            return;
        }

        $display_mode = isset( $_POST['display_mode'] ) ? sanitize_key( wp_unslash( $_POST['display_mode'] ) ) : 'leaderboard';
        if ( ! in_array( $display_mode, array( 'leaderboard', 'recent_agreements', 'none' ), true ) ) {
            $display_mode = 'leaderboard';
        }

        $timing = $this->get_event_timing( $event_id );
        $this->maybe_auto_select_winners( $event_id, $timing );

        // Counts, leaderboard and recent agreements only include agreements
        // registered at or before the event end_date (enforced in the repository).
        $count = $this->event_service->count_agreements_for_event($event_id);
        $leaderboard = $this->event_service->get_event_leaderboard($event_id, 10);
        $recent_agreements = $this->event_service->get_recent_agreements($event_id, 4);
        
        wp_send_json_success(array_merge(
            array(
                'count' => intval($count),
                'display_mode' => $display_mode,
                'leaderboard' => $leaderboard,
                'recent_agreements' => $recent_agreements,
            ),
            $this->timing_payload( $timing )
        ));
    }

    /**
     * Get post-event statistics for the fullscreen display via AJAX.
     *
     * @return void
     */
    public function get_event_statistics() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        $stats = $this->event_service->get_display_statistics( $event_id );

        if ( null === $stats ) {
            wp_send_json_error( array( 'message' => 'Could not load event statistics' ) );
            return;
        }

        wp_send_json_success( $stats );
    }

    /**
     * Get competitions for the fullscreen display via AJAX.
     *
     * @return void
     */
    public function get_event_competitions() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        $this->maybe_auto_select_winners( $event_id, $this->get_event_timing( $event_id ) );

        $competitions = $this->get_display_competitions( $event_id );

        wp_send_json_success( array(
            'competitions' => $competitions
        ) );
    }

    /**
     * Activate competition winners when event time expires.
     *
     * Automatically selects winners for all competitions in the event.
     *
     * @return void
     */
    public function activate_competition_winners() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;

        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
            return;
        }

        $this->competition_service->auto_select_winners_for_event( $event_id );

        wp_send_json_success( array(
            'message' => 'Competition winners activated successfully'
        ) );
    }

    /**
     * Get event competitions enriched with display-ready winner names.
     *
     * @param int $event_id Event ID.
     * @return array<int, object>
     */
    private function get_display_competitions( $event_id ) {
        $competitions = $this->competition_service->get_competitions_for_event( $event_id );

        foreach ( $competitions as $competition ) {
            $competition->winner_name = '';
            if ( isset( $competition->winner_input_type ) && 'text' === $competition->winner_input_type && ! empty( $competition->winner_text ) ) {
                $competition->winner_name = (string) $competition->winner_text;
            } elseif ( ! empty( $competition->winner_id ) ) {
                $participant = $this->participant_service->get_by_id( (int) $competition->winner_id );
                if ( $participant && ! empty( $participant->organization_name ) ) {
                    $competition->winner_name = (string) $participant->organization_name;
                }
            }
        }

        return $competitions;
    }

    /**
     * Get winner-backed competition summaries for remote display navigation.
     *
     * @param int $event_id Event ID.
     * @return array<int, array<string, int|string>>
     */
    private function get_display_competition_summaries( $event_id ) {
        $competitions = $this->get_display_competitions( $event_id );
        $summaries    = array();

        foreach ( $competitions as $competition ) {
            if ( empty( $competition->winner_name ) ) {
                continue;
            }

            $summaries[] = array(
                'title'       => isset( $competition->title ) ? sanitize_text_field( (string) $competition->title ) : '',
                'winner_name' => sanitize_text_field( (string) $competition->winner_name ),
                'index'       => count( $summaries ),
            );
        }

        return $summaries;
    }

    /* ------------------------------------------------------------------
     * Presentation state (one authoritative state per event)
     * ------------------------------------------------------------------ */

    /**
     * Maximum age of a stored presentation state before it is ignored.
     */
    const STATE_MAX_AGE = 43200; // 12 hours.

    /**
     * Seconds without a heartbeat after which a screen is no longer "active".
     */
    const SCREEN_ACTIVE_WINDOW = 30;

    /**
     * Minimum seconds between presence writes per screen.
     */
    const PRESENCE_WRITE_INTERVAL = 8;

    /**
     * Option name holding the authoritative presentation state.
     *
     * @param int $event_id Event ID.
     * @return string
     */
    private function state_option_name( $event_id ) {
        return 'vep_display_pstate_' . (int) $event_id;
    }

    /**
     * Option name holding per-screen presence (heartbeats).
     *
     * @param int $event_id Event ID.
     * @return string
     */
    private function presence_option_name( $event_id ) {
        return 'vep_display_presence_' . (int) $event_id;
    }

    /**
     * Read the current event timing using SERVER time.
     *
     * The event end_date is stored as WP-local wall time, so it is parsed with
     * wp_timezone(). The returned phase is decided here, never in the browser.
     *
     * @param int $event_id Event ID.
     * @return array|null
     */
    private function get_event_timing( $event_id ) {
        $event = $this->event_service->get_by_id( (int) $event_id );
        if ( ! $event || empty( $event->end_date ) ) {
            return null;
        }

        try {
            $timezone = wp_timezone();
            $end_ts   = ( new \DateTimeImmutable( (string) $event->end_date, $timezone ) )->getTimestamp();
            $start_ts = ! empty( $event->start_date )
                ? ( new \DateTimeImmutable( (string) $event->start_date, $timezone ) )->getTimestamp()
                : 0;
        } catch ( \Exception $e ) {
            return null;
        }

        $now = time();
        if ( $now >= $end_ts ) {
            $phase = 'ended';
        } elseif ( $start_ts > 0 && $now < $start_ts ) {
            $phase = 'before';
        } else {
            $phase = 'running';
        }

        return array(
            'end_ts'   => $end_ts,
            'start_ts' => $start_ts,
            'phase'    => $phase,
        );
    }

    /**
     * Build the timing fields included in poll responses.
     *
     * Computed as late as possible so server_now_ms is close to the moment
     * the response leaves the server (the client corrects for RTT/2).
     *
     * @param array|null $timing Result of get_event_timing().
     * @return array
     */
    private function timing_payload( $timing ) {
        return array(
            'server_now_ms' => (int) round( microtime( true ) * 1000 ),
            'end_ts_ms'     => $timing ? (int) $timing['end_ts'] * 1000 : 0,
            'phase'         => $timing ? $timing['phase'] : 'running',
        );
    }

    /**
     * Select competition winners once their timing rules are met.
     *
     * Throttled with a short transient; the throttle key includes the phase and
     * end timestamp so the transition to "ended" always triggers a fresh run.
     *
     * @param int        $event_id Event ID.
     * @param array|null $timing   Result of get_event_timing().
     * @param bool       $force    Skip the throttle.
     * @return void
     */
    private function maybe_auto_select_winners( $event_id, $timing, $force = false ) {
        if ( ! $timing ) {
            return;
        }

        $guard = 'vep_display_ws_' . (int) $event_id . '_' . $timing['phase'] . '_' . $timing['end_ts'];
        if ( ! $force && false !== get_transient( $guard ) ) {
            return;
        }
        set_transient( $guard, 1, 'ended' === $timing['phase'] ? 60 : 10 );

        $selected = $this->competition_service->auto_select_winners_for_event( (int) $event_id );
        if ( ! empty( $selected ) ) {
            delete_transient( 'vep_display_comp_summary_' . (int) $event_id );
        }
    }

    /**
     * Atomic read-modify-write of a JSON option (compare-and-set on the raw value).
     *
     * The mutator receives the decoded current value (or null) and returns the
     * new array, or false for "no change". Retries when another request wins.
     *
     * @param string   $option  Option name.
     * @param callable $mutator Mutator.
     * @return array|null The stored (or unchanged current) value.
     */
    private function cas_update_json( $option, $mutator ) {
        global $wpdb;

        $current = null;

        for ( $attempt = 0; $attempt < 8; $attempt++ ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Direct read avoids stale object cache; atomic CAS needs it.
            $raw     = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
            $current = null;
            if ( is_string( $raw ) && '' !== $raw ) {
                $decoded = json_decode( $raw, true );
                if ( is_array( $decoded ) ) {
                    $current = $decoded;
                }
            }

            $new = call_user_func( $mutator, $current );
            if ( false === $new ) {
                return $current;
            }

            $new_raw = wp_json_encode( $new );

            if ( null === $raw ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $ok = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $option, $new_raw ) );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $ok = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = %s", $new_raw, $option, $raw ) );
            }

            if ( $ok ) {
                wp_cache_delete( $option, 'options' );
                return $new;
            }
        }

        return $current;
    }

    /**
     * Whether a decoded state array is usable for the current event end time.
     *
     * A state created for a different end_date (event edited) or older than
     * STATE_MAX_AGE is treated as non-existent.
     *
     * @param mixed $state   Decoded state.
     * @param int   $end_ts  Current event end timestamp (0 = unknown, skip check).
     * @return bool
     */
    public function is_valid_state( $state, $end_ts ) {
        if ( ! is_array( $state ) || ! isset( $state['gen'], $state['seq'], $state['view'] ) ) {
            return false;
        }
        if ( $end_ts > 0 && (int) ( isset( $state['end_ts'] ) ? $state['end_ts'] : 0 ) !== (int) $end_ts ) {
            return false;
        }
        if ( (int) ( isset( $state['updated_at'] ) ? $state['updated_at'] : 0 ) < time() - self::STATE_MAX_AGE ) {
            return false;
        }
        return true;
    }

    /**
     * Read the authoritative presentation state (read-only).
     *
     * @param int $event_id Event ID.
     * @param int $end_ts   Current event end timestamp.
     * @return array|null
     */
    private function read_state( $event_id, $end_ts ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->state_option_name( $event_id ) ) );
        if ( ! is_string( $raw ) || '' === $raw ) {
            return null;
        }

        $state = json_decode( $raw, true );
        return $this->is_valid_state( $state, $end_ts ) ? $state : null;
    }

    /**
     * Public (client-facing) shape of a state.
     *
     * @param array|null $state State.
     * @return array|null
     */
    private function public_state( $state ) {
        if ( ! is_array( $state ) ) {
            return null;
        }

        return array(
            'view'         => (string) $state['view'],
            'winner_index' => isset( $state['winner_index'] ) ? (int) $state['winner_index'] : 0,
            'auto'         => ! empty( $state['auto'] ),
            'seq'          => (int) $state['seq'],
            'gen'          => (string) $state['gen'],
            'updated_at'   => isset( $state['updated_at'] ) ? (int) $state['updated_at'] : 0,
        );
    }

    /**
     * Record a heartbeat for one screen (throttled).
     *
     * @param int    $event_id  Event ID.
     * @param string $screen_id Screen ID.
     * @return void
     */
    private function touch_presence( $event_id, $screen_id ) {
        if ( '' === $screen_id ) {
            return;
        }

        $now = time();
        $this->cas_update_json(
            $this->presence_option_name( $event_id ),
            static function ( $current ) use ( $screen_id, $now ) {
                $screens = is_array( $current ) ? $current : array();
                if ( isset( $screens[ $screen_id ] ) && (int) $screens[ $screen_id ] >= $now - EventDisplayHandler::PRESENCE_WRITE_INTERVAL ) {
                    return false;
                }
                foreach ( $screens as $id => $ts ) {
                    if ( (int) $ts < $now - 120 ) {
                        unset( $screens[ $id ] );
                    }
                }
                $screens[ $screen_id ] = $now;
                return $screens;
            }
        );
    }

    /**
     * Number of screens with a recent heartbeat.
     *
     * @param int $event_id Event ID.
     * @return int
     */
    private function count_active_screens( $event_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $raw     = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->presence_option_name( $event_id ) ) );
        $screens = is_string( $raw ) ? json_decode( $raw, true ) : null;
        if ( ! is_array( $screens ) ) {
            return 0;
        }

        $threshold = time() - self::SCREEN_ACTIVE_WINDOW;
        $active    = 0;
        foreach ( $screens as $ts ) {
            if ( (int) $ts >= $threshold ) {
                $active++;
            }
        }
        return $active;
    }

    /**
     * Read and sanitize the screen ID from the request.
     *
     * @return string
     */
    private function get_screen_id_from_request() {
        $screen_id = isset( $_POST['screen_id'] ) ? sanitize_key( wp_unslash( $_POST['screen_id'] ) ) : '';
        return substr( $screen_id, 0, 40 );
    }

    /**
     * Read the event ID from the request or send a JSON error.
     *
     * @return int
     */
    private function require_event_id() {
        $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        if ( ! $event_id ) {
            wp_send_json_error( array( 'message' => 'No event ID provided' ) );
        }
        return $event_id;
    }

    /**
     * Admin nav box polls for the current presentation state (READ-ONLY).
     *
     * "active" means at least one screen sent a heartbeat in the last 30 s.
     *
     * @return void
     */
    public function display_get_state() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = $this->require_event_id();
        $timing   = $this->get_event_timing( $event_id );
        $screens  = $this->count_active_screens( $event_id );

        if ( $screens < 1 ) {
            wp_send_json_success( array_merge( array( 'active' => false, 'screens' => 0 ), $this->timing_payload( $timing ) ) );
            return;
        }

        $state        = $this->read_state( $event_id, $timing ? $timing['end_ts'] : 0 );
        $competitions = $this->get_cached_competition_summaries( $event_id );

        wp_send_json_success( array_merge(
            array(
                'active'        => true,
                'screens'       => $screens,
                'view'          => $state ? (string) $state['view'] : 'countdown',
                'winner_index'  => $state && isset( $state['winner_index'] ) ? (int) $state['winner_index'] : 0,
                'total_winners' => count( $competitions ),
                'competitions'  => $competitions,
                'seq'           => $state ? (int) $state['seq'] : 0,
            ),
            $this->timing_payload( $timing )
        ) );
    }

    /**
     * Winner-backed competition summaries, cached for ~5 seconds.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    private function get_cached_competition_summaries( $event_id ) {
        $key    = 'vep_display_comp_summary_' . (int) $event_id;
        $cached = get_transient( $key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $summaries = $this->get_display_competition_summaries( $event_id );
        set_transient( $key, $summaries, 5 );
        return $summaries;
    }

    /**
     * Explicit navigation (nav box or on-screen buttons): the ONLY writer of
     * the presentation state besides the one-time automatic end transition.
     * Increments seq atomically so two clicks in the same second are both kept.
     *
     * @return void
     */
    public function display_send_command() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = $this->require_event_id();

        $views  = array(
            'showCountdown'    => 'countdown',
            'showStatistics'   => 'statistics',
            'showCompetitions' => 'competitions',
            'showWinner'       => 'winner',
            'showClosing'      => 'closing',
        );
        $action = isset( $_POST['nav_action'] ) ? sanitize_text_field( wp_unslash( $_POST['nav_action'] ) ) : '';
        if ( ! isset( $views[ $action ] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid action' ) );
            return;
        }
        $view = $views[ $action ];

        $winner_index = isset( $_POST['winner_index'] ) ? absint( wp_unslash( $_POST['winner_index'] ) ) : 0;
        if ( 'winner' === $view ) {
            $total = count( $this->get_cached_competition_summaries( $event_id ) );
            if ( $total > 0 ) {
                $winner_index = min( $winner_index, $total - 1 );
            }
        } else {
            $winner_index = 0;
        }

        $timing = $this->get_event_timing( $event_id );
        $end_ts = $timing ? (int) $timing['end_ts'] : 0;
        $self   = $this;

        $new_state = $this->cas_update_json(
            $this->state_option_name( $event_id ),
            static function ( $current ) use ( $self, $view, $winner_index, $end_ts ) {
                $valid = $self->is_valid_state( $current, $end_ts );
                return array(
                    'gen'          => $valid ? (string) $current['gen'] : wp_generate_password( 8, false, false ),
                    'seq'          => $valid ? (int) $current['seq'] + 1 : 1,
                    'view'         => $view,
                    'winner_index' => $winner_index,
                    'auto'         => false,
                    'end_ts'       => $end_ts,
                    'updated_at'   => time(),
                );
            }
        );

        wp_send_json_success( array_merge(
            array( 'state' => $this->public_state( $new_state ) ),
            $this->timing_payload( $timing )
        ) );
    }

    /**
     * Screen poll (READ-ONLY with respect to the presentation state).
     *
     * Returns the authoritative state plus server clock/phase and records a
     * throttled per-screen heartbeat.
     *
     * @return void
     */
    public function display_poll_command() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = $this->require_event_id();
        $timing   = $this->get_event_timing( $event_id );

        $this->touch_presence( $event_id, $this->get_screen_id_from_request() );
        $this->maybe_auto_select_winners( $event_id, $timing );

        $state = $this->read_state( $event_id, $timing ? $timing['end_ts'] : 0 );

        wp_send_json_success( array_merge(
            array(
                'state'       => $this->public_state( $state ),
                'auto_switch' => (bool) get_option( 'vep_display_auto_switch_stats', 0 ),
            ),
            $this->timing_payload( $timing )
        ) );
    }

    /**
     * One-time automatic transition to statistics when the event has ended.
     *
     * Compare-and-set: only creates a state when none exists, so N open screens
     * produce exactly one transition. Returns the resulting authoritative state.
     *
     * @return void
     */
    public function display_auto_transition() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = $this->require_event_id();
        $timing   = $this->get_event_timing( $event_id );

        if ( ! $timing || 'ended' !== $timing['phase'] || ! get_option( 'vep_display_auto_switch_stats', 0 ) ) {
            $state = $timing ? $this->read_state( $event_id, $timing['end_ts'] ) : null;
            wp_send_json_success( array_merge(
                array( 'state' => $this->public_state( $state ) ),
                $this->timing_payload( $timing )
            ) );
            return;
        }

        // Winners must exist before the statistics/competition views are shown.
        $this->maybe_auto_select_winners( $event_id, $timing, true );

        $end_ts = (int) $timing['end_ts'];
        $self   = $this;
        $state  = $this->cas_update_json(
            $this->state_option_name( $event_id ),
            static function ( $current ) use ( $self, $end_ts ) {
                $valid = $self->is_valid_state( $current, $end_ts );
                // Allowed when there is no state, or when the only state is a
                // countdown set up BEFORE the end (transition not yet consumed).
                // A "back to countdown" chosen after the end must never bounce.
                if ( $valid && ! ( 'countdown' === $current['view'] && (int) $current['updated_at'] < $end_ts ) ) {
                    return false; // Somebody already navigated or transitioned.
                }
                return array(
                    'gen'          => $valid ? (string) $current['gen'] : wp_generate_password( 8, false, false ),
                    'seq'          => $valid ? (int) $current['seq'] + 1 : 1,
                    'view'         => 'statistics',
                    'winner_index' => 0,
                    'auto'         => true,
                    'end_ts'       => $end_ts,
                    'updated_at'   => time(),
                );
            }
        );

        if ( ! $this->is_valid_state( $state, $end_ts ) ) {
            $state = null;
        }

        wp_send_json_success( array_merge(
            array( 'state' => $this->public_state( $state ) ),
            $this->timing_payload( $timing )
        ) );
    }

    /**
     * A screen was closed: remove only ITS presence. The shared presentation
     * state is never touched here.
     *
     * @return void
     */
    public function display_screen_leave() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id  = $this->require_event_id();
        $screen_id = $this->get_screen_id_from_request();

        if ( '' !== $screen_id ) {
            $this->cas_update_json(
                $this->presence_option_name( $event_id ),
                static function ( $current ) use ( $screen_id ) {
                    if ( ! is_array( $current ) || ! isset( $current[ $screen_id ] ) ) {
                        return false;
                    }
                    unset( $current[ $screen_id ] );
                    return $current;
                }
            );
        }

        wp_send_json_success();
    }

    /**
     * Manual reset (admin "Nulstil navigationssystem"): the ONLY place that
     * clears the shared presentation state.
     *
     * @return void
     */
    /**
     * Flush cached competition summary for an event (call when a winner changes).
     *
     * @param int $event_id Event ID.
     * @return void
     */
    public static function flush_competition_cache( $event_id ) {
        delete_transient( 'vep_display_comp_summary_' . (int) $event_id );
    }

    public function display_clear_state() {
        check_ajax_referer( 'vep_admin_nonce', 'nonce' );

        $event_id = $this->require_event_id();

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->delete( $wpdb->options, array( 'option_name' => $this->state_option_name( $event_id ) ) );
        wp_cache_delete( $this->state_option_name( $event_id ), 'options' );

        // Legacy transients from earlier versions.
        delete_transient( 'vep_display_state_'   . $event_id );
        delete_transient( 'vep_display_command_' . $event_id );
        delete_transient( 'vep_display_comp_summary_' . $event_id );

        wp_send_json_success();
    }
}
