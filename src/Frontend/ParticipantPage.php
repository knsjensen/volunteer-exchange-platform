<?php

/**
 * Handles the participant detail page
 *
 * @package VEP
 * @subpackage Frontend
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Frontend;

use VolunteerExchangePlatform\Email\Settings;
use VolunteerExchangePlatform\Services\ParticipantService;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles the participant detail page
 * 
 * Manages rewrite rules, query vars, and rendering of participant detail pages
 * 
 * @package VolunteerExchangePlatform\Frontend
 */
class ParticipantPage
{
    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * Build inline style for action buttons based on settings color.
     *
     * @return string
     */
    private function get_button_style()
    {
        $settings = Settings::get_all();
        $background_color = isset($settings['button_background_color']) ? sanitize_hex_color((string) $settings['button_background_color']) : '';
        $border_color = isset($settings['button_border_color']) ? sanitize_hex_color((string) $settings['button_border_color']) : '';
        $text_color = isset($settings['button_text_color']) ? sanitize_hex_color((string) $settings['button_text_color']) : '';

        if (! $background_color) {
            return '';
        }

        if (! $border_color) {
            $border_color = $background_color;
        }

        if (! $text_color) {
            $text_color = $this->get_contrasting_text_color($background_color);
        }

        return sprintf(
            'background-color: %1$s; border-color: %2$s; color: %3$s;',
            $background_color,
            $border_color,
            $text_color
        );
    }

