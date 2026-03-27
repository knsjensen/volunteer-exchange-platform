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
 * countdown timer configuration and fullscreen event display launch.
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
        if (isset($_POST['vep_save_display_settings']) && check_admin_referer('vep_display_settings', 'vep_display_nonce')) {
            $countdown_datetime = isset( $_POST['countdown_datetime'] ) ? sanitize_text_field( wp_unslash( $_POST['countdown_datetime'] ) ) : '';
            $display_title = isset( $_POST['display_title'] ) ? sanitize_text_field( wp_unslash( $_POST['display_title'] ) ) : '';

            update_option('vep_display_countdown_datetime', $countdown_datetime);
            update_option('vep_display_title', $display_title);
            
            wp_safe_redirect(add_query_arg(array(
                'page' => 'vep-event-display',
                'message' => 'settings_saved'
            ), admin_url('admin.php')));
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
        // Get saved countdown datetime
        $countdown_datetime = get_option('vep_display_countdown_datetime', '');
        $display_title = get_option('vep_display_title', '');
        $message = filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        // Get active event
        $active_event = $this->event_service->get_active_event();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Event Display', 'volunteer-exchange-platform'); ?></h1>
            
            <?php if ( sanitize_key( (string) $message ) === 'settings_saved' ): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved successfully.', 'volunteer-exchange-platform'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!$active_event): ?>
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
                                    <label for="countdown_datetime"><?php esc_html_e('Countdown Target Time', 'volunteer-exchange-platform'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="countdown_datetime" 
                                           name="countdown_datetime" 
                                           value="<?php echo esc_attr($countdown_datetime); ?>" 
                                           class="regular-text"
                                           placeholder="YYYY-MM-DD HH:MM:SS">
                                    <p class="description">
                                        <?php esc_html_e('Enter the countdown target date and time. Format: YYYY-MM-DD HH:MM:SS (e.g., 2026-02-15 18:00:00)', 'volunteer-exchange-platform'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="vep_save_display_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'volunteer-exchange-platform'); ?>">
                        </p>
                    </form>
                </div>
                
                <?php if ($active_event && !empty($countdown_datetime)): ?>
                    <div class="card" style="padding: 20px; margin-top: 20px;">
                        <h2><?php esc_html_e('Display Control', 'volunteer-exchange-platform'); ?></h2>
                        <p><?php esc_html_e('Click the button below to launch the fullscreen event display.', 'volunteer-exchange-platform'); ?></p>
                        
                        <button type="button" 
                                id="vep-start-event-display" 
                                class="button button-primary button-hero"
                                data-countdown="<?php echo esc_attr($countdown_datetime); ?>"
                                data-event-id="<?php echo esc_attr($active_event->id); ?>"
                                data-display-title="<?php echo esc_attr($display_title ? $display_title : $active_event->name); ?>"
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
                
                <div class="vep-display-main-content">
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
                    
                    <div class="vep-display-right">
                        <div class="vep-leaderboard">
                            <h2 class="vep-leaderboard-title">
                                <span class="dashicons dashicons-awards"></span>
                                <?php esc_html_e('Top Participants', 'volunteer-exchange-platform'); ?>
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
