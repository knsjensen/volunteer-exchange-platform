<?php
/**
 * Elementor integration for plugin shortcodes.
 *
 * @package VEP
 * @subpackage Plugin
 */

namespace VolunteerExchangePlatform\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ElementorIntegration {
    /**
     * Whether widgets have already been registered.
     *
     * @var bool
     */
    private $registered = false;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
        add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
        add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widgets' ) );
    }

    /**
     * Register the plugin category in Elementor.
     *
     * @param object $elements_manager Elementor elements manager.
     * @return void
     */
    public function register_category( $elements_manager ) {
        if ( ! is_object( $elements_manager ) || ! method_exists( $elements_manager, 'add_category' ) ) {
            return;
        }

        $elements_manager->add_category(
            'vep-shortcodes',
            array(
                'title' => __( 'Volunteer Exchange', 'volunteer-exchange-platform' ),
                'icon'  => 'fa fa-plug',
            )
        );
    }

    /**
     * Register plugin widgets in Elementor.
     *
     * @param object|null $widgets_manager Elementor widgets manager.
     * @return void
     */
    public function register_widgets( $widgets_manager = null ) {
        if ( $this->registered || ! class_exists( '\\Elementor\\Widget_Base' ) ) {
            return;
        }

        $manager = $widgets_manager;

        if ( ! is_object( $manager ) && class_exists( '\\Elementor\\Plugin' ) ) {
            $plugin = \Elementor\Plugin::instance();
            if ( $plugin && isset( $plugin->widgets_manager ) ) {
                $manager = $plugin->widgets_manager;
            }
        }

        if ( ! is_object( $manager ) ) {
            return;
        }

        require_once VEP_PLUGIN_DIR . 'src/Plugin/ElementorShortcodeWidgets.inc';

        $widget_classes = array(
            __NAMESPACE__ . '\\ElementorRegistrationWidget',
            __NAMESPACE__ . '\\ElementorParticipantsGridWidget',
            __NAMESPACE__ . '\\ElementorParticipantsTableWidget',
            __NAMESPACE__ . '\\ElementorAgreementFormWidget',
            __NAMESPACE__ . '\\ElementorAgreementsListWidget',
            __NAMESPACE__ . '\\ElementorEventCountdownWidget',
        );

        foreach ( $widget_classes as $widget_class ) {
            if ( ! class_exists( $widget_class ) ) {
                continue;
            }

            $widget = new $widget_class();

            if ( method_exists( $manager, 'register' ) ) {
                $manager->register( $widget );
            } elseif ( method_exists( $manager, 'register_widget_type' ) ) {
                $manager->register_widget_type( $widget );
            }
        }

        $this->registered = true;
    }
}