    /**
     * Pick a readable text color for a hex background.
     *
     * @param string $hex_color Hex color with leading #.
     * @return string
     */
    private function get_contrasting_text_color($hex_color)
    {
        $hex = ltrim($hex_color, '#');
        if (6 !== strlen($hex)) {
            return '#ffffff';
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $brightness >= 140 ? '#1f2328' : '#ffffff';
    }

    /**
     * Constructor.
     *
     * @param ParticipantService|null $participant_service Participant service instance.
     * @return void
     */
    public function __construct(?ParticipantService $participant_service = null)
    {
        $this->participant_service = $participant_service ?: new ParticipantService();
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_participant_page'));
    }

    /**
     * Add rewrite rules for participant pages
     *
     * @return void
     */
    public function add_rewrite_rules()
    {
        // Add rewrite rules for both English and Danish slugs
        add_rewrite_rule(
            '^vep/participant/([0-9]+)/?$',
            'index.php?vep_participant_id=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^vep/deltager/([0-9]+)/?$',
            'index.php?vep_participant_id=$matches[1]',
            'top'
        );
    }

    /**
     * Add custom query vars
     *
     * @param array $vars Existing query vars.
     * @return array
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'vep_participant_id';
        return $vars;
    }

    /**
     * Handle participant page template
     *
     * @return void
     */
    public function handle_participant_page()
    {
        $participant_id = get_query_var('vep_participant_id');

        if (!$participant_id) {
            return;
        }

        // Load WordPress header
        get_header();

        // Render participant content
        echo '<div class="vep-page-wrapper">';
        echo wp_kses_post($this->render_participant_detail(intval($participant_id)));
        echo '</div>';

        // Load WordPress footer
        get_footer();

        exit;
    }

    /**
     * Render participant detail
     *
     * @param int $participant_id
     * @return string
     */
    private function render_participant_detail($participant_id)
    {
        // Get participant details
        $participant = $this->participant_service->get_approved_by_id_with_details($participant_id);

        if (!$participant) {
            return '<div class="vep-message vep-error">' . esc_html__('Participant not found or not approved.', 'volunteer-exchange-platform') . '</div>';
        }

        // Get tags for this participant
        $tags = $this->participant_service->get_tags_for_participant($participant_id);
        $button_style = $this->get_button_style();

        ob_start();
?>
        <div class="vep-participant-detail-page">
            <div class="vep-participant-detail-header">
                <h1>
                    <?php if ($participant->participant_number): ?>
                        <?php echo esc_html($participant->participant_number); ?>:
                    <?php endif; ?>
                    <?php echo esc_html($participant->organization_name); ?>
                </h1>
                <?php if ($participant->logo_url): ?>
                    <div class="vep-participant-detail-logo">
                        <img src="<?php echo esc_url($participant->logo_url); ?>" alt="<?php echo esc_attr($participant->organization_name); ?>">
                    </div>
                <?php endif; ?>
                <?php if ($participant->type_name): ?>
                    <h2><?php echo esc_html($participant->type_name); ?></h2>
                <?php endif; ?>
                <?php if (!empty($tags)): ?>
                    <div class="vep-participant-tags">
                        <?php foreach ($tags as $tag): ?>
                            <span class="vep-tag"><?php echo esc_html($tag->name); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="vep-participant-detail-content">
                <?php if ($participant->description): ?>
                    <div class="vep-detail-section">
                        <h2><?php esc_html_e('About', 'volunteer-exchange-platform'); ?></h2>
                        <div class="vep-detail-description">
                            <?php echo nl2br(esc_html($participant->description)); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (
                    (property_exists($participant, 'contact_person_name') && ! empty($participant->contact_person_name)) ||
                    (property_exists($participant, 'contact_email') && ! empty($participant->contact_email)) ||
                    (property_exists($participant, 'contact_phone') && ! empty($participant->contact_phone))
                ): ?>
                    <div class="vep-detail-section">
                        <h2><?php esc_html_e('Contact', 'volunteer-exchange-platform'); ?></h2>

                        <?php if (property_exists($participant, 'contact_person_name') && !empty($participant->contact_person_name)): ?>
                            <div class="vep-detail-item">
                                <strong><?php esc_html_e('Contact name:', 'volunteer-exchange-platform'); ?></strong>
                                <span><?php echo esc_html($participant->contact_person_name); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (property_exists($participant, 'contact_email') && !empty($participant->contact_email)): ?>
                            <div class="vep-detail-item">
                                <strong><?php esc_html_e('Email:', 'volunteer-exchange-platform'); ?></strong>
                                <a href="mailto:<?php echo esc_attr($participant->contact_email); ?>">
                                    <?php echo esc_html($participant->contact_email); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if (property_exists($participant, 'contact_phone') && !empty($participant->contact_phone)): ?>
                            <div class="vep-detail-item">
                                <strong><?php esc_html_e('Phone:', 'volunteer-exchange-platform'); ?></strong>
                                <a href="tel:<?php echo esc_attr($participant->contact_phone); ?>">
                                    <?php echo esc_html($participant->contact_phone); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="vep-detail-actions">
                    <a href="<?php echo esc_url($this->get_back_url()); ?>" class="vep-button vep-button-secondary"<?php echo '' !== $button_style ? ' style="' . esc_attr($button_style) . '"' : ''; ?>>
                        <?php esc_html_e('Back to Participants', 'volunteer-exchange-platform'); ?>
                    </a>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * Resolve safe back URL for participant detail page.
     *
     * Priority:
     * 1) Explicit `vep_back` query argument from grid link.
     * 2) HTTP referrer if present and safe.
     * 3) Site home URL.
     *
     * @return string
     */
    private function get_back_url()
    {
        $fallback = home_url('/');

        if (isset($_GET['vep_back'])) {
            $requested_back_url = esc_url_raw(wp_unslash($_GET['vep_back']));
            if (! empty($requested_back_url)) {
                return wp_validate_redirect($requested_back_url, $fallback);
            }
        }

        $referer = wp_get_referer();
        if ($referer) {
            return wp_validate_redirect($referer, $fallback);
        }

        return $fallback;
    }

    /**
     * Get participant page URL
     *
     * @param int $participant_id Participant ID.
     * @return string
     */
    public static function get_participant_url($participant_id)
    {
        $participant_id = intval($participant_id);

        if (empty(get_option('permalink_structure'))) {
            return add_query_arg(
                'vep_participant_id',
                $participant_id,
                home_url('/')
            );
        }

        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $default_slug = (is_string($locale) && strpos(strtolower($locale), 'da') === 0) ? 'deltager' : 'participant';
        $slug = (string) apply_filters('vep_participant_slug', $default_slug, $locale, $participant_id);

        if ('' === trim($slug)) {
            $slug = $default_slug;
        }

        return home_url(user_trailingslashit('vep/' . $slug . '/' . $participant_id));
    }
}
