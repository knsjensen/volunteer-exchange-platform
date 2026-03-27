<?php
/**
 * Participants grid shortcode
 * Usage: [vep_participants_grid]
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
 * Participants grid shortcode
 * Usage: [vep_participants_grid]
 * 
 * @package VolunteerExchangePlatform\Shortcodes
 */
class ParticipantsGrid {
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
        add_shortcode('vep_participants_grid', array($this, 'render'));
    }
    
    /**
     * Render shortcode
     *
     * @param array $atts
     * @return string
     */
    public function render($atts) {
        // Get active event
        $active_event = $this->event_service->get_active_event();
        
        if (!$active_event) {
            return '<div class="vep-message vep-error">' . __('No active event at the moment.', 'volunteer-exchange-platform') . '</div>';
        }
        
        // Get approved participants for active event
        $participants = $this->participant_service->get_approved_for_event_with_type($active_event->id);
        
        if (empty($participants)) {
            return '<div class="vep-message vep-info">' . __('No participants yet.', 'volunteer-exchange-platform') . '</div>';
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
        
        $participant_tag_keys = array();
        $available_tags = array();
        $participant_type_keys = array();
        $available_types = array();        

        foreach ( $participants as $participant ) {
            $type_name = isset( $participant->type_name ) ? sanitize_text_field( $participant->type_name ) : '';
            $type_id = isset( $participant->participant_type_id ) ? absint( $participant->participant_type_id ) : 0;

            if ( $type_id > 0 && '' !== $type_name ) {
                $type_key = (string) $type_id;
                $participant_type_keys[ (int) $participant->id ] = $type_key;

                if ( ! isset( $available_types[ $type_key ] ) ) {
                    $available_types[ $type_key ] = $type_name;
                }
            } else {
                $participant_type_keys[ (int) $participant->id ] = '';
            }

            $tags = $this->participant_service->get_tags_for_participant( $participant->id );
            $tag_keys = array();

            if ( ! empty( $tags ) ) {
                foreach ( $tags as $tag ) {
                    $tag_name = isset( $tag->name ) ? sanitize_text_field( $tag->name ) : '';
                    if ( '' === $tag_name ) {
                        continue;
                    }

                    $tag_key = sanitize_title( $tag_name );
                    if ( '' === $tag_key ) {
                        continue;
                    }

                    $tag_keys[] = $tag_key;

                    if ( ! isset( $available_tags[ $tag_key ] ) ) {
                        $available_tags[ $tag_key ] = $tag_name;
                    }
                }
            }

            $participant_tag_keys[ (int) $participant->id ] = array_values( array_unique( $tag_keys ) );
        }

        if ( ! empty( $available_types ) ) {
            asort( $available_types, SORT_NATURAL | SORT_FLAG_CASE );
        }

        if ( ! empty( $available_tags ) ) {
            asort( $available_tags, SORT_NATURAL | SORT_FLAG_CASE );
        }

        ob_start();
        ?>
        <div class="vep-participants-grid">
            <?php if ( ! empty( $available_types ) ) : ?>
                <div class="vep-grid-filter vep-form-group">
                    <span class="vep-grid-filter-label"><?php esc_html_e( 'Filter by participant type', 'volunteer-exchange-platform' ); ?></span>
                    <div class="vep-grid-tag-filter-buttons" role="group" aria-label="<?php esc_attr_e( 'Filter by participant type', 'volunteer-exchange-platform' ); ?>">
                        <button type="button" class="vep-grid-tag-filter-button vep-grid-type-filter-button is-active" data-type-filter="">
                            <?php esc_html_e( 'All', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <?php foreach ( $available_types as $type_key => $type_name ) : ?>
                            <button type="button" class="vep-grid-tag-filter-button vep-grid-type-filter-button" data-type-filter="<?php echo esc_attr( $type_key ); ?>">
                                <?php echo esc_html( $type_name ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $available_tags ) ) : ?>
                <div class="vep-grid-filter vep-form-group">
                    <span class="vep-grid-filter-label"><?php esc_html_e( 'Filter by tag', 'volunteer-exchange-platform' ); ?></span>
                    <div class="vep-grid-tag-filter-buttons" role="group" aria-label="<?php esc_attr_e( 'Filter by tag', 'volunteer-exchange-platform' ); ?>">
                        <button type="button" class="vep-grid-tag-filter-button is-active" data-tag-filter="">
                            <?php esc_html_e( 'All', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <?php foreach ( $available_tags as $tag_key => $tag_name ) : ?>
                            <button type="button" class="vep-grid-tag-filter-button" data-tag-filter="<?php echo esc_attr( $tag_key ); ?>">
                                <?php echo esc_html( $tag_name ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="vep-grid">
                <?php foreach ($participants as $participant):
                    $participant_keys = isset( $participant_tag_keys[ (int) $participant->id ] ) ? $participant_tag_keys[ (int) $participant->id ] : array();
                    $participant_type_key = isset( $participant_type_keys[ (int) $participant->id ] ) ? $participant_type_keys[ (int) $participant->id ] : '';
                ?>
                    <div class="vep-grid-item" data-participant-id="<?php echo esc_attr($participant->id); ?>" data-tag-ids="<?php echo esc_attr( implode( ',', $participant_keys ) ); ?>" data-participant-type="<?php echo esc_attr( $participant_type_key ); ?>">
                        <div class="vep-participant-card">
                            <?php if ($participant->logo_url): ?>
                                <div class="vep-participant-logo">
                                    <img src="<?php echo esc_url($participant->logo_url); ?>" alt="<?php echo esc_attr($participant->organization_name); ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="vep-participant-info">
                                <h3><?php echo esc_html($participant->participant_number); ?>: <?php echo esc_html($participant->organization_name); ?></h3>
                                <p class="vep-participant-type"><?php echo esc_html($participant->type_name ?? ''); ?></p>
                                <a href="<?php echo esc_url( add_query_arg( 'vep_back', get_permalink(), \VolunteerExchangePlatform\Frontend\ParticipantPage::get_participant_url($participant->id) ) ); ?>" class="vep-button vep-button-secondary vep-view-details">
                                    <?php esc_html_e('View Details', 'volunteer-exchange-platform'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
