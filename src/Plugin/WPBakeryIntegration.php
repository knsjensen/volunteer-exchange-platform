<?php
/**
 * WPBakery integration for plugin shortcodes.
 *
 * @package VEP
 * @subpackage Plugin
 */

namespace VolunteerExchangePlatform\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPBakeryIntegration {
    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'vc_before_init', array( $this, 'register_elements' ) );
    }

    /**
     * Register plugin shortcodes in WPBakery.
     *
     * @return void
     */
    public function register_elements() {
        if ( ! function_exists( 'vc_map' ) ) {
            return;
        }

        $category = __( 'Frivilligbørs', 'volunteer-exchange-platform' );

        call_user_func( 'vc_map',
            array(
                'name' => __( 'Tilmeldingsformular', 'volunteer-exchange-platform' ),
                'base' => 'vep_registration',
                'icon' => 'dashicons dashicons-feedback',
                'category' => $category,
                'description' => __( 'Volunteer registration form.', 'volunteer-exchange-platform' ),
                'params' => array(
                    array(
                        'type' => 'dropdown',
                        'heading' => __( 'Form Type', 'volunteer-exchange-platform' ),
                        'param_name' => 'form_type',
                        'value' => array(
                            __( 'Simple', 'volunteer-exchange-platform' ) => 'simple',
                            __( 'Multistep', 'volunteer-exchange-platform' ) => 'multistep',
                        ),
                        'std' => 'simple',
                    ),
                ),
            )
        );

        call_user_func( 'vc_map',
            array(
                'name' => __( 'Deltager grid', 'volunteer-exchange-platform' ),
                'base' => 'vep_participants_grid',
                'icon' => 'dashicons dashicons-screenoptions',
                'category' => $category,
                'description' => __( 'Grid of approved participants.', 'volunteer-exchange-platform' ),
                'params' => array(),
            )
        );

        call_user_func( 'vc_map',
            array(
                'name' => __( 'Participants table', 'volunteer-exchange-platform' ),
                'base' => 'vep_participants_table',
                'icon' => 'dashicons dashicons-editor-table',
                'category' => $category,
                'description' => __( 'Simple table of participants sorted by participant number.', 'volunteer-exchange-platform' ),
                'params' => array(
                    array(
                        'type' => 'checkbox',
                        'heading' => __( 'Show details button', 'volunteer-exchange-platform' ),
                        'param_name' => 'show_button',
                        'value' => array(
                            __( 'Show button linking to participant details page.', 'volunteer-exchange-platform' ) => 'yes',
                        ),
                    ),
                ),
            )
        );

        call_user_func( 'vc_map',
            array(
                'name' => __( 'Opret børsaftale formular', 'volunteer-exchange-platform' ),
                'base' => 'vep_agreement_form',
                'icon' => 'dashicons dashicons-feedback',
                'category' => $category,
                'description' => __( 'Create agreements between participants.', 'volunteer-exchange-platform' ),
                'params' => array(),
            )
        );

        call_user_func( 'vc_map',
            array(
                'name' => __( 'Børsaftale tabel', 'volunteer-exchange-platform' ),
                'base' => 'vep_agreements_list',
                'icon' => 'dashicons dashicons-editor-table',
                'category' => $category,
                'description' => __( 'List of agreements for active event.', 'volunteer-exchange-platform' ),
                'params' => array(),
            )
        );

        call_user_func( 'vc_map',
            array(
                'name' => __( 'Opdater deltager formular', 'volunteer-exchange-platform' ),
                'base' => 'vep_update_participant',
                'icon' => 'dashicons dashicons-feedback',
                'category' => $category,
                'description' => __( 'Allow participants to update registration.', 'volunteer-exchange-platform' ),
                'params' => array(),
            )
        );
    }
}
