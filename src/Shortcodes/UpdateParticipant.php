<?php
/**
 * Update participant shortcode
 * Usage: [update_participant]
 *
 * @package VolunteerExchangePlatform\Shortcodes
 */

namespace VolunteerExchangePlatform\Shortcodes;

use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;
use VolunteerExchangePlatform\Services\ParticipantTypeService;
use VolunteerExchangePlatform\Services\TagService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateParticipant {
    /**
     * @var EventService
     */
    private $event_service;

    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * @var TagService
     */
    private $tag_service;

    /**
     * @var ParticipantTypeService
     */
    private $participant_type_service;

    /**
     * Constructor.
     *
     * @param EventService|null       $event_service Event service.
     * @param ParticipantService|null $participant_service Participant service.
     * @param ParticipantTypeService|null $participant_type_service Participant type service.
     * @param TagService|null         $tag_service Tag service.
     * @return void
     */
    public function __construct( ?EventService $event_service = null, ?ParticipantService $participant_service = null, ?ParticipantTypeService $participant_type_service = null, ?TagService $tag_service = null ) {
        $this->event_service = $event_service ?: new EventService();
        $this->participant_service = $participant_service ?: new ParticipantService();
        $this->participant_type_service = $participant_type_service ?: new ParticipantTypeService();
        $this->tag_service = $tag_service ?: new TagService();

        add_shortcode( 'vep_update_participant', array( $this, 'render' ) );
    }

    /**
     * Render shortcode.
     *
     * @return string
     */
    public function render() {
        $active_event = $this->event_service->get_active_event();

        if ( ! $active_event ) {
            return '<div class="vep-message vep-error">' . esc_html__( 'No active event at the moment.', 'volunteer-exchange-platform' ) . '</div>';
        }

        $participants = $this->participant_service->get_for_event_select( $active_event->id );
        $types = $this->participant_type_service->get_all_for_select();
        $tags = $this->tag_service->get_paginated( 1000, 0, 'name', 'ASC' );

        ob_start();
        ?>
        <div class="vep-update-participant-form">
            <form id="vep-update-participant-form" class="vep-form" enctype="multipart/form-data">
                <?php wp_nonce_field( 'vep_update_participant', 'vep_update_participant_nonce', false ); ?>
                <input type="hidden" name="event_id" value="<?php echo esc_attr( $active_event->id ); ?>">

                <div class="vep-form-group">
                    <label for="update_participant_id"><?php esc_html_e( 'Select Organization', 'volunteer-exchange-platform' ); ?> <span class="required">*</span></label>
                    <select id="update_participant_id" name="participant_id" class="vep-choices" required>
                        <option value=""><?php esc_html_e( 'Select Organization', 'volunteer-exchange-platform' ); ?></option>
                        <?php foreach ( $participants as $participant ) : ?>
                            <option value="<?php echo esc_attr( $participant->id ); ?>"><?php echo esc_html( $participant->organization_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="vep-update-participant-fields" style="display: none;">

                <div class="vep-form-group">
                    <label for="update_organization_name"><?php esc_html_e( 'Organization Name', 'volunteer-exchange-platform' ); ?> <span class="required">*</span></label>
                    <input type="text" id="update_organization_name" name="organization_name" required>
                </div>

                <div class="vep-form-group">
                    <label for="update_participant_type_id"><?php esc_html_e( 'Organization Type', 'volunteer-exchange-platform' ); ?> <span class="required">*</span></label>
                    <select id="update_participant_type_id" name="participant_type_id" class="vep-choices" required>
                        <option value=""><?php esc_html_e( 'Select Type', 'volunteer-exchange-platform' ); ?></option>
                        <?php foreach ( $types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type->id ); ?>"><?php echo esc_html( $type->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="vep-form-group">
                    <label for="update_contact_person_name"><?php esc_html_e( 'Dit navn', 'volunteer-exchange-platform' ); ?> <span class="required">*</span></label>
                    <input type="text" id="update_contact_person_name" name="contact_person_name" required>
                </div>

                <div class="vep-form-group">
                    <label for="update_contact_email"><?php esc_html_e( 'E-mail', 'volunteer-exchange-platform' ); ?> <span class="required">*</span></label>
                    <input type="email" id="update_contact_email" name="contact_email" required>
                </div>

                <div class="vep-form-group">
                    <label for="update_contact_phone"><?php esc_html_e( 'Phone', 'volunteer-exchange-platform' ); ?></label>
                    <input type="tel" id="update_contact_phone" name="contact_phone">
                </div>

                <div class="vep-form-group">
                    <label for="update_logo"><?php esc_html_e( 'Organization Logo', 'volunteer-exchange-platform' ); ?></label>
                    <input type="file" id="update_logo" name="logo" accept="image/*">
                    <small><?php esc_html_e( 'Supported formats: JPG, PNG, GIF. Max size: 2MB', 'volunteer-exchange-platform' ); ?></small>
                </div>

                <div class="vep-form-group">
                    <label for="update_description"><?php esc_html_e( 'Organization Description', 'volunteer-exchange-platform' ); ?></label>
                    <textarea id="update_description" name="description" rows="4" placeholder="<?php esc_attr_e( 'Tell us about your organization...', 'volunteer-exchange-platform' ); ?>"></textarea>
                </div>

                <div class="vep-form-group">
                    <label for="update_expected_participants_count"><?php esc_html_e( 'Participants Expected', 'volunteer-exchange-platform' ); ?></label>
                    <input type="number" id="update_expected_participants_count" name="expected_participants_count" min="0" step="1" placeholder="<?php esc_attr_e( 'e.g., 12', 'volunteer-exchange-platform' ); ?>">
                </div>

                <div class="vep-form-group">
                    <label for="update_expected_participants_names"><?php esc_html_e( 'Participant Names', 'volunteer-exchange-platform' ); ?></label>
                    <textarea id="update_expected_participants_names" name="expected_participants_names" rows="3" placeholder="<?php esc_attr_e( 'e.g., Jane Doe, John Smith', 'volunteer-exchange-platform' ); ?>"></textarea>
                </div>

                <div class="vep-form-group">
                    <label><?php esc_html_e( 'What We Offer', 'volunteer-exchange-platform' ); ?></label>
                    <div class="vep-checkbox-group">
                        <?php foreach ( $tags as $tag ) : ?>
                            <label class="vep-checkbox-label">
                                <input type="checkbox" name="tags[]" value="<?php echo esc_attr( $tag->id ); ?>">
                                <span><?php echo esc_html( $tag->name ); ?></span>
                                <?php if ( $tag->description ) : ?>
                                    <small class="vep-tag-description"><?php echo esc_html( $tag->description ); ?></small>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="vep-form-group">
                    <button type="submit" class="vep-button vep-button-primary">
                        <?php esc_html_e( 'Update Participant', 'volunteer-exchange-platform' ); ?>
                    </button>
                </div>

                </div>

                <div class="vep-message vep-update-participant-message" style="display: none;"></div>
            </form>
        </div>
        <?php

        return ob_get_clean();
    }
}
