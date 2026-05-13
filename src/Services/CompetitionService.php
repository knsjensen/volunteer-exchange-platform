<?php
/**
 * Competition Service
 *
 * @package VEP
 * @subpackage Services
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Services;

use VolunteerExchangePlatform\Database\CompetitionRepository;
use VolunteerExchangePlatform\Database\EventRepository;
use VolunteerExchangePlatform\Database\ParticipantRepository;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Competition Service class
 *
 * Handles business logic for competitions.
 *
 * @package VolunteerExchangePlatform\Services
 */
class CompetitionService extends AbstractService {
    
    /**
     * Competition repository
     *
     * @var CompetitionRepository
     */
    private $competition_repository;
    
    /**
     * Event repository
     *
     * @var EventRepository
     */
    private $event_repository;
    
    /**
     * Participant repository
     *
     * @var ParticipantRepository
     */
    private $participant_repository;

    /**
     * Constructor
     *
     * @param CompetitionRepository|null $competition_repository Competition repository
     * @param EventRepository|null       $event_repository Event repository
     * @param ParticipantRepository|null $participant_repository Participant repository
     */
    public function __construct(
        ?CompetitionRepository $competition_repository = null,
        ?EventRepository $event_repository = null,
        ?ParticipantRepository $participant_repository = null
    ) {
        $this->competition_repository = $competition_repository ?: new CompetitionRepository();
        $this->event_repository = $event_repository ?: new EventRepository();
        $this->participant_repository = $participant_repository ?: new ParticipantRepository();
    }

    /**
     * Get active competitions for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public function get_active_competitions( $event_id ) {
        return $this->competition_repository->get_active_for_event( $event_id );
    }

    /**
     * Get inactive competitions for an event
     *
     * @param int $event_id Event ID
     * @return array
     */
    public function get_inactive_competitions( $event_id ) {
        return $this->competition_repository->get_inactive_for_event( $event_id );
    }

    /**
     * Get all competitions for an event sorted by sort_order
     *
     * @param int $event_id Event ID
     * @return array
     */
    public function get_competitions_for_event( $event_id ) {
        return $this->competition_repository->get_all_for_event( $event_id );
    }

    /**
     * Create a new competition
     *
     * @param array $data Competition data
     * @return int|false Competition ID or false on failure
     */
    public function create_competition( array $data ) {
        if ( empty( $data['sort_order'] ) && ! empty( $data['event_id'] ) ) {
            $data['sort_order'] = count( $this->competition_repository->get_all_for_event( (int) $data['event_id'] ) ) + 1;
        }

        return $this->competition_repository->create( $data );
    }

