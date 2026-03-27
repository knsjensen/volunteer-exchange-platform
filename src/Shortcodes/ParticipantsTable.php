<?php
/**
 * Participants table shortcode
 * Usage: [vep_participants_table]
 *
 * @package VEP
 * @subpackage Shortcodes
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Shortcodes;

use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Participants table shortcode
 * Usage: [vep_participants_table]
 *
 * @package VolunteerExchangePlatform\Shortcodes
 */
class ParticipantsTable {
    /**
     * @var EventService
     */
    private $event_service;

    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * Constructor.
     *
     * @param EventService|null       $event_service Event service instance.
     * @param ParticipantService|null $participant_service Participant service instance.
     * @return void
     */
    public function __construct( ?EventService $event_service = null, ?ParticipantService $participant_service = null ) {
        $this->event_service = $event_service ?: new EventService();
        $this->participant_service = $participant_service ?: new ParticipantService();
        add_shortcode( 'vep_participants_table', array( $this, 'render' ) );
    }

    /**
     * Render shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render( $atts ) {
        $atts = shortcode_atts(
            array(
                'show_button' => 'no',
            ),
            $atts,
            'vep_participants_table'
        );

        $show_button = in_array( strtolower( trim( (string) $atts['show_button'] ) ), array( '1', 'yes', 'true', 'on' ), true );

        $active_event = $this->event_service->get_active_event();

        if ( ! $active_event ) {
            return '<div class="vep-message vep-error">' . __( 'No active event at the moment.', 'volunteer-exchange-platform' ) . '</div>';
        }

        $participants = $this->participant_service->get_approved_for_event_with_type( $active_event->id );

        if ( empty( $participants ) ) {
            return '<div class="vep-message vep-info">' . __( 'No participants yet.', 'volunteer-exchange-platform' ) . '</div>';
        }

        usort(
            $participants,
            static function ( $left, $right ) {
                $left_number = isset( $left->participant_number ) ? (int) $left->participant_number : 0;
                $right_number = isset( $right->participant_number ) ? (int) $right->participant_number : 0;

                if ( $left_number !== $right_number ) {
                    return $left_number <=> $right_number;
                }

                $left_name = isset( $left->organization_name ) ? (string) $left->organization_name : '';
                $right_name = isset( $right->organization_name ) ? (string) $right->organization_name : '';

                return strcasecmp( $left_name, $right_name );
            }
        );

        ob_start();
        ?>
        <div class="vep-agreements-table-wrap vep-participants-table-wrap">
            <table class="vep-agreements-table vep-participants-table">
                <thead>
                    <tr>
                        <th class="vep-agreements-col-num"><?php esc_html_e( 'Number', 'volunteer-exchange-platform' ); ?></th>
                        <th><?php esc_html_e( 'Participant name', 'volunteer-exchange-platform' ); ?></th>
                        <?php if ( $show_button ) : ?>
                            <th class="vep-participants-table-col-action"></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $participants as $participant ) : ?>
                        <tr>
                            <td class="vep-agreements-col-num"><strong><?php echo esc_html( $participant->participant_number ); ?></strong></td>
                            <td><?php echo esc_html( $participant->organization_name ); ?></td>
                            <?php if ( $show_button ) : ?>
                                <td class="vep-participants-table-col-action">
                                    <a href="<?php echo esc_url( add_query_arg( 'vep_back', get_permalink(), \VolunteerExchangePlatform\Frontend\ParticipantPage::get_participant_url( $participant->id ) ) ); ?>" class="vep-button vep-button-secondary vep-view-details">
                                        <?php esc_html_e( 'View Details', 'volunteer-exchange-platform' ); ?>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php

        return ob_get_clean();
    }
}
