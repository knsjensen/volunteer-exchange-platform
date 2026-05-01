<?php
/**
 * Handles key-based participant update pages
 *
 * @package VEP
 * @subpackage Frontend
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Frontend;

use VolunteerExchangePlatform\Email\Settings;
use VolunteerExchangePlatform\Services\ParticipantService;
use VolunteerExchangePlatform\Services\ParticipantTypeService;
use VolunteerExchangePlatform\Services\TagService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateParticipantPage {
    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * @var ParticipantTypeService
     */
    private $participant_type_service;

    /**
     * @var TagService
     */
    private $tag_service;

    /**
     * Constructor.
     *
     * @param ParticipantService|null     $participant_service Participant service.
     * @param ParticipantTypeService|null $participant_type_service Participant type service.
     * @param TagService|null             $tag_service Tag service.
     * @return void
     */
    public function __construct(
        ?ParticipantService $participant_service = null,
        ?ParticipantTypeService $participant_type_service = null,
        ?TagService $tag_service = null
    ) {
        $this->participant_service = $participant_service ?: new ParticipantService();
        $this->participant_type_service = $participant_type_service ?: new ParticipantTypeService();
        $this->tag_service = $tag_service ?: new TagService();

        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_update_participant_page' ) );
    }

    /**
     * Add rewrite rules for update participant pages.
     *
     * @return void
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^vep/updateparticipant/([^/]+)/?$',
            'index.php?vep_update_participant_key=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^vep/opdaterdeltager/([^/]+)/?$',
            'index.php?vep_update_participant_key=$matches[1]',
            'top'
        );
    }

    /**
     * Register custom query vars.
     *
     * @param array $vars Existing query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'vep_update_participant_key';
        return $vars;
    }

    /**
     * Handle update participant page rendering.
     *
     * @return void
     */
    public function handle_update_participant_page() {
        $randon_key = get_query_var( 'vep_update_participant_key' );

        if ( ! $randon_key ) {
            return;
        }

        $randon_key = sanitize_text_field( (string) $randon_key );
        $participant = $this->participant_service->get_by_randon_key( $randon_key );

        get_header();

        echo '<div class="vep-page-wrapper">';
        if ( ! $participant ) {
            echo '<div class="vep-message vep-error">' . esc_html__( 'Participant not found.', 'volunteer-exchange-platform' ) . '</div>';
        } else {
            echo $this->render_update_form( $participant, $randon_key );
        }
        echo '</div>';

        get_footer();
        exit;
    }

    /**
     * Render the key-based update form.
     *
     * @param object $participant Participant row.
     * @param string $randon_key Participant key from route.
     * @return string
     */
    private function render_update_form( $participant, $randon_key ) {
        $types = $this->participant_type_service->get_all_for_select();
        $tags = $this->tag_service->get_paginated( 1000, 0, 'name', 'ASC' );
        $max_participants = Settings::max_participants_per_organization();
        $selected_tag_ids = array_map( 'absint', $this->participant_service->get_tag_ids( (int) $participant->id ) );
        $logo_url = isset( $participant->logo_url ) ? trim( (string) $participant->logo_url ) : '';
        if ( '' !== $logo_url ) {
            $logo_url = set_url_scheme( $logo_url, is_ssl() ? 'https' : 'http' );
        }
        $no_field_help_text = __( 'If you do not know this information and do not want reminder emails to fill in the field later, check this box.', 'volunteer-exchange-platform' );

        ob_start();
        ?>
        <div class="vep-participant-detail-page vep-update-participant-page">
            <h1 class="vep-page-title"><?php esc_html_e( 'Update participant', 'volunteer-exchange-platform' ); ?></h1>
            <div class="vep-participant-detail-content">
            <form id="vep-update-participant-form" class="vep-form vep-update-participant-form" enctype="multipart/form-data">
                <?php wp_nonce_field( 'vep_update_participant', 'vep_update_participant_nonce', false ); ?>
                <input type="hidden" name="participant_id" value="<?php echo esc_attr( (int) $participant->id ); ?>">
                <input type="hidden" name="event_id" value="<?php echo esc_attr( (int) $participant->event_id ); ?>">
                <input type="hidden" name="randon_key" value="<?php echo esc_attr( $randon_key ); ?>">

                <div class="vep-form-group">
                    <label for="update_organization_name"><?php esc_html_e( 'Organization Name', 'volunteer-exchange-platform' ); ?> <span class="required">*</span></label>
                    <input type="text" id="update_organization_name" name="organization_name" value="<?php echo esc_attr( (string) $participant->organization_name ); ?>" required>
                </div>

                <div class="vep-form-group">
                    <label for="update_participant_type_id"><?php esc_html_e( 'Organization Type', 'volunteer-exchange-platform' ); ?> <span class="required">*</span></label>
                    <select id="update_participant_type_id" name="participant_type_id" class="vep-choices" required>
                        <option value=""><?php esc_html_e( 'Select Type', 'volunteer-exchange-platform' ); ?></option>
                        <?php foreach ( $types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type->id ); ?>" <?php selected( (int) $participant->participant_type_id, (int) $type->id ); ?>><?php echo esc_html( $type->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="vep-form-group">
                    <label for="update_contact_person_name"><?php esc_html_e( 'Contact name', 'volunteer-exchange-platform' ); ?> <span class="required">*</span></label>
                    <input type="text" id="update_contact_person_name" name="contact_person_name" value="<?php echo esc_attr( (string) $participant->contact_person_name ); ?>" required>
                </div>

                <div class="vep-form-group">
                    <label for="update_contact_email"><?php esc_html_e( 'E-mail', 'volunteer-exchange-platform' ); ?> <span class="required">*</span></label>
                    <input type="email" id="update_contact_email" name="contact_email" value="<?php echo esc_attr( (string) $participant->contact_email ); ?>" required>
                </div>

                <div class="vep-form-group">
                    <label for="update_contact_phone"><?php esc_html_e( 'Phone', 'volunteer-exchange-platform' ); ?></label>
                    <input type="tel" id="update_contact_phone" name="contact_phone" value="<?php echo esc_attr( (string) $participant->contact_phone ); ?>">
                </div>

                <div class="vep-form-group vep-form-group--full">
                    <label for="update_description"><?php esc_html_e( 'Organization Description', 'volunteer-exchange-platform' ); ?></label>
                    <textarea id="update_description" name="description" rows="4" placeholder="<?php esc_attr_e( 'Tell us about your organization...', 'volunteer-exchange-platform' ); ?>"><?php echo esc_textarea( (string) $participant->description ); ?></textarea>
                </div>

                <div id="vep-logo-field" class="vep-form-group vep-form-group--full<?php echo (int) ( $participant->no_logo ?? 0 ) ? ' is-hidden' : ''; ?>">
                    <label for="update_logo"><?php esc_html_e( 'Organization Logo', 'volunteer-exchange-platform' ); ?></label>
                    <input type="file" id="update_logo" name="logo" accept="image/*">
                    <small><?php esc_html_e( 'Supported formats: JPG, PNG, GIF. Max size: 2MB', 'volunteer-exchange-platform' ); ?></small>
                    <div class="vep-current-logo-wrapper<?php echo '' === $logo_url ? ' is-hidden' : ''; ?>">
                        <div class="vep-current-logo-preview">
                            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( (string) $participant->organization_name ); ?>" loading="lazy" decoding="async">
                            <button type="button" class="vep-logo-remove-btn" aria-label="<?php esc_attr_e( 'Remove current logo', 'volunteer-exchange-platform' ); ?>">&#x2715;</button>
                        </div>
                        <input type="hidden" id="update_remove_logo" name="remove_logo" value="0">
                    </div>
                </div>
                <div class="vep-form-group vep-form-group--full">
                    <div class="vep-toggle-checkbox-row">
                        <label class="vep-toggle-checkbox-label">
                            <input type="checkbox" id="no_logo" name="no_logo" value="1" data-toggle-target="vep-logo-field" <?php checked( (int) ( $participant->no_logo ?? 0 ), 1 ); ?>>
                            <span><?php esc_html_e( 'Has no logo', 'volunteer-exchange-platform' ); ?></span>
                        </label>
                        <span class="vep-help-popover" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'More information', 'volunteer-exchange-platform' ); ?>">?</span>
                        <span class="vep-help-popover-content"><?php echo esc_html( $no_field_help_text ); ?></span>
                    </div>
                </div>

                <div id="vep-link-field" class="vep-form-group vep-form-group--full<?php echo (int) ( $participant->no_link ?? 0 ) ? ' is-hidden' : ''; ?>">
                    <label for="update_link"><?php esc_html_e( 'Link to homepage', 'volunteer-exchange-platform' ); ?></label>
                    <input type="url" id="update_link" name="link" value="<?php echo esc_attr( (string) ( $participant->link ?? '' ) ); ?>" placeholder="https://">
                </div>
                <div class="vep-form-group vep-form-group--full">
                    <div class="vep-toggle-checkbox-row">
                        <label class="vep-toggle-checkbox-label">
                            <input type="checkbox" id="no_link" name="no_link" value="1" data-toggle-target="vep-link-field" <?php checked( (int) ( $participant->no_link ?? 0 ), 1 ); ?>>
                            <span><?php esc_html_e( 'Has no homepage / Socialkompas link', 'volunteer-exchange-platform' ); ?></span>
                        </label>
                        <span class="vep-help-popover" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'More information', 'volunteer-exchange-platform' ); ?>">?</span>
                        <span class="vep-help-popover-content"><?php echo esc_html( $no_field_help_text ); ?></span>
                    </div>
                </div>

                <div id="vep-expected-count-field" class="vep-form-group vep-form-group--full<?php echo (int) ( $participant->no_expected_count ?? 0 ) ? ' is-hidden' : ''; ?>">
                    <label for="update_expected_participants_count"><?php printf( esc_html__( 'Participants Expected (1-%d)', 'volunteer-exchange-platform' ), (int) $max_participants ); ?></label>
                    <input type="number" id="update_expected_participants_count" name="expected_participants_count" min="1" max="<?php echo esc_attr( (int) $max_participants ); ?>" step="1" placeholder="<?php esc_attr_e( 'e.g., 3', 'volunteer-exchange-platform' ); ?>" value="<?php echo esc_attr( isset( $participant->expected_participants_count ) ? (string) $participant->expected_participants_count : '' ); ?>">
                </div>
                <div class="vep-form-group vep-form-group--full">
                    <div class="vep-toggle-checkbox-row">
                        <label class="vep-toggle-checkbox-label">
                            <input type="checkbox" id="no_expected_count" name="no_expected_count" value="1" data-toggle-target="vep-expected-count-field" <?php checked( (int) ( $participant->no_expected_count ?? 0 ), 1 ); ?>>
                            <span><?php esc_html_e( 'Does not know number of participants', 'volunteer-exchange-platform' ); ?></span>
                        </label>
                        <span class="vep-help-popover" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'More information', 'volunteer-exchange-platform' ); ?>">?</span>
                        <span class="vep-help-popover-content"><?php echo esc_html( $no_field_help_text ); ?></span>
                    </div>
                </div>

                <div id="vep-expected-names-field" class="vep-form-group vep-form-group--full<?php echo (int) ( $participant->no_expected_names ?? 0 ) ? ' is-hidden' : ''; ?>">
                    <label for="update_expected_participants_names"><?php esc_html_e( 'Participant Names', 'volunteer-exchange-platform' ); ?></label>
                    <textarea id="update_expected_participants_names" name="expected_participants_names" rows="3" placeholder="<?php esc_attr_e( 'e.g., Jane Doe, John Smith', 'volunteer-exchange-platform' ); ?>"><?php echo esc_textarea( (string) $participant->expected_participants_names ); ?></textarea>
                </div>
                <div class="vep-form-group vep-form-group--full">
                    <div class="vep-toggle-checkbox-row">
                        <label class="vep-toggle-checkbox-label">
                            <input type="checkbox" id="no_expected_names" name="no_expected_names" value="1" data-toggle-target="vep-expected-names-field" <?php checked( (int) ( $participant->no_expected_names ?? 0 ), 1 ); ?>>
                            <span><?php esc_html_e( 'Does not know participant names', 'volunteer-exchange-platform' ); ?></span>
                        </label>
                        <span class="vep-help-popover" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'More information', 'volunteer-exchange-platform' ); ?>">?</span>
                        <span class="vep-help-popover-content"><?php echo esc_html( $no_field_help_text ); ?></span>
                    </div>
                </div>

                <div class="vep-form-group vep-form-group--full">
                    <label><?php esc_html_e( 'What We Offer', 'volunteer-exchange-platform' ); ?></label>
                    <div class="vep-checkbox-group">
                        <?php foreach ( $tags as $tag ) : ?>
                            <?php $is_checked = in_array( (int) $tag->id, $selected_tag_ids, true ); ?>
                            <label class="vep-checkbox-label">
                                <input type="checkbox" name="tags[]" value="<?php echo esc_attr( $tag->id ); ?>" <?php checked( $is_checked ); ?>>
                                <span><?php echo esc_html( $tag->name ); ?></span>
                                <?php if ( $tag->description ) : ?>
                                    <small class="vep-tag-description"><?php echo esc_html( $tag->description ); ?></small>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="vep-form-group vep-form-group--full">
                    <button type="submit" class="vep-button vep-button-primary">
                        <?php esc_html_e( 'Update Participant', 'volunteer-exchange-platform' ); ?>
                    </button>
                </div>

                <div class="vep-message vep-update-participant-message" style="display: none;"></div>
            </form>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Get update participant URL by random key.
     *
     * @param string $randon_key Participant key.
     * @return string
     */
    public static function get_update_participant_url( $randon_key ) {
        $randon_key = sanitize_text_field( (string) $randon_key );

        if ( empty( get_option( 'permalink_structure' ) ) ) {
            return add_query_arg( 'vep_update_participant_key', rawurlencode( $randon_key ), home_url( '/' ) );
        }

        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
        $default_slug = ( is_string( $locale ) && strpos( strtolower( $locale ), 'da' ) === 0 ) ? 'opdaterdeltager' : 'updateparticipant';
        $slug = (string) apply_filters( 'vep_update_participant_slug', $default_slug, $locale, $randon_key );

        if ( '' === trim( $slug ) ) {
            $slug = $default_slug;
        }

        return home_url( user_trailingslashit( 'vep/' . $slug . '/' . rawurlencode( $randon_key ) ) );
    }
}