    /**
     * Ensure all predefined competitions exist for an event.
     *
     * @param int $event_id Event ID.
     * @return int Number of competitions created.
     */
    public function ensure_default_competitions_for_event( $event_id ) {
        $event_id = (int) $event_id;

        if ( $event_id <= 0 ) {
            return 0;
        }

        $created = 0;
        $definitions = $this->get_default_competition_definitions();

        foreach ( $definitions as $index => $definition ) {
            // For custom type, check by type AND title since there can be multiple custom competitions
            if ( 'custom' === $definition['type'] ) {
                $existing = $this->competition_repository->get_by_event_and_type_and_title( $event_id, $definition['type'], $definition['title'] );
            } else {
                $existing = $this->competition_repository->get_by_event_and_type( $event_id, $definition['type'] );
            }

            if ( $existing ) {
                continue;
            }

            $result = $this->competition_repository->create(
                array(
                    'event_id'   => $event_id,
                    'type'       => $definition['type'],
                    'title'      => $definition['title'],
                    'description'=> isset( $definition['description'] ) ? $definition['description'] : '',
                    'is_active'  => ( 'custom' === $definition['type'] ) ? 1 : 0,
                    'winner_id'  => null,
                    'sort_order' => $index + 1,
                )
            );

            if ( $result ) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Update a competition
     *
     * @param int   $id Competition ID
     * @param array $data Competition data
     * @return bool
     */
    public function update_competition( $id, array $data ) {
        $update_data = array();
        $format = array();
        
        if ( isset( $data['title'] ) ) {
            $update_data['title'] = sanitize_text_field( $data['title'] );
            $format[] = '%s';
        }

        if ( isset( $data['description'] ) ) {
            $update_data['description'] = sanitize_textarea_field( $data['description'] );
            $format[] = '%s';
        }
        
        if ( isset( $data['is_active'] ) ) {
            $update_data['is_active'] = (int) $data['is_active'];
            $format[] = '%d';
        }
        
        if ( isset( $data['winner_id'] ) ) {
            $update_data['winner_id'] = $data['winner_id'] ? (int) $data['winner_id'] : null;
            $format[] = $data['winner_id'] ? '%d' : '%s';
        }
        
        if ( isset( $data['sort_order'] ) ) {
            $update_data['sort_order'] = (int) $data['sort_order'];
            $format[] = '%d';
        }
        
        if ( empty( $update_data ) ) {
            return false;
        }
        
        return $this->competition_repository->update( $update_data, array( 'id' => $id ), $format, array( '%d' ) );
    }

    /**
     * Delete a competition (only if it's custom type)
     *
     * @param int $id Competition ID
     * @return bool
     */
    public function delete_competition( $id ) {
        $competition = $this->competition_repository->get_by_id( $id );
        
        if ( ! $competition ) {
            return false;
        }
        
        // Only custom competitions can be deleted
        if ( 'custom' !== $competition->type ) {
            return false;
        }
        
        return $this->competition_repository->delete_by_id( $id );
    }

    /**
     * Activate a competition
     *
     * @param int $id Competition ID
     * @return bool
     */
    public function set_active( $id ) {
        return $this->competition_repository->update( array( 'is_active' => 1 ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
    }

    /**
     * Deactivate a competition
     *
     * @param int $id Competition ID
     * @return bool
     */
    public function set_inactive( $id ) {
        return $this->competition_repository->update( array( 'is_active' => 0 ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
    }

    /**
     * Reorder competitions
     *
     * @param array $order_map Array of competition_id => sort_order
     * @return bool
     */
    public function reorder_competitions( array $order_map ) {
        return $this->competition_repository->update_sort_order( $order_map );
    }

    /**
     * Set winner for a competition
     *
     * @param int      $competition_id Competition ID
     * @param int|null $winner_id Participant ID or null to clear
     * @return bool
     */
    public function set_winner( $competition_id, $winner_id = null ) {
        return $this->competition_repository->set_winner( $competition_id, $winner_id );
    }

    /**
     * Check if a competition can be deleted
     *
     * @param int $competition_id Competition ID
     * @return bool
     */
    public function can_delete_competition( $competition_id ) {
        $competition = $this->competition_repository->get_by_id( $competition_id );
        
        if ( ! $competition ) {
            return false;
        }
        
        return 'custom' === $competition->type;
    }

    /**
     * Auto-select winner if criteria are met.
     *
     * Checks if event timing conditions are met and no winner is set yet,
     * then automatically selects winner based on competition type.
     *
     * @param object $competition Competition object.
     * @param object $event Event object.
     * @return bool|int Participant ID if winner was set, false otherwise
     */
    public function auto_select_winner_if_needed( $competition, $event ) {
        if ( ! is_object( $competition ) ) {
            return false;
        }

        if ( ! is_object( $event ) ) {
            return false;
        }

        $competition_id = isset( $competition->id ) ? (int) $competition->id : 0;
        $event_id = isset( $competition->event_id ) ? (int) $competition->event_id : ( isset( $event->id ) ? (int) $event->id : 0 );

        if ( $event_id <= 0 ) {
            return false;
        }

        if ( ! (int) $competition->is_active || $competition->winner_id ) {
            // Doesn't exist, is inactive, or already has winner
            return false;
        }

        if ( ! $this->should_auto_select_now( $competition->type, $event ) ) {
            return false;
        }
        
        $winner_id = $this->get_competition_winner( $competition->type, $event_id );
        
        if ( ! $winner_id ) {
            return false;
        }
        
        $this->competition_repository->set_winner( $competition_id, $winner_id );
        
        return $winner_id;
    }

    /**
     * Auto-select winners for all eligible competitions on an event.
     *
     * @param int $event_id Event ID.
     * @return array Competition ID => winner ID for newly selected winners.
     */
    public function auto_select_winners_for_event( $event_id ) {
        $event_id = (int) $event_id;

        if ( $event_id <= 0 ) {
            return array();
        }

        $selected_winners = array();
        $competitions = $this->competition_repository->get_all_for_event( $event_id );
        $events_by_id = array();

        foreach ( $competitions as $competition ) {
            $competition_event_id = isset( $competition->event_id ) ? (int) $competition->event_id : $event_id;

            if ( ! isset( $events_by_id[ $competition_event_id ] ) ) {
                $events_by_id[ $competition_event_id ] = $this->event_repository->get_by_id( $competition_event_id );
            }

            if ( ! is_object( $events_by_id[ $competition_event_id ] ) ) {
                continue;
            }

            $winner_id = $this->auto_select_winner_if_needed( $competition, $events_by_id[ $competition_event_id ] );

            if ( $winner_id ) {
                $selected_winners[ (int) $competition->id ] = (int) $winner_id;
            }
        }

        return $selected_winners;
    }

    /**
     * Get competition winner based on type
     *
     * @param string $type Competition type
     * @param int    $event_id Event ID
     * @return int|false Participant ID or false if no winner found
     */
    public function get_competition_winner( $type, $event_id ) {
        switch ( $type ) {
            case 'first_registered':
                $participant = $this->participant_repository->get_first_registered( $event_id );
                return $participant ? $participant->id : false;
                
            case 'last_minute':
                $participant = $this->participant_repository->get_last_minute_registration( $event_id );
                return $participant ? $participant->id : false;
                
            case 'longest_description':
                $participant = $this->participant_repository->get_longest_description( $event_id );
                return $participant ? $participant->id : false;
                
            case 'first_agreement':
                $agreement = $this->event_repository->get_first_agreement( $event_id );
                if ( $agreement ) {
                    // Return the initiator if available, else participant1
                    return $agreement->initiator_id ?: $agreement->participant1_id;
                }
                return false;
                
            case 'last_agreement':
                $agreement = $this->event_repository->get_last_agreement( $event_id );
                if ( $agreement ) {
                    return $agreement->initiator_id ?: $agreement->participant1_id;
                }
                return false;
                
            case 'deadline_rush':
                $agreement = $this->event_repository->get_deadline_rush_agreement( $event_id );
                if ( $agreement ) {
                    return $agreement->initiator_id ?: $agreement->participant1_id;
                }
                return false;
                
            case 'most_agreements':
                $participant = $this->event_repository->get_participant_with_most_agreements( $event_id );
                return $participant ? $participant->id : false;
                
            case 'shortest_time':
                $participant = $this->event_repository->get_participant_shortest_time_between_agreements( $event_id );
                return $participant ? $participant->id : false;
                
            default:
                return false;
        }
    }

    /**
     * Check if competition winner should be auto-selected now.
     *
     * Timing rules:
     * - After event start: first_registered, last_minute, longest_description, first_agreement
     * - After event end: last_agreement, deadline_rush, most_agreements, shortest_time
     *
     * @param string $type Competition type.
     * @param object $event Event object.
     * @return bool
     */
    public function should_auto_select_now( $type, $event ) {
        if ( ! is_object( $event ) ) {
            return false;
        }

        $timezone = wp_timezone();
        $current_time = current_datetime()->getTimestamp();

        $to_timestamp = static function ( $value ) use ( $timezone ) {
            $value = trim( (string) $value );
            if ( '' === $value ) {
                return false;
            }

            $formats = array(
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                'Y-m-d H:i:sP',
                'Y-m-d H:iP',
                'Y-m-d\\TH:i:s',
                'Y-m-d\\TH:i',
                'Y-m-d\\TH:i:sP',
                'Y-m-d\\TH:iP',
            );

            foreach ( $formats as $format ) {
                $date = \DateTimeImmutable::createFromFormat( $format, $value, $timezone );
                if ( ! ( $date instanceof \DateTimeImmutable ) ) {
                    continue;
                }

                $errors = \DateTimeImmutable::getLastErrors();
                if ( false === $errors || ( 0 === (int) $errors['warning_count'] && 0 === (int) $errors['error_count'] ) ) {
                    return $date->getTimestamp();
                }
            }

            try {
                $date = new \DateTimeImmutable( $value, $timezone );
                return $date->getTimestamp();
            } catch ( \Exception $e ) {
                return false;
            }
        };

        $start_time = $to_timestamp( $event->start_date );
        $end_time = $to_timestamp( $event->end_date );

        if ( false === $start_time || false === $end_time ) {
            // Fallback for legacy/bad datetime values in existing events.
            // If event is active, allow "after start" competitions to auto-select.
            if ( in_array( $type, array( 'first_registered', 'last_minute', 'longest_description', 'first_agreement' ), true ) ) {
                return ! empty( $event->is_active );
            }

            return false;
        }
        
        // After event start competitions
        if ( in_array( $type, array( 'first_registered', 'last_minute', 'longest_description', 'first_agreement' ), true ) ) {
            return $current_time >= $start_time;
        }
        
        // After event end competitions
        if ( in_array( $type, array( 'last_agreement', 'deadline_rush', 'most_agreements', 'shortest_time' ), true ) ) {
            return $current_time >= $end_time;
        }
        
        return false;
    }

    /**
     * Get competition with localized type name
     *
     * @param object $competition Competition object
     * @return object Competition with localized type_label
     */
    public function get_competition_with_label( $competition ) {
        $labels = array(
            'first_registered'   => __( 'First Registered Participant', 'volunteer-exchange-platform' ),
            'last_minute'        => __( 'Last Minute Registration', 'volunteer-exchange-platform' ),
            'longest_description' => __( 'Longest Description', 'volunteer-exchange-platform' ),
            'first_agreement'    => __( 'First Agreement', 'volunteer-exchange-platform' ),
            'last_agreement'     => __( 'Last Agreement of the Day', 'volunteer-exchange-platform' ),
            'deadline_rush'      => __( 'Last Minute Agreement', 'volunteer-exchange-platform' ),
            'most_agreements'    => __( 'Actor with Most Agreements', 'volunteer-exchange-platform' ),
            'shortest_time'      => __( 'Shortest Time Between Agreements', 'volunteer-exchange-platform' ),
            'custom'             => __( 'Custom Competition', 'volunteer-exchange-platform' ),
        );
        
        $competition->type_label = isset( $labels[ $competition->type ] ) ? $labels[ $competition->type ] : $competition->type;
        
        return $competition;
    }

            /**
             * Get predefined competition definitions.
             *
             * @return array
             */
            private function get_default_competition_definitions() {
                return array(
                    array(
                        'type'  => 'first_registered',
                        'title' => __( 'First Registered Participant', 'volunteer-exchange-platform' ),
                        'description' => __( 'Gives til den deltager, der forst blev registreret til eventet.', 'volunteer-exchange-platform' ),
                    ),
                    array(
                        'type'  => 'last_minute',
                        'title' => __( 'Last Minute Registration', 'volunteer-exchange-platform' ),
                        'description' => __( 'Gives til den deltager, der registrerede sig senest op mod eventstart.', 'volunteer-exchange-platform' ),
                    ),
                    array(
                        'type'  => 'longest_description',
                        'title' => __( 'Longest Description', 'volunteer-exchange-platform' ),
                        'description' => __( 'Gives til den deltager, der har skrevet den laengste profilbeskrivelse.', 'volunteer-exchange-platform' ),
                    ),
                    array(
                        'type'  => 'first_agreement',
                        'title' => __( 'First Agreement', 'volunteer-exchange-platform' ),
                        'description' => __( 'Gives til begge deltagere i den forste aftale, der blev indgaet pa eventet.', 'volunteer-exchange-platform' ),
                    ),
                    array(
                        'type'  => 'last_agreement',
                        'title' => __( 'Last Agreement of the Day', 'volunteer-exchange-platform' ),
                        'description' => __( 'Gives til begge deltagere i dagens sidste aftale.', 'volunteer-exchange-platform' ),
                    ),
                    array(
                        'type'  => 'deadline_rush',
                        'title' => __( 'Last Minute Agreement', 'volunteer-exchange-platform' ),
                        'description' => __( 'Gives til begge deltagere i den aftale, der blev indgaet taettest pa eventets afslutning.', 'volunteer-exchange-platform' ),
                    ),
                    array(
                        'type'  => 'most_agreements',
                        'title' => __( 'Actor with Most Agreements', 'volunteer-exchange-platform' ),
                        'description' => __( 'Gives til den deltager, der har indgaet flest aftaler i lobet af eventet.', 'volunteer-exchange-platform' ),
                    ),
                    array(
                        'type'  => 'shortest_time',
                        'title' => __( 'Shortest Time Between Agreements', 'volunteer-exchange-platform' ),
                        'description' => __( 'Gives til den deltager, der hurtigst lavede to aftaler efter hinanden.', 'volunteer-exchange-platform' ),
                    ),
                    array(
                        'type'  => 'custom',
                        'title' => __( 'Bedste børsudklædning', 'volunteer-exchange-platform' ),
                        'description' => __( 'Gives til den stand, der har den mest kreative og gennemforte udklaedning.', 'volunteer-exchange-platform' ),
                    ),
                    array(
                        'type'  => 'custom',
                        'title' => __( 'Bedste børsoptræden', 'volunteer-exchange-platform' ),
                        'description' => __( 'Gives til den stand, der skaber den bedste energi, formidling og oplevelse.', 'volunteer-exchange-platform' ),
                    ),
                );
            }
}
