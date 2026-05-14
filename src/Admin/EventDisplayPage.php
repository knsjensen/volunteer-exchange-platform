<?php
/**
 * Event display admin page
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\EventService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Event display admin page
 * 
 * Handles the admin interface for event display settings including
 * event end time configuration and fullscreen event display launch.
 * 
 * @package VolunteerExchangePlatform\Admin
 */
class EventDisplayPage {
    /**
     * @var EventService
     */
    private $event_service;
    
    /**
     * Constructor.
     *
     * @param EventService|null $event_service Event service instance.
     * @return void
     */
    public function __construct( ?EventService $event_service = null ) {
        $this->event_service = $event_service ?: new EventService();
        add_action('admin_init', array($this, 'handle_form_submission'));
    }

    /**
     * Sanitize a hex color and return fallback when invalid.
     *
     * @param string $value Raw color value.
     * @param string $fallback Fallback color.
     * @return string
     */
    private function sanitize_hex_color_with_fallback( $value, $fallback ) {
        $color = sanitize_hex_color( (string) $value );

        return $color ? $color : $fallback;
    }

    /**
     * Normalize percentage value to 0-100 integer range.
     *
     * @param mixed $value Raw percentage value.
     * @param int   $fallback Fallback percentage.
     * @return int
     */
    private function sanitize_percentage( $value, $fallback ) {
        $percent = is_numeric( $value ) ? (int) $value : (int) $fallback;

        if ( $percent < 0 ) {
            return 0;
        }

        if ( $percent > 100 ) {
            return 100;
        }

        return $percent;
    }

    /**
     * Normalize gradient angle to 0-360 integer range.
     *
     * @param mixed $value Raw angle value.
     * @param int   $fallback Fallback degrees.
     * @return int
     */
    private function sanitize_degrees( $value, $fallback ) {
        $degrees = is_numeric( $value ) ? (int) $value : (int) $fallback;

        if ( $degrees < 0 ) {
            return 0;
        }

        if ( $degrees > 360 ) {
            return 360;
        }

        return $degrees;
    }

    /**
     * Normalize datetime input into MySQL datetime format.
     *
     * Accepts datetime-local values (with T) and SQL-like datetimes.
     *
     * @param string $value Datetime input value.
     * @return string|false
     */
    private function normalize_datetime_value( $value ) {
        return $this->event_service->normalize_datetime_value( $value );
    }

    /**
     * Convert stored MySQL datetime to datetime-local input format.
     *
     * @param string $value Stored datetime value.
     * @return string
     */
    private function to_datetime_local_value( $value ) {
        return $this->event_service->to_datetime_local_value( $value );
    }
    
