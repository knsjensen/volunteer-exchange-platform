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
                ? $this->sanitize_hex_color_with_fallback( wp_unslash( $_POST['vep_background_gradient_color_1'] ), '#1e3c72' )
                : '#1e3c72';
            $gradient_color_2 = isset( $_POST['vep_background_gradient_color_2'] )
                ? $this->sanitize_hex_color_with_fallback( wp_unslash( $_POST['vep_background_gradient_color_2'] ), '#2a5298' )
                : '#2a5298';
            $gradient_color_3 = isset( $_POST['vep_background_gradient_color_3'] )
                ? $this->sanitize_hex_color_with_fallback( wp_unslash( $_POST['vep_background_gradient_color_3'] ), '#7e22ce' )
                : '#7e22ce';

            $gradient_stop_1 = isset( $_POST['vep_background_gradient_stop_1'] )
                ? $this->sanitize_percentage( wp_unslash( $_POST['vep_background_gradient_stop_1'] ), 0 )
                : 0;
            $gradient_stop_2 = isset( $_POST['vep_background_gradient_stop_2'] )
                ? $this->sanitize_percentage( wp_unslash( $_POST['vep_background_gradient_stop_2'] ), 50 )
                : 50;
            $gradient_stop_3 = isset( $_POST['vep_background_gradient_stop_3'] )
                ? $this->sanitize_percentage( wp_unslash( $_POST['vep_background_gradient_stop_3'] ), 100 )
                : 100;
            $gradient_angle = isset( $_POST['vep_background_gradient_angle'] )
                ? $this->sanitize_degrees( wp_unslash( $_POST['vep_background_gradient_angle'] ), 135 )
                : 135;
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
        $gradient_color_1 = $this->sanitize_hex_color_with_fallback( get_option('vep_display_background_gradient_color_1', '#1e3c72'), '#1e3c72' );
        $gradient_color_2 = $this->sanitize_hex_color_with_fallback( get_option('vep_display_background_gradient_color_2', '#2a5298'), '#2a5298' );
        $gradient_color_3 = $this->sanitize_hex_color_with_fallback( get_option('vep_display_background_gradient_color_3', '#7e22ce'), '#7e22ce' );
        $gradient_stop_1 = $this->sanitize_percentage( get_option('vep_display_background_gradient_stop_1', 0), 0 );
        $gradient_stop_2 = $this->sanitize_percentage( get_option('vep_display_background_gradient_stop_2', 50), 50 );
        $gradient_stop_3 = $this->sanitize_percentage( get_option('vep_display_background_gradient_stop_3', 100), 100 );
        $gradient_angle = $this->sanitize_degrees( get_option('vep_display_background_gradient_angle', 135), 135 );
        $display_text_color = $this->sanitize_hex_color_with_fallback( get_option('vep_display_text_color', '#ffffff'), '#ffffff' );

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
                <div class="card" style="padding: 20px;">
                    <h2><?php esc_html_e('Display Settings', 'volunteer-exchange-platform'); ?></h2>
                    
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
                                                data-default-gradient-color-1="#1e3c72"
                                                data-default-gradient-color-2="#2a5298"
                                                data-default-gradient-color-3="#7e22ce"
                                                data-default-gradient-stop-1="0"
                                                data-default-gradient-stop-2="50"
                                                data-default-gradient-stop-3="100"
                                                data-default-gradient-angle="135"
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
                        </p>
                    </form>
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
                                style="margin-top: 10px;">
                            <span class="dashicons dashicons-visibility" style="margin-top: 6px;"></span>
                            <?php esc_html_e('Start Event Display', 'volunteer-exchange-platform'); ?>
                        </button>
                        
                        <p class="description" style="margin-top: 10px;">
                            <?php esc_html_e('This will open a fullscreen display with countdown timer and live agreement count. Press ESC to exit fullscreen.', 'volunteer-exchange-platform'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Fullscreen Display Modal -->
        <div id="vep-fullscreen-display" class="vep-fullscreen-display" style="display: none;">
            <button id="vep-close-display" class="vep-close-display" title="<?php esc_attr_e('Close Display (ESC)', 'volunteer-exchange-platform'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
            
            <canvas id="vep-fireworks-canvas" class="vep-fireworks-canvas"></canvas>
            
            <div class="vep-display-content">
                <div class="vep-display-header">
                    <h1 id="vep-display-event-name"></h1>
                </div>
                
                <div id="vep-display-main-content" class="vep-display-main-content">
                    <div class="vep-display-left">
                        <div class="vep-display-countdown">
                            <div class="vep-countdown-timer">
                                <div class="vep-countdown-clock">
                                    <span id="vep-display-timer-time" class="vep-timer-time">00:00:00</span>
                                </div>
                            </div>
                            <div class="vep-countdown-expired" style="display: none;">
                                <p><?php esc_html_e('Times up!', 'volunteer-exchange-platform'); ?></p>
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
        </div>
        <?php
    }
}
