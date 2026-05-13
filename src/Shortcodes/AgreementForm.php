<?php
/**
 * Agreement form shortcode
 * Usage: [vep_agreement_form]
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
 * Agreement form shortcode
 * Usage: [vep_agreement_form]
 * 
 * @package VolunteerExchangePlatform\Shortcodes
 */
class AgreementForm {
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
        add_shortcode('vep_agreement_form', array($this, 'render'));
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

        $event_end_ts = isset( $active_event->end_date ) ? strtotime( (string) $active_event->end_date ) : false;
        if ( false !== $event_end_ts && $event_end_ts < current_time( 'timestamp' ) ) {
            $event_name = isset( $active_event->name ) ? (string) $active_event->name : '';

            return '<div class="vep-message vep-info">' . sprintf(
                esc_html__( '%s is over for this time.', 'volunteer-exchange-platform' ),
                esc_html( $event_name )
            ) . '</div>';
        }
        
        // Get approved participants for active event
        $participants = $this->participant_service->get_approved_for_event_select($active_event->id);
        
        if (count($participants) < 2) {
            return '<div class="vep-message vep-info">' . __('At least 2 approved participants are required to create agreements.', 'volunteer-exchange-platform') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="vep-agreement-form">
            <form id="vep-agreement-form" class="vep-form">
                <?php wp_nonce_field('vep_agreement', 'vep_agreement_nonce', false); ?>
                <input type="hidden" name="event_id" value="<?php echo esc_attr($active_event->id); ?>">
                
                <div class="vep-form-row">
                    <div class="vep-form-group vep-form-half">
                        <label for="participant1_id" class="vep-form-label-with-action">
                            <span><?php esc_html_e('Participant 1', 'volunteer-exchange-platform'); ?> <span class="required">*</span></span>
                            <button type="button" id="vep-clear-participant1-preselection" class="vep-inline-action" style="display: none;">
                                <?php esc_html_e('Clear', 'volunteer-exchange-platform'); ?>
                            </button>
                        </label>
                        <select id="participant1_id" name="participant1_id" class="vep-choices" required>
                            <option value=""><?php esc_html_e('Select participant...', 'volunteer-exchange-platform'); ?></option>
                            <?php foreach ($participants as $participant): ?>
                                <option value="<?php echo esc_attr($participant->id); ?>"><?php echo esc_html($participant->participant_number . ': ' . $participant->organization_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label class="vep-radio-label vep-initiator-label">
                            <input type="radio" name="initiator" value="participant1" required>
                            <span><?php esc_html_e('This participant initiates', 'volunteer-exchange-platform'); ?></span>
                        </label>
                    </div>
                    
                    <div class="vep-form-group vep-form-half">
                        <label for="participant2_id"><?php esc_html_e('Participant 2', 'volunteer-exchange-platform'); ?> <span class="required">*</span></label>
                        <select id="participant2_id" name="participant2_id" class="vep-choices" required>
                            <option value=""><?php esc_html_e('Select participant...', 'volunteer-exchange-platform'); ?></option>
                            <?php foreach ($participants as $participant): ?>
                                <option value="<?php echo esc_attr($participant->id); ?>"><?php echo esc_html($participant->participant_number . ': ' . $participant->organization_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label class="vep-radio-label vep-initiator-label">
                            <input type="radio" name="initiator" value="participant2" required>
                            <span><?php esc_html_e('This participant initiates', 'volunteer-exchange-platform'); ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="vep-form-group">
                    <label for="agreement_description"><?php esc_html_e('Agreement Description', 'volunteer-exchange-platform'); ?> <span class="required">*</span></label>
                    <textarea id="agreement_description" name="agreement_description" rows="5" required></textarea>
                    <small><?php esc_html_e('Describe what the agreement is about and what each party will do.', 'volunteer-exchange-platform'); ?></small>
                </div>
                
                <div class="vep-form-group">
                    <button type="submit" class="vep-button vep-button-primary">
                        <?php esc_html_e('Create Agreement', 'volunteer-exchange-platform'); ?>
                    </button>
                </div>
                
                <div id="vep-agreement-message" class="vep-message" style="display: none;"></div>
            </form>
        </div>

        <div id="vep-participant-preference-modal" class="vep-participant-preference-modal" style="display: none;" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="vep-participant-preference-title">
            <div class="vep-participant-preference-modal-dialog">
                <h3 id="vep-participant-preference-title"><?php esc_html_e('Which participant are you?', 'volunteer-exchange-platform'); ?></h3>
                <p><?php esc_html_e('Choose one to preselect as Participant 1 next time.', 'volunteer-exchange-platform'); ?></p>
                <div class="vep-participant-preference-options">
                    <button type="button" class="vep-button vep-button-secondary" id="vep-participant-preference-option-1"></button>
                    <button type="button" class="vep-button vep-button-secondary" id="vep-participant-preference-option-2"></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