    /**
     * Handle form submission for display settings
     *
     * @return void
     */
    public function handle_form_submission() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'vep-event-display' !== $page ) {
            return;
        }
        
        // Handle settings reset
        if ( isset( $_POST['vep_reset_display_settings'] ) && check_admin_referer( 'vep_display_settings', 'vep_display_nonce' ) ) {
            $options_to_delete = array(
                'vep_display_title',
                'vep_display_mode',
                'vep_display_background_type',
                'vep_display_background_solid_color',
                'vep_display_background_gradient_color_1',
                'vep_display_background_gradient_color_2',
                'vep_display_background_gradient_color_3',
                'vep_display_background_gradient_stop_1',
                'vep_display_background_gradient_stop_2',
                'vep_display_background_gradient_stop_3',
                'vep_display_background_gradient_angle',
                'vep_display_text_color',
                'vep_display_auto_switch_stats',
                'vep_display_closing_text',
                'vep_display_time_up_text',
                'vep_display_hide_buttons',
                'vep_display_show_competitions',
            );
            foreach ( $options_to_delete as $option ) {
                delete_option( $option );
            }
            wp_safe_redirect( add_query_arg( array(
                'page'    => 'vep-event-display',
                'message' => 'settings_reset',
            ), admin_url( 'admin.php' ) ) );
            exit;
        }

        // Handle settings save
        if ( isset( $_POST['vep_save_display_settings'] ) && check_admin_referer( 'vep_display_settings', 'vep_display_nonce' ) ) {
            $event_end_datetime_raw = '';
            if ( isset( $_POST['event_end_datetime'] ) ) {
                $event_end_datetime_raw = sanitize_text_field( wp_unslash( $_POST['event_end_datetime'] ) );
            } elseif ( isset( $_POST['countdown_datetime'] ) ) {
                // Backward compatibility with previous field name.
                $event_end_datetime_raw = sanitize_text_field( wp_unslash( $_POST['countdown_datetime'] ) );
            }

            $event_end_datetime = '';
            if ( '' !== $event_end_datetime_raw ) {
                $normalized_event_end = $this->normalize_datetime_value( $event_end_datetime_raw );
                if ( false !== $normalized_event_end ) {
                    $event_end_datetime = $normalized_event_end;
                }
            }

            // Keep a single source of truth: display end time equals active event end_date.
            if ( '' !== $event_end_datetime ) {
                $active_event = $this->event_service->get_active_event();
                if ( $active_event && isset( $active_event->id ) ) {
                    $this->event_service->update_event(
                        (int) $active_event->id,
                        array(
                            'name'        => isset( $active_event->name ) ? (string) $active_event->name : '',
                            'description' => isset( $active_event->description ) ? (string) $active_event->description : '',
                            'start_date'  => isset( $active_event->start_date ) ? (string) $active_event->start_date : '',
                            'end_date'    => $event_end_datetime,
                        )
                    );
                }
            }
            $display_title = isset( $_POST['display_title'] ) ? sanitize_text_field( wp_unslash( $_POST['display_title'] ) ) : '';
            $display_mode = isset( $_POST['display_mode'] ) ? sanitize_key( wp_unslash( $_POST['display_mode'] ) ) : 'leaderboard';
            $background_type = isset( $_POST['vep_background_type'] ) ? sanitize_key( wp_unslash( $_POST['vep_background_type'] ) ) : 'gradient';

            $solid_color = isset( $_POST['vep_background_solid_color'] )
                ? $this->sanitize_hex_color_with_fallback( wp_unslash( $_POST['vep_background_solid_color'] ), '#1e3c72' )
                : '#1e3c72';

            $gradient_color_1 = isset( $_POST['vep_background_gradient_color_1'] )
                ? $this->sanitize_hex_color_with_fallback( wp_unslash( $_POST['vep_background_gradient_color_1'] ), '#db870f' )
                : '#db870f';
            $gradient_color_2 = isset( $_POST['vep_background_gradient_color_2'] )
                ? $this->sanitize_hex_color_with_fallback( wp_unslash( $_POST['vep_background_gradient_color_2'] ), '#db870f' )
                : '#db870f';
            $gradient_color_3 = isset( $_POST['vep_background_gradient_color_3'] )
                ? $this->sanitize_hex_color_with_fallback( wp_unslash( $_POST['vep_background_gradient_color_3'] ), '#b014db' )
                : '#b014db';

            $gradient_stop_1 = isset( $_POST['vep_background_gradient_stop_1'] )
                ? $this->sanitize_percentage( wp_unslash( $_POST['vep_background_gradient_stop_1'] ), 10 )
                : 10;
            $gradient_stop_2 = isset( $_POST['vep_background_gradient_stop_2'] )
                ? $this->sanitize_percentage( wp_unslash( $_POST['vep_background_gradient_stop_2'] ), 47 )
                : 47;
            $gradient_stop_3 = isset( $_POST['vep_background_gradient_stop_3'] )
                ? $this->sanitize_percentage( wp_unslash( $_POST['vep_background_gradient_stop_3'] ), 85 )
                : 85;
            $gradient_angle = isset( $_POST['vep_background_gradient_angle'] )
                ? $this->sanitize_degrees( wp_unslash( $_POST['vep_background_gradient_angle'] ), 128 )
                : 128;
            $display_text_color = isset( $_POST['vep_display_text_color'] )
                ? $this->sanitize_hex_color_with_fallback( wp_unslash( $_POST['vep_display_text_color'] ), '#ffffff' )
                : '#ffffff';

            if ( ! in_array( $display_mode, array( 'leaderboard', 'recent_agreements', 'none' ), true ) ) {
                $display_mode = 'leaderboard';
            }

            if ( ! in_array( $background_type, array( 'solid', 'gradient' ), true ) ) {
                $background_type = 'gradient';
            }

            // Legacy option no longer used; countdown is always active event end_date.
            delete_option( 'vep_display_countdown_datetime' );
            update_option( 'vep_display_title', $display_title );
            update_option( 'vep_display_mode', $display_mode );
            update_option( 'vep_display_background_type', $background_type );
            update_option( 'vep_display_background_solid_color', $solid_color );
            update_option( 'vep_display_background_gradient_color_1', $gradient_color_1 );
            update_option( 'vep_display_background_gradient_color_2', $gradient_color_2 );
            update_option( 'vep_display_background_gradient_color_3', $gradient_color_3 );
            update_option( 'vep_display_background_gradient_stop_1', $gradient_stop_1 );
            update_option( 'vep_display_background_gradient_stop_2', $gradient_stop_2 );
            update_option( 'vep_display_background_gradient_stop_3', $gradient_stop_3 );
            update_option( 'vep_display_background_gradient_angle', $gradient_angle );
            update_option( 'vep_display_text_color', $display_text_color );
            $auto_switch_stats = isset( $_POST['auto_switch_to_stats'] ) ? 1 : 0;
            update_option( 'vep_display_auto_switch_stats', $auto_switch_stats );
            $closing_text = isset( $_POST['vep_display_closing_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['vep_display_closing_text'] ) ) : '';
            update_option( 'vep_display_closing_text', $closing_text );
            $time_up_text = isset( $_POST['vep_display_time_up_text'] ) ? sanitize_text_field( wp_unslash( $_POST['vep_display_time_up_text'] ) ) : '';
            update_option( 'vep_display_time_up_text', $time_up_text );
            $hide_buttons = isset( $_POST['vep_display_hide_buttons'] ) ? 1 : 0;
            update_option( 'vep_display_hide_buttons', $hide_buttons );
            $show_competitions = isset( $_POST['vep_display_show_competitions'] ) ? 1 : 0;
            update_option( 'vep_display_show_competitions', $show_competitions );
            
            wp_safe_redirect( add_query_arg( array(
                'page' => 'vep-event-display',
                'message' => 'settings_saved'
            ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }
    
    /**
     * Render the page
     *
     * Displays event display settings form and fullscreen display launcher
     *
     * @return void
     */
    public function render() {
        // Event end time uses active event end_date as the single source of truth.
        $event_end_datetime = '';
        $event_end_datetime_input = '';
        $display_title = get_option('vep_display_title', '');
        $display_mode = get_option('vep_display_mode', 'leaderboard');
        if ( ! in_array( $display_mode, array( 'leaderboard', 'recent_agreements', 'none' ), true ) ) {
            $display_mode = 'leaderboard';
        }

        $background_type = get_option('vep_display_background_type', 'gradient');
        if ( ! in_array( $background_type, array( 'solid', 'gradient' ), true ) ) {
            $background_type = 'gradient';
        }

        $solid_color = $this->sanitize_hex_color_with_fallback( get_option('vep_display_background_solid_color', '#1e3c72'), '#1e3c72' );
        $gradient_color_1 = $this->sanitize_hex_color_with_fallback( get_option('vep_display_background_gradient_color_1', '#db870f'), '#db870f' );
        $gradient_color_2 = $this->sanitize_hex_color_with_fallback( get_option('vep_display_background_gradient_color_2', '#db870f'), '#db870f' );
        $gradient_color_3 = $this->sanitize_hex_color_with_fallback( get_option('vep_display_background_gradient_color_3', '#b014db'), '#b014db' );
        $gradient_stop_1 = $this->sanitize_percentage( get_option('vep_display_background_gradient_stop_1', 10), 10 );
        $gradient_stop_2 = $this->sanitize_percentage( get_option('vep_display_background_gradient_stop_2', 47), 47 );
        $gradient_stop_3 = $this->sanitize_percentage( get_option('vep_display_background_gradient_stop_3', 85), 85 );
        $gradient_angle = $this->sanitize_degrees( get_option('vep_display_background_gradient_angle', 128), 128 );
        $display_text_color = $this->sanitize_hex_color_with_fallback( get_option('vep_display_text_color', '#ffffff'), '#ffffff' );
        $auto_switch_stats  = (bool) get_option( 'vep_display_auto_switch_stats', 0 );
        $closing_text       = (string) get_option( 'vep_display_closing_text', '' );
        $closing_text       = $closing_text !== '' ? $closing_text : __( 'Tak for denne gang', 'volunteer-exchange-platform' );
        $time_up_text       = (string) get_option( 'vep_display_time_up_text', '' );
        $time_up_text       = $time_up_text !== '' ? $time_up_text : __( 'Tiden er gået', 'volunteer-exchange-platform' );
        $hide_buttons       = (bool) get_option( 'vep_display_hide_buttons', 1 );
        $show_competitions  = (bool) get_option( 'vep_display_show_competitions', 1 );

        if ( 'recent_agreements' === $display_mode ) {
            $right_panel_title = __( 'Latest Agreements', 'volunteer-exchange-platform' );
        } else {
            $right_panel_title = __( 'Top Participants', 'volunteer-exchange-platform' );
        }
        $message = filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        // Get active event and surface its end_date in the event end time field.
        $active_event = $this->event_service->get_active_event();
        if ( $active_event && isset( $active_event->end_date ) ) {
            $normalized_event_end = $this->normalize_datetime_value( (string) $active_event->end_date );
            if ( false !== $normalized_event_end ) {
                $event_end_datetime = $normalized_event_end;
                $event_end_datetime_input = $this->to_datetime_local_value( $normalized_event_end );
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Event Display', 'volunteer-exchange-platform'); ?></h1>
            
            <?php if ( sanitize_key( (string) $message ) === 'settings_saved' ): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved successfully.', 'volunteer-exchange-platform'); ?></p>
                </div>
            <?php elseif ( sanitize_key( (string) $message ) === 'settings_reset' ): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings reset to defaults.', 'volunteer-exchange-platform'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ( ! $active_event ): ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e('No active event found. Please create and activate an event first.', 'volunteer-exchange-platform'); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><strong><?php esc_html_e('Active Event:', 'volunteer-exchange-platform'); ?></strong> <?php echo esc_html($active_event->name); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="vep-display-settings-container" style="max-width: 800px; margin-top: 20px;">
                <div class="card" id="vep-display-settings-card" style="padding: 20px;">
                    <h2 style="margin-top:0;"><?php esc_html_e('Display Settings', 'volunteer-exchange-platform'); ?></h2>
                    <div id="vep-display-settings-body" style="margin-top:16px;">
                    <form method="post" action="">
                        <?php wp_nonce_field('vep_display_settings', 'vep_display_nonce'); ?>

                        <p>
                            <button type="button"
                                    id="vep-toggle-advanced-settings"
                                    class="button"
                                    aria-expanded="false"
                                    data-label-show="<?php echo esc_attr__( 'Advanced', 'volunteer-exchange-platform' ); ?>"
                                    data-label-hide="<?php echo esc_attr__( 'Hide Advanced', 'volunteer-exchange-platform' ); ?>">
                                <?php esc_html_e( 'Advanced', 'volunteer-exchange-platform' ); ?>
                            </button>
                        </p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="display_title"><?php esc_html_e('Display Title', 'volunteer-exchange-platform'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="display_title" 
                                           name="display_title" 
                                           value="<?php echo esc_attr($display_title); ?>" 
                                           class="regular-text"
                                           placeholder="<?php esc_attr_e('e.g., Welcome to Our Event', 'volunteer-exchange-platform'); ?>">
                                    <p class="description">
                                        <?php esc_html_e('Enter the title to display in the fullscreen view.', 'volunteer-exchange-platform'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="event_end_datetime"><?php esc_html_e('Event End Time', 'volunteer-exchange-platform'); ?></label>
                                </th>
                                <td>
                                    <input type="datetime-local"
                                           id="event_end_datetime" 
                                           name="event_end_datetime" 
                                           value="<?php echo esc_attr($event_end_datetime_input); ?>"
                                           step="60"
                                           class="regular-text">
                                    <p class="description">
                                        <?php esc_html_e('Uses the active event end date and time.', 'volunteer-exchange-platform'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <?php esc_html_e( 'Auto-switch to statistics', 'volunteer-exchange-platform' ); ?>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="auto_switch_to_stats"
                                               id="auto_switch_to_stats"
                                               value="1"
                                               <?php checked( $auto_switch_stats, true ); ?>>
                                        <?php esc_html_e( 'Auto-switch to statistics', 'volunteer-exchange-platform' ); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'Automatically switch to the statistics view when the countdown expires.', 'volunteer-exchange-platform' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <?php esc_html_e( 'Vis konkurrencer', 'volunteer-exchange-platform' ); ?>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="vep_display_show_competitions" value="1" <?php checked( $show_competitions ); ?> />
                                        <?php esc_html_e( 'Vis konkurrenceknapper i visningerne', 'volunteer-exchange-platform' ); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <?php esc_html_e( 'Skjul alle knapper', 'volunteer-exchange-platform' ); ?>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="vep_display_hide_buttons" value="1" <?php checked( $hide_buttons ); ?> />
                                        <?php esc_html_e( 'Skjul alle navigationsknapper i bunden af visningerne', 'volunteer-exchange-platform' ); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <label for="vep_display_time_up_text"><?php esc_html_e( 'Tiden er gået tekst', 'volunteer-exchange-platform' ); ?></label>
                                </th>
                                <td>
                                    <textarea id="vep_display_time_up_text"
                                              name="vep_display_time_up_text"
                                              rows="3"
                                              class="regular-text"><?php echo esc_textarea( $time_up_text ); ?></textarea>
                                </td>
                            </tr>
                            <tr class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <label for="vep_display_closing_text"><?php esc_html_e( 'Afsluttende tekst', 'volunteer-exchange-platform' ); ?></label>
                                </th>
                                <td>
                                    <textarea id="vep_display_closing_text"
                                              name="vep_display_closing_text"
                                              rows="3"
                                              class="regular-text"><?php echo esc_textarea( $closing_text ); ?></textarea>
                                </td>
                            </tr>
                            <tr class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <label for="display_mode"><?php esc_html_e('Right Panel View', 'volunteer-exchange-platform'); ?></label>
                                </th>
                                <td>
                                    <select id="display_mode" name="display_mode">
                                        <option value="leaderboard" <?php selected( $display_mode, 'leaderboard' ); ?>>
                                            <?php esc_html_e('Top Participants', 'volunteer-exchange-platform'); ?>
                                        </option>
                                        <option value="recent_agreements" <?php selected( $display_mode, 'recent_agreements' ); ?>>
                                            <?php esc_html_e('Latest Agreements', 'volunteer-exchange-platform'); ?>
                                        </option>
                                        <option value="none" <?php selected( $display_mode, 'none' ); ?>>
                                            <?php esc_html_e('None', 'volunteer-exchange-platform'); ?>
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Choose what to show in the right side panel during fullscreen display.', 'volunteer-exchange-platform'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <label for="vep_background_type"><?php esc_html_e('Background Style', 'volunteer-exchange-platform'); ?></label>
                                </th>
                                <td>
                                    <div class="vep-background-type-controls">
                                        <select id="vep_background_type" name="vep_background_type">
                                            <option value="solid" <?php selected( $background_type, 'solid' ); ?>>
                                                <?php esc_html_e('Single Color', 'volunteer-exchange-platform'); ?>
                                            </option>
                                            <option value="gradient" <?php selected( $background_type, 'gradient' ); ?>>
                                                <?php esc_html_e('Gradient', 'volunteer-exchange-platform'); ?>
                                            </option>
                                        </select>
                                        <button type="button"
                                                id="vep-background-reset"
                                                class="button"
                                                data-default-type="gradient"
                                                data-default-solid-color="#1e3c72"
                                                data-default-gradient-color-1="#db870f"
                                                data-default-gradient-color-2="#db870f"
                                                data-default-gradient-color-3="#b014db"
                                                data-default-gradient-stop-1="10"
                                                data-default-gradient-stop-2="47"
                                                data-default-gradient-stop-3="85"
                                                data-default-gradient-angle="128"
                                                data-default-text-color="#ffffff">
                                            <?php esc_html_e('Reset Background', 'volunteer-exchange-platform'); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <label for="vep_display_text_color"><?php esc_html_e('Text Color', 'volunteer-exchange-platform'); ?></label>
                                </th>
                                <td>
                                    <input type="color"
                                           id="vep_display_text_color"
                                           name="vep_display_text_color"
                                           value="<?php echo esc_attr( $display_text_color ); ?>">
                                </td>
                            </tr>
                            <tr id="vep-background-solid-row" class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <label for="vep_background_solid_color"><?php esc_html_e('Background Color', 'volunteer-exchange-platform'); ?></label>
                                </th>
                                <td>
                                    <input type="color"
                                           id="vep_background_solid_color"
                                           name="vep_background_solid_color"
                                           value="<?php echo esc_attr( $solid_color ); ?>">
                                </td>
                            </tr>
                            <tr id="vep-background-gradient-row" class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <?php esc_html_e('Gradient Colors', 'volunteer-exchange-platform'); ?>
                                </th>
                                <td>
                                    <div id="vep-gradient-stops" class="vep-gradient-stops">
                                        <div class="vep-gradient-stop">
                                            <div class="vep-gradient-stop-header">
                                                <strong><?php esc_html_e('Color Stop 1', 'volunteer-exchange-platform'); ?></strong>
                                                <span class="vep-gradient-stop-actions">
                                                    <button type="button" class="button button-small vep-gradient-move-down"><?php esc_html_e('Move Down', 'volunteer-exchange-platform'); ?></button>
                                                </span>
                                            </div>
                                            <div class="vep-gradient-stop-controls">
                                                <input type="color"
                                                       name="vep_background_gradient_color_1"
                                                       class="vep-gradient-color"
                                                       value="<?php echo esc_attr( $gradient_color_1 ); ?>">
                                                <label>
                                                    <?php esc_html_e('Fill Percentage', 'volunteer-exchange-platform'); ?>
                                                    <input type="range"
                                                           name="vep_background_gradient_stop_1"
                                                           class="vep-gradient-stop-range"
                                                           min="0"
                                                           max="100"
                                                           value="<?php echo esc_attr( (string) $gradient_stop_1 ); ?>">
                                                    <span class="vep-gradient-stop-value"><?php echo esc_html( (string) $gradient_stop_1 ); ?>%</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="vep-gradient-stop">
                                            <div class="vep-gradient-stop-header">
                                                <strong><?php esc_html_e('Color Stop 2', 'volunteer-exchange-platform'); ?></strong>
                                                <span class="vep-gradient-stop-actions">
                                                    <button type="button" class="button button-small vep-gradient-move-up"><?php esc_html_e('Move Up', 'volunteer-exchange-platform'); ?></button>
                                                    <button type="button" class="button button-small vep-gradient-move-down"><?php esc_html_e('Move Down', 'volunteer-exchange-platform'); ?></button>
                                                </span>
                                            </div>
                                            <div class="vep-gradient-stop-controls">
                                                <input type="color"
                                                       name="vep_background_gradient_color_2"
                                                       class="vep-gradient-color"
                                                       value="<?php echo esc_attr( $gradient_color_2 ); ?>">
                                                <label>
                                                    <?php esc_html_e('Fill Percentage', 'volunteer-exchange-platform'); ?>
                                                    <input type="range"
                                                           name="vep_background_gradient_stop_2"
                                                           class="vep-gradient-stop-range"
                                                           min="0"
                                                           max="100"
                                                           value="<?php echo esc_attr( (string) $gradient_stop_2 ); ?>">
                                                    <span class="vep-gradient-stop-value"><?php echo esc_html( (string) $gradient_stop_2 ); ?>%</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="vep-gradient-stop">
                                            <div class="vep-gradient-stop-header">
                                                <strong><?php esc_html_e('Color Stop 3', 'volunteer-exchange-platform'); ?></strong>
                                                <span class="vep-gradient-stop-actions">
                                                    <button type="button" class="button button-small vep-gradient-move-up"><?php esc_html_e('Move Up', 'volunteer-exchange-platform'); ?></button>
                                                </span>
                                            </div>
                                            <div class="vep-gradient-stop-controls">
                                                <input type="color"
                                                       name="vep_background_gradient_color_3"
                                                       class="vep-gradient-color"
                                                       value="<?php echo esc_attr( $gradient_color_3 ); ?>">
                                                <label>
                                                    <?php esc_html_e('Fill Percentage', 'volunteer-exchange-platform'); ?>
                                                    <input type="range"
                                                           name="vep_background_gradient_stop_3"
                                                           class="vep-gradient-stop-range"
                                                           min="0"
                                                           max="100"
                                                           value="<?php echo esc_attr( (string) $gradient_stop_3 ); ?>">
                                                    <span class="vep-gradient-stop-value"><?php echo esc_html( (string) $gradient_stop_3 ); ?>%</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e('Use the move buttons to rearrange gradient colors without reselecting them.', 'volunteer-exchange-platform'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr id="vep-background-gradient-angle-row" class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <label for="vep_background_gradient_angle"><?php esc_html_e('Gradient Angle', 'volunteer-exchange-platform'); ?></label>
                                </th>
                                <td>
                                    <input type="range"
                                           id="vep_background_gradient_angle"
                                           name="vep_background_gradient_angle"
                                           min="0"
                                           max="360"
                                           value="<?php echo esc_attr( (string) $gradient_angle ); ?>">
                                    <span id="vep-gradient-angle-value" class="vep-gradient-stop-value"><?php echo esc_html( (string) $gradient_angle ); ?>deg</span>
                                </td>
                            </tr>
                            <tr class="vep-display-advanced-row" style="display: none;">
                                <th scope="row">
                                    <?php esc_html_e('Background Preview', 'volunteer-exchange-platform'); ?>
                                </th>
                                <td>
                                    <div id="vep-background-preview" class="vep-background-preview"></div>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="vep_save_display_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'volunteer-exchange-platform'); ?>">
                            <input type="submit" name="vep_reset_display_settings" class="button button-secondary" value="<?php esc_attr_e('Nulstil indstillinger', 'volunteer-exchange-platform'); ?>" onclick="return confirm('<?php esc_attr_e( 'Er du sikker på at du vil nulstille alle indstillinger til standard?', 'volunteer-exchange-platform' ); ?>')">
                        </p>
                    </form>
                    </div><!-- #vep-display-settings-body -->
                </div>
                
                <?php if ($active_event && !empty($event_end_datetime)): ?>
                    <div class="card" style="padding: 20px; margin-top: 20px;">
                        <h2><?php esc_html_e('Display Control', 'volunteer-exchange-platform'); ?></h2>
                        <p><?php esc_html_e('Click the button below to launch the fullscreen event display.', 'volunteer-exchange-platform'); ?></p>
                        
                        <button type="button" 
                                id="vep-start-event-display" 
                                class="button button-primary button-hero"
                                data-event-end="<?php echo esc_attr($event_end_datetime); ?>"
                                data-countdown="<?php echo esc_attr($event_end_datetime); ?>"
                                data-event-id="<?php echo esc_attr($active_event->id); ?>"
                                data-display-mode="<?php echo esc_attr($display_mode); ?>"
                                data-display-title="<?php echo esc_attr($display_title ? $display_title : $active_event->name); ?>"
                                data-background-type="<?php echo esc_attr($background_type); ?>"
                                data-background-solid-color="<?php echo esc_attr($solid_color); ?>"
                                data-background-gradient-color-1="<?php echo esc_attr($gradient_color_1); ?>"
                                data-background-gradient-color-2="<?php echo esc_attr($gradient_color_2); ?>"
                                data-background-gradient-color-3="<?php echo esc_attr($gradient_color_3); ?>"
                                data-background-gradient-stop-1="<?php echo esc_attr( (string) $gradient_stop_1 ); ?>"
                                data-background-gradient-stop-2="<?php echo esc_attr( (string) $gradient_stop_2 ); ?>"
                                data-background-gradient-stop-3="<?php echo esc_attr( (string) $gradient_stop_3 ); ?>"
                                data-background-gradient-angle="<?php echo esc_attr( (string) $gradient_angle ); ?>"
                                data-text-color="<?php echo esc_attr($display_text_color); ?>"
                                data-auto-switch-stats="<?php echo $auto_switch_stats ? '1' : '0'; ?>"
                                style="margin-top: 10px;">
                            <span class="dashicons dashicons-visibility" style="margin-top: 6px;"></span>
                            <?php esc_html_e('Start Event Display', 'volunteer-exchange-platform'); ?>
                        </button>
                        
                        <p class="description" style="margin-top: 10px;">
                            <?php esc_html_e('This will open a fullscreen display with countdown timer and live agreement count. Press ESC to exit fullscreen.', 'volunteer-exchange-platform'); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Visningsnavigation box — shown by JS when display is active -->
                <div id="vep-nav-box" class="card" style="padding: 20px; margin-top: 20px; display: none;">
                    <h2><?php esc_html_e( 'Visningsnavigation', 'volunteer-exchange-platform' ); ?></h2>
                    <p class="description" style="margin-bottom: 16px;">
                        <?php esc_html_e( 'Styr visningen fra denne side. Knapperne synkroniseres med det aktive display.', 'volunteer-exchange-platform' ); ?>
                    </p>

                    <!-- Countdown view buttons -->
                    <div id="vep-nav-countdown-actions" class="vep-nav-actions" style="display: none;">
                        <button type="button" class="button button-primary vep-nav-btn" data-nav-action="showStatistics">
                            <?php esc_html_e( 'Vis statistik', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" class="button button-primary vep-nav-btn vep-nav-btn-competitions" data-nav-action="showCompetitions">
                            <?php esc_html_e( 'Vis konkurrencer', 'volunteer-exchange-platform' ); ?>
                        </button>
                    </div>

                    <!-- Statistics view buttons -->
                    <div id="vep-nav-statistics-actions" class="vep-nav-actions" style="display: none;">
                        <button type="button" class="button button-primary vep-nav-btn vep-nav-btn-back-countdown" data-nav-action="showCountdown" style="display: none;">
                            <?php esc_html_e( 'Tilbage til nedtælling', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" class="button button-primary vep-nav-btn vep-nav-btn-competitions" data-nav-action="showCompetitions">
                            <?php esc_html_e( 'Vis konkurrencer', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" class="button button-primary vep-nav-btn vep-nav-btn-closing" data-nav-action="showClosing" style="display: none;">
                            <?php esc_html_e( 'Afslut', 'volunteer-exchange-platform' ); ?>
                        </button>
                    </div>

                    <!-- Competitions view buttons -->
                    <div id="vep-nav-competitions-actions" class="vep-nav-actions" style="display: none;">
                        <div id="vep-nav-competitions-list" class="vep-nav-competitions-list" style="display: none;"></div>
                        <button type="button" class="button button-primary vep-nav-btn" data-nav-action="showStatistics">
                            <?php esc_html_e( 'Vis statistik', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" class="button button-primary vep-nav-btn" data-nav-action="showWinner" data-winner-index="0">
                            <?php esc_html_e( 'Vis første vinder', 'volunteer-exchange-platform' ); ?>
                        </button>
                    </div>

                    <!-- Winner view buttons -->
                    <div id="vep-nav-winner-actions" class="vep-nav-actions" style="display: none;">
                        <button type="button" class="button button-primary vep-nav-btn" data-nav-action="showCompetitions">
                            <?php esc_html_e( 'Konkurrence oversigt', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" class="button button-primary vep-nav-btn" id="vep-nav-winner-prev" data-nav-action="showWinner" data-winner-index="0" style="display: none;">
                            <?php esc_html_e( 'Forrige vinder', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" class="button button-primary vep-nav-btn" id="vep-nav-winner-next" data-nav-action="showWinner" data-winner-index="0" style="display: none;">
                            <?php esc_html_e( 'Næste vinder', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" class="button button-primary vep-nav-btn" id="vep-nav-winner-finish" data-nav-action="showClosing" style="display: none;">
                            <?php esc_html_e( 'Afslut', 'volunteer-exchange-platform' ); ?>
                        </button>
                    </div>

                    <!-- Closing view buttons -->
                    <div id="vep-nav-closing-actions" class="vep-nav-actions" style="display: none;">
                        <button type="button" class="button button-primary vep-nav-btn vep-nav-btn-closing-to-competitions" data-nav-action="showCompetitions">
                            <?php esc_html_e( 'Tilbage til konkurrence oversigten', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" class="button button-primary vep-nav-btn vep-nav-btn-closing-to-statistics" data-nav-action="showStatistics" style="display: none;">
                            <?php esc_html_e( 'Tilbage til statistik', 'volunteer-exchange-platform' ); ?>
                        </button>
                    </div>

                    <p id="vep-nav-status" class="description" style="margin-top: 12px; font-style: italic;"></p>

                    <hr style="margin: 16px 0;">
                    <button type="button" id="vep-nav-reset" class="button button-secondary" style="color:#b32d2e;">
                        <?php esc_html_e( 'Nulstil navigationssystem', 'volunteer-exchange-platform' ); ?>
                    </button>
                    <p class="description" style="margin-top:6px; font-size:11px;">
                        <?php esc_html_e( 'Brug kun hvis navigationen er fastlåst. Sletter visningsdata i databasen.', 'volunteer-exchange-platform' ); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Fullscreen Display Modal -->
        <div id="vep-fullscreen-display" class="vep-fullscreen-display" style="display: none;">
            <button id="vep-close-display" class="vep-close-display" title="<?php esc_attr_e('Close Display (ESC)', 'volunteer-exchange-platform'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
            
            <canvas id="vep-fireworks-canvas" class="vep-fireworks-canvas"></canvas>
            
            <div class="vep-display-content">
                <!-- Header Area -->
                <div class="vep-display-header">
                    <h1 id="vep-display-event-name"></h1>
                    <p id="vep-display-time-up" class="vep-display-subheading" style="display: none;"></p>
                    <p id="vep-display-competitions-heading" class="vep-display-subheading" style="display: none;"><?php esc_html_e( 'Competitions', 'volunteer-exchange-platform' ); ?></p>
                    <p id="vep-display-winner-heading" class="vep-display-subheading" style="display: none;"></p>
                </div>

                <!-- Content Area (only one view shown at a time) -->
                <div class="vep-display-content-area">
                    <!-- Countdown View -->
                    <div id="vep-countdown-view" class="vep-view vep-view-active">
                        <div class="vep-display-main-content">
                            <div class="vep-display-left">
                                <div class="vep-display-countdown">
                                    <div class="vep-countdown-timer">
                                        <div class="vep-countdown-clock">
                                            <span id="vep-display-timer-time" class="vep-timer-time">00:00:00</span>
                                        </div>
                                    </div>
                                    <div class="vep-countdown-expired" style="display: none;">
                                        <p id="vep-countdown-expired-text"></p>
                                    </div>
                                </div>
                                
                                <div class="vep-display-agreements">
                                    <div class="vep-agreements-box">
                                        <div class="vep-agreements-count" id="vep-agreements-count">0</div>
                                        <div class="vep-agreements-label"><?php esc_html_e('Agreements Made', 'volunteer-exchange-platform'); ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="vep-display-right" class="vep-display-right" <?php echo 'none' === $display_mode ? 'style="display: none;"' : ''; ?>>
                                <div class="vep-leaderboard">
                                    <h2 class="vep-leaderboard-title">
                                        <span id="vep-display-right-panel-icon" class="dashicons dashicons-awards" <?php echo 'leaderboard' === $display_mode ? '' : 'style="display: none;"'; ?>></span>
                                        <span id="vep-display-right-panel-title"><?php echo esc_html( $right_panel_title ); ?></span>
                                    </h2>
                                    <div id="vep-leaderboard-list" class="vep-leaderboard-list">
                                        <p class="vep-leaderboard-empty"><?php esc_html_e('No agreements yet...', 'volunteer-exchange-platform'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics View -->
                    <div id="vep-statistics-view" class="vep-view" style="display: none;">
                        <div class="vep-statistics-inner">
                            <canvas id="vep-statistics-chart" class="vep-statistics-chart"></canvas>
                            <div class="vep-statistics-summary">
                                <div class="vep-stat-item">
                                    <div class="vep-stat-value" id="vep-stat-total">—</div>
                                    <div class="vep-stat-label"><?php esc_html_e( 'Total agreements', 'volunteer-exchange-platform' ); ?></div>
                                </div>
                                <div class="vep-stat-item">
                                    <div class="vep-stat-value" id="vep-stat-avg">—</div>
                                    <div class="vep-stat-label"><?php esc_html_e( 'Average agreements per minute', 'volunteer-exchange-platform' ); ?></div>
                                </div>
                                <div class="vep-stat-item">
                                    <div class="vep-stat-value" id="vep-stat-max">—</div>
                                    <div class="vep-stat-label"><?php esc_html_e( 'Most agreements by a single actor', 'volunteer-exchange-platform' ); ?></div>
                                </div>
                                <div class="vep-stat-item">
                                    <div class="vep-stat-value" id="vep-stat-first">—</div>
                                    <div class="vep-stat-label"><?php esc_html_e( 'First agreement after', 'volunteer-exchange-platform' ); ?></div>
                                </div>
                            </div>
                            <p class="vep-statistics-error" style="display: none;"></p>
                        </div>
                    </div>

                    <!-- Competitions View -->
                    <div id="vep-competitions-view" class="vep-view" style="display: none;">
                        <div id="vep-competitions-list" class="vep-competitions-list">
                            <p class="vep-competitions-empty"><?php esc_html_e( 'No competitions available.', 'volunteer-exchange-platform' ); ?></p>
                        </div>
                        <p class="vep-competitions-error" style="display: none;"></p>
                    </div>

                    <!-- Winner View -->
                    <div id="vep-winner-view" class="vep-view" style="display: none;">
                        <div class="vep-winner-inner">
                            <div class="vep-winner-label"><u><?php esc_html_e( 'Vinderen er:', 'volunteer-exchange-platform' ); ?></u></div>
                            <div id="vep-winner-name" class="vep-winner-name"></div>
                        </div>
                    </div>

                    <!-- Closing View -->
                    <div id="vep-closing-view" class="vep-view" style="display: none;">
                        <div class="vep-closing-inner">
                            <div id="vep-closing-text" class="vep-closing-text"></div>
                        </div>
                    </div>
                </div>

                <!-- Actions Area (buttons at bottom) -->
                <div class="vep-display-actions">
                    <div id="vep-post-time-actions" class="vep-action-buttons" style="display: none;">
                        <button type="button" id="vep-show-statistics" class="button button-primary button-hero vep-post-time-action-button">
                            <?php esc_html_e( 'Show Statistics', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" id="vep-show-competitions" class="button button-primary button-hero vep-post-time-action-button">
                            <?php esc_html_e( 'Show Competitions', 'volunteer-exchange-platform' ); ?>
                        </button>
                    </div>

                    <div id="vep-statistics-actions" class="vep-action-buttons" style="display: none;">
                        <button type="button" id="vep-back-to-countdown" class="button button-primary button-hero vep-post-time-action-button" style="display: none;">
                            <?php esc_html_e( 'Back to Countdown', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" id="vep-show-competitions-from-statistics" class="button button-primary button-hero vep-post-time-action-button">
                            <?php esc_html_e( 'Show Competitions', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" id="vep-show-closing-from-statistics" class="button button-primary button-hero vep-post-time-action-button" style="display: none;">
                            <?php esc_html_e( 'Afslut', 'volunteer-exchange-platform' ); ?>
                        </button>
                    </div>

                    <div id="vep-competitions-actions" class="vep-action-buttons" style="display: none;">
                        <button type="button" id="vep-show-statistics-from-competitions" class="button button-primary button-hero vep-post-time-action-button">
                            <?php esc_html_e( 'Back to Statistics', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" id="vep-show-first-winner" class="button button-primary button-hero vep-post-time-action-button">
                            <?php esc_html_e( 'Vis første vinder', 'volunteer-exchange-platform' ); ?>
                        </button>
                    </div>

                    <div id="vep-winner-actions" class="vep-action-buttons" style="display: none;">
                        <button type="button" id="vep-winner-back-to-list" class="button button-primary button-hero vep-post-time-action-button">
                            <?php esc_html_e( 'Tilbage til konkurrence oversigt', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" id="vep-winner-prev" class="button button-primary button-hero vep-post-time-action-button" style="display: none;">
                            <?php esc_html_e( 'Forrige vinder', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" id="vep-winner-next" class="button button-primary button-hero vep-post-time-action-button" style="display: none;">
                            <?php esc_html_e( 'Næste vinder', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" id="vep-winner-finish" class="button button-primary button-hero vep-post-time-action-button" style="display: none;">
                            <?php esc_html_e( 'Afslut', 'volunteer-exchange-platform' ); ?>
                        </button>
                    </div>

                    <div id="vep-closing-actions" class="vep-action-buttons" style="display: none;">
                        <button type="button" id="vep-closing-back-to-competitions" class="button button-primary button-hero vep-post-time-action-button">
                            <?php esc_html_e( 'Tilbage til konkurrence oversigten', 'volunteer-exchange-platform' ); ?>
                        </button>
                        <button type="button" id="vep-closing-back-to-statistics" class="button button-primary button-hero vep-post-time-action-button" style="display: none;">
                            <?php esc_html_e( 'Tilbage til statistik', 'volunteer-exchange-platform' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
