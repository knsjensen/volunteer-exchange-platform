<?php
/**
 * Runtime-loaded Elementor widgets for plugin shortcodes.
 *
 * Loaded only when Elementor is active.
 *
 * @package VEP
 * @subpackage Plugin
 */

namespace VolunteerExchangePlatform\Plugin;

if ( ! defined( 'ABSPATH' ) || ! class_exists( '\\Elementor\\Widget_Base' ) ) {
    return;
}

trait ElementorShortcodeRenderTrait {
    /**
     * Render a shortcode with attributes.
     *
     * @param string $tag Shortcode tag.
     * @param array  $attributes Shortcode attributes.
     * @return void
     */
    protected function render_vep_shortcode( $tag, array $attributes = array() ) {
        $parts = array();

        foreach ( $attributes as $attribute => $value ) {
            if ( '' === $value || null === $value ) {
                continue;
            }

            $parts[] = sprintf(
                '%s="%s"',
                sanitize_key( $attribute ),
                esc_attr( (string) $value )
            );
        }

        echo do_shortcode(
            '[' . $tag . ( empty( $parts ) ? '' : ' ' . implode( ' ', $parts ) ) . ']'
        );
    }
}

abstract class ElementorShortcodeWidgetBase extends \Elementor\Widget_Base {
    /**
     * Compatibility wrapper for older Elementor versions.
     *
     * @return void
     */
    protected function _register_controls() {
        $this->register_controls();
    }

    /**
     * Widget category slug.
     *
     * @return array
     */
    public function get_categories() {
        return array( 'vep-shortcodes' );
    }
}

class ElementorRegistrationWidget extends ElementorShortcodeWidgetBase {
    use ElementorShortcodeRenderTrait;

    public function get_name() {
        return 'vep_registration';
    }

    public function get_title() {
        return __( 'Registration Form', 'volunteer-exchange-platform' );
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    protected function register_controls() {
        $this->start_controls_section(
            'vep_shortcode_settings',
            array(
                'label' => __( 'Settings', 'volunteer-exchange-platform' ),
            )
        );

        $this->add_control(
            'form_type',
            array(
                'label'   => __( 'Form Type', 'volunteer-exchange-platform' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'simple',
                'options' => array(
                    'simple'    => __( 'Simple', 'volunteer-exchange-platform' ),
                    'multistep' => __( 'Multistep', 'volunteer-exchange-platform' ),
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->render_vep_shortcode(
            'vep_registration',
            array(
                'form_type' => isset( $settings['form_type'] ) ? $settings['form_type'] : 'simple',
            )
        );
    }
}

class ElementorParticipantsGridWidget extends ElementorShortcodeWidgetBase {
    use ElementorShortcodeRenderTrait;

    public function get_name() {
        return 'vep_participants_grid';
    }

    public function get_title() {
        return __( 'Participants Grid', 'volunteer-exchange-platform' );
    }

    public function get_icon() {
        return 'eicon-gallery-grid';
    }

    protected function render() {
        $this->render_vep_shortcode( 'vep_participants_grid' );
    }
}

class ElementorParticipantsTableWidget extends ElementorShortcodeWidgetBase {
    use ElementorShortcodeRenderTrait;

    public function get_name() {
        return 'vep_participants_table';
    }

    public function get_title() {
        return __( 'Participants Table', 'volunteer-exchange-platform' );
    }

    public function get_icon() {
        return 'eicon-table';
    }

    protected function register_controls() {
        $this->start_controls_section(
            'vep_shortcode_settings',
            array(
                'label' => __( 'Settings', 'volunteer-exchange-platform' ),
            )
        );

        $this->add_control(
            'show_button',
            array(
                'label'        => __( 'Show details button', 'volunteer-exchange-platform' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'volunteer-exchange-platform' ),
                'label_off'    => __( 'No', 'volunteer-exchange-platform' ),
                'return_value' => 'yes',
                'default'      => 'no',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->render_vep_shortcode(
            'vep_participants_table',
            array(
                'show_button' => isset( $settings['show_button'] ) ? $settings['show_button'] : 'no',
            )
        );
    }
}

class ElementorAgreementFormWidget extends ElementorShortcodeWidgetBase {
    use ElementorShortcodeRenderTrait;

    public function get_name() {
        return 'vep_agreement_form';
    }

    public function get_title() {
        return __( 'Agreement Form', 'volunteer-exchange-platform' );
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    protected function render() {
        $this->render_vep_shortcode( 'vep_agreement_form' );
    }
}

class ElementorAgreementsListWidget extends ElementorShortcodeWidgetBase {
    use ElementorShortcodeRenderTrait;

    public function get_name() {
        return 'vep_agreements_list';
    }

    public function get_title() {
        return __( 'Agreements Table', 'volunteer-exchange-platform' );
    }

    public function get_icon() {
        return 'eicon-post-list';
    }

    protected function render() {
        $this->render_vep_shortcode( 'vep_agreements_list' );
    }
}

class ElementorEventCountdownWidget extends ElementorShortcodeWidgetBase {
    use ElementorShortcodeRenderTrait;

    public function get_name() {
        return 'vep_event_countdown';
    }

    public function get_title() {
        return __( 'Event Countdown', 'volunteer-exchange-platform' );
    }

    public function get_icon() {
        return 'eicon-countdown';
    }

    protected function register_controls() {
        $this->start_controls_section(
            'vep_shortcode_settings',
            array(
                'label' => __( 'Settings', 'volunteer-exchange-platform' ),
            )
        );

        $this->add_control(
            'unit_mode',
            array(
                'label'   => __( 'Countdown units', 'volunteer-exchange-platform' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'months',
                'options' => array(
                    'months' => __( 'Months + days + hours + minutes + seconds', 'volunteer-exchange-platform' ),
                    'days'   => __( 'Days + hours + minutes + seconds', 'volunteer-exchange-platform' ),
                ),
            )
        );

        $this->add_control(
            'in_progress_text',
            array(
                'label' => __( 'In Progress Text', 'volunteer-exchange-platform' ),
                'type'  => \Elementor\Controls_Manager::TEXT,
            )
        );

        $this->add_control(
            'event_over_text',
            array(
                'label' => __( 'Event Over Text', 'volunteer-exchange-platform' ),
                'type'  => \Elementor\Controls_Manager::TEXT,
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $this->render_vep_shortcode(
            'vep_event_countdown',
            array(
                'unit_mode'        => isset( $settings['unit_mode'] ) ? $settings['unit_mode'] : 'months',
                'in_progress_text' => isset( $settings['in_progress_text'] ) ? $settings['in_progress_text'] : '',
                'event_over_text'  => isset( $settings['event_over_text'] ) ? $settings['event_over_text'] : '',
            )
        );
    }
}