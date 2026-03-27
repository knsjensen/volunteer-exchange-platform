<?php
/**
 * Registration shortcode
 * Usage: [vep_registration]
 *
 * @package VEP
 * @subpackage Shortcodes
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Shortcodes;

use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantTypeService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registration shortcode
 * Usage: [vep_registration]
 * 
 * @package VolunteerExchangePlatform\Shortcodes
 */
class Registration {
    /**
     * @var EventService
     */
    private $event_service;

    /**
     * @var ParticipantTypeService
     */
    private $participant_type_service;

    /**
     * Constructor.
     *
     * @param EventService|null           $event_service Event service instance.
     * @param ParticipantTypeService|null $participant_type_service Participant type service instance.
     * @return void
     */
    public function __construct( ?EventService $event_service = null, ?ParticipantTypeService $participant_type_service = null ) {
        $this->event_service = $event_service ?: new EventService();
        $this->participant_type_service = $participant_type_service ?: new ParticipantTypeService();
        add_shortcode('vep_registration', array($this, 'render'));
    }
    
    /**
     * Render shortcode
     *
     * @param array $atts
     * @return string
     */
    public function render($atts) {
        $atts = shortcode_atts(array(
            'form_type' => 'simple',
        ), $atts, 'vep_registration');

        $form_type = strtolower(trim((string) $atts['form_type']));
        $is_multistep = in_array($form_type, array('multiform', 'multistep', 'multi'), true);

        // Get active event
        $active_event = $this->event_service->get_active_event();
        
        if (!$active_event) {
            return '<div class="vep-message vep-error">' . __('No active event at the moment.', 'volunteer-exchange-platform') . '</div>';
        }
        
        // Get participant types
        $types = $this->participant_type_service->get_all_for_select();
        
        ob_start();
        ?>
        <div class="vep-registration-form">
            <form id="vep-registration-form" class="vep-form<?php echo $is_multistep ? ' vep-registration-multistep' : ''; ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('vep_registration', 'vep_registration_nonce', false); ?>
                <input type="hidden" name="event_id" value="<?php echo esc_attr($active_event->id); ?>">

                <?php if (!$is_multistep): ?>
                    <div class="vep-form-group">
                        <label for="organization_name"><?php esc_html_e('Organization Name', 'volunteer-exchange-platform'); ?> <span class="required">*</span></label>
                        <input type="text" id="organization_name" name="organization_name" required>
                    </div>

                    <div class="vep-form-group">
                        <label for="participant_type_id"><?php esc_html_e('Organization Type', 'volunteer-exchange-platform'); ?> <span class="required">*</span></label>
                        <select id="participant_type_id" name="participant_type_id" required>
                            <option value=""><?php esc_html_e('Select Type', 'volunteer-exchange-platform'); ?></option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?php echo esc_attr($type->id); ?>"><?php echo esc_html($type->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="vep-form-group">
                        <label for="contact_person_name"><?php esc_html_e('Contact name', 'volunteer-exchange-platform'); ?> <span class="required">*</span></label>
                        <input type="text" id="contact_person_name" name="contact_person_name" required>
                    </div>
                    
                    <div class="vep-form-group">
                        <label for="contact_email"><?php esc_html_e('E-mail', 'volunteer-exchange-platform'); ?> <span class="required">*</span></label>
                        <input type="email" id="contact_email" name="contact_email" required>
                    </div>
                    
                    <div class="vep-form-group">
                        <label for="contact_phone"><?php esc_html_e('Phone', 'volunteer-exchange-platform'); ?></label>
                        <input type="tel" id="contact_phone" name="contact_phone">
                    </div>
                    
                    <div class="vep-form-group">
                        <button type="submit" class="vep-button vep-button-primary">
                            <?php esc_html_e('Register', 'volunteer-exchange-platform'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="vep-steps-indicator" aria-hidden="true">
                        <span class="vep-step-dot is-active">1</span>
                        <span class="vep-step-dot">2</span>
                    </div>

                    <div class="vep-step is-active" data-vep-step="1">
                        <div class="vep-form-group">
                            <label for="organization_name"><?php esc_html_e('Organization Name', 'volunteer-exchange-platform'); ?> <span class="required">*</span></label>
                            <input type="text" id="organization_name" name="organization_name" required>
                        </div>

                        <div class="vep-form-group">
                            <label for="participant_type_id"><?php esc_html_e('Organization Type', 'volunteer-exchange-platform'); ?> <span class="required">*</span></label>
                            <select id="participant_type_id" name="participant_type_id" required>
                                <option value=""><?php esc_html_e('Select Type', 'volunteer-exchange-platform'); ?></option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo esc_attr($type->id); ?>"><?php echo esc_html($type->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="vep-form-actions">
                            <button type="button" class="vep-button vep-button-primary" data-vep-next>
                                <?php esc_html_e('Next', 'volunteer-exchange-platform'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="vep-step" data-vep-step="2">
                        <div class="vep-form-group">
                            <label for="contact_person_name"><?php esc_html_e('Dit navn', 'volunteer-exchange-platform'); ?> <span class="required">*</span></label>
                            <input type="text" id="contact_person_name" name="contact_person_name" required>
                        </div>
                        
                        <div class="vep-form-group">
                            <label for="contact_email"><?php esc_html_e('E-mail', 'volunteer-exchange-platform'); ?> <span class="required">*</span></label>
                            <input type="email" id="contact_email" name="contact_email" required>
                        </div>
                        
                        <div class="vep-form-group">
                            <label for="contact_phone"><?php esc_html_e('Phone', 'volunteer-exchange-platform'); ?></label>
                            <input type="tel" id="contact_phone" name="contact_phone">
                        </div>

                        <div class="vep-form-actions">
                            <button type="button" class="vep-button vep-button-secondary" data-vep-prev>
                                <?php esc_html_e('Back', 'volunteer-exchange-platform'); ?>
                            </button>
                            <button type="submit" class="vep-button vep-button-primary">
                                <?php esc_html_e('Register', 'volunteer-exchange-platform'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="vep-message vep-registration-message" style="display: none;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
