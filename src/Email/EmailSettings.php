<?php
/**
 * SMTP2GO email settings accessor.
 *
 * Wraps the single 'vep_email_settings' option with typed getters
 * so no other class calls get_option() directly for email config.
 *
 * @package VEP
 * @subpackage Email
 */

namespace VolunteerExchangePlatform\Email;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmailSettings {
    const OPTION_KEY = 'vep_email_settings';

    /**
     * Retrieve full settings array from DB, merged with defaults.
     *
     * @return array
     */
    public static function get_all() {
        $saved    = get_option( self::OPTION_KEY, array() );
        $defaults = array(
            'api_key'                => '',
            'sender_name'            => '',
            'sender_email'           => '',
            'info_email_recipient'   => '',
            'max_participants_per_organization' => 3,
            'log_retention_days'     => 365,
            'template_profiles'      => array(),
        );

        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        $merged = array_merge( $defaults, $saved );

        // Ensure template_profiles is always an array.
        if ( ! is_array( $merged['template_profiles'] ) ) {
            $merged['template_profiles'] = array();
        }

        return $merged;
    }

    /**
     * API key for SMTP2GO.
     *
     * @return string
     */
    public static function api_key() {
        $settings = self::get_all();
        return (string) $settings['api_key'];
    }

    /**
     * Sender display name.
     *
     * @return string
     */
    public static function sender_name() {
        $settings = self::get_all();
        return (string) $settings['sender_name'];
    }

    /**
     * Sender email address.
     *
     * @return string
     */
    public static function sender_email() {
        $settings = self::get_all();
        return (string) $settings['sender_email'];
    }

    /**
     * Number of days to keep email log records. 0 = never delete.
     *
     * @return int
     */
    public static function log_retention_days() {
        $settings = self::get_all();
        return max( 0, (int) $settings['log_retention_days'] );
    }

    /**
     * Info emails recipient address.
     *
     * @return string
     */
    public static function info_email_recipient() {
        $settings = self::get_all();
        return (string) $settings['info_email_recipient'];
    }

    /**
     * Maximum participants allowed per organization.
     *
     * @return int
     */
    public static function max_participants_per_organization() {
        $settings = self::get_all();
        return max( 1, (int) $settings['max_participants_per_organization'] );
    }

    /**
     * Build sender string "Name <email>" if both set, else just email.
     *
     * @return string
     */
    public static function sender() {
        $name  = self::sender_name();
        $email = self::sender_email();

        if ( '' === $email ) {
            return '';
        }

        return '' !== $name ? $name . ' <' . $email . '>' : $email;
    }

    /**
     * All template profiles as an array.
     *
     * Each profile:
     *   key, label, template_id, default_subject, allowed_data_keys[]
     *
     * @return array
     */
    public static function template_profiles() {
        $settings = self::get_all();
        return $settings['template_profiles'];
    }

    /**
     * Get a single template profile by its key.
     *
     * @param string $key Profile key.
     * @return array|null
     */
    public static function get_profile( $key ) {
        foreach ( self::template_profiles() as $profile ) {
            if ( isset( $profile['key'] ) && $profile['key'] === (string) $key ) {
                return $profile;
            }
        }
        return null;
    }

    /**
     * Persist sanitized settings to DB.
     *
     * @param array $raw Raw POST input.
     * @return void
     */
    public static function save( array $raw ) {
        // API key: keep existing if blank is submitted (password field left empty).
        $existing = self::get_all();
        $submitted_key = isset( $raw['api_key'] ) ? sanitize_text_field( $raw['api_key'] ) : '';
        $api_key = '' !== $submitted_key ? $submitted_key : $existing['api_key'];

        $retention_raw = isset( $raw['log_retention_days'] ) ? (int) $raw['log_retention_days'] : 365;
        $log_retention_days = max( 0, $retention_raw );

        $settings = array(
            'api_key'              => $api_key,
            'sender_name'          => isset( $raw['sender_name'] ) ? sanitize_text_field( $raw['sender_name'] ) : '',
            'sender_email'         => isset( $raw['sender_email'] ) ? sanitize_email( $raw['sender_email'] ) : '',
            'info_email_recipient' => isset( $raw['info_email_recipient'] ) ? sanitize_email( $raw['info_email_recipient'] ) : '',
            'max_participants_per_organization' => isset( $raw['max_participants_per_organization'] ) ? max( 1, (int) $raw['max_participants_per_organization'] ) : 3,
            'log_retention_days'   => $log_retention_days,
            'template_profiles'    => array(),
        );

        // Template profiles come in as parallel arrays.
        $keys        = isset( $raw['profile_key'] ) && is_array( $raw['profile_key'] ) ? $raw['profile_key'] : array();
        $labels      = isset( $raw['profile_label'] ) && is_array( $raw['profile_label'] ) ? $raw['profile_label'] : array();
        $tpl_ids     = isset( $raw['profile_template_id'] ) && is_array( $raw['profile_template_id'] ) ? $raw['profile_template_id'] : array();
        $subjects    = isset( $raw['profile_subject'] ) && is_array( $raw['profile_subject'] ) ? $raw['profile_subject'] : array();
        $data_keys   = isset( $raw['profile_data_keys'] ) && is_array( $raw['profile_data_keys'] ) ? $raw['profile_data_keys'] : array();
        $html_bodies = isset( $raw['profile_html_body'] ) && is_array( $raw['profile_html_body'] ) ? $raw['profile_html_body'] : array();
        $text_bodies = isset( $raw['profile_text_body'] ) && is_array( $raw['profile_text_body'] ) ? $raw['profile_text_body'] : array();

        $seen_keys = array();
        foreach ( $keys as $i => $raw_key ) {
            $profile_key = sanitize_key( $raw_key );
            if ( '' === $profile_key || in_array( $profile_key, $seen_keys, true ) ) {
                continue;
            }
            $seen_keys[] = $profile_key;

            $allowed = array();
            $data_keys_raw = isset( $data_keys[ $i ] ) ? $data_keys[ $i ] : '';
            foreach ( preg_split( '/[\r\n,]+/', $data_keys_raw ) as $dk ) {
                $dk = sanitize_key( trim( $dk ) );
                if ( '' !== $dk ) {
                    $allowed[] = $dk;
                }
            }

            $settings['template_profiles'][] = array(
                'key'             => $profile_key,
                'label'           => isset( $labels[ $i ] ) ? sanitize_text_field( $labels[ $i ] ) : $profile_key,
                'template_id'     => isset( $tpl_ids[ $i ] ) ? sanitize_text_field( $tpl_ids[ $i ] ) : '',
                'default_subject' => isset( $subjects[ $i ] ) ? sanitize_text_field( $subjects[ $i ] ) : '',
                'allowed_data_keys' => $allowed,
                'default_html_body' => isset( $html_bodies[ $i ] ) ? wp_kses_post( $html_bodies[ $i ] ) : '',
                'default_text_body' => isset( $text_bodies[ $i ] ) ? sanitize_textarea_field( $text_bodies[ $i ] ) : '',
            );
        }

        update_option( self::OPTION_KEY, $settings );
    }
}
