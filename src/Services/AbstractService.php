<?php
/**
 * Abstract service
 *
 * @package VEP
 * @subpackage Services
 */

namespace VolunteerExchangePlatform\Services;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class AbstractService {
    protected function is_valid_id( $value ) {
        return is_numeric( $value ) && (int) $value > 0;
    }

    protected function sanitize_icon_value( $icon ) {
        $icon = trim( (string) $icon );
        if ( '' === $icon ) {
            return '';
        }

        if ( ! preg_match( '/^(?:(s|r|b):)?(fa-)?[a-z0-9-]+$/i', $icon ) ) {
            return '';
        }

        return strtolower( $icon );
    }

    protected function run_guarded( $callback, $fallback = null ) {
        try {
            return $callback();
        } catch ( \Throwable $throwable ) {
            do_action( 'volunteer_exchange_platform_service_error', $throwable->getMessage(), $throwable );
            return $fallback;
        }
    }
}
