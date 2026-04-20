<?php
/**
 * Plugin Name: Volunteer Exchange Platform
 * Plugin URI: https://www.noergaardsmidt.dk
 * Description: A comprehensive platform for managing volunteer exchanges between actors/organizations with events, participants, and agreements.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.0
 * Author: Kim Nørgaard Smidt Jensen
 * Author URI: https://www.noergaardsmidt.dk
 * Text Domain: volunteer-exchange-platform
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VEP
 * @subpackage Plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VEP_VERSION', '1.0.0');
define('VEP_DB_VERSION', '1.0.0');
define('VEP_MIN_PHP_VERSION', '7.0');
define('VEP_MIN_WP_VERSION', '5.0');
define('VEP_TESTED_UP_TO_WP_VERSION', '6.8');
define('VEP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VEP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VEP_PLUGIN_FILE', __FILE__);
define('VEP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Hardcoded dependency loading
require_once VEP_PLUGIN_DIR . 'src/Database/Installer.php';
require_once VEP_PLUGIN_DIR . 'src/Database/AbstractRepository.php';
require_once VEP_PLUGIN_DIR . 'src/Database/EventRepository.php';
require_once VEP_PLUGIN_DIR . 'src/Database/ParticipantRepository.php';
require_once VEP_PLUGIN_DIR . 'src/Database/ParticipantTypeRepository.php';
require_once VEP_PLUGIN_DIR . 'src/Database/TagRepository.php';

require_once VEP_PLUGIN_DIR . 'src/Services/AbstractService.php';
require_once VEP_PLUGIN_DIR . 'src/Services/EventService.php';
require_once VEP_PLUGIN_DIR . 'src/Services/ParticipantService.php';
require_once VEP_PLUGIN_DIR . 'src/Services/ParticipantTypeService.php';
require_once VEP_PLUGIN_DIR . 'src/Services/TagService.php';

require_once VEP_PLUGIN_DIR . 'src/Admin/EventsListTable.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/EventsPage.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/ParticipantsListTable.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/ParticipantsPage.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/ParticipantTypesListTable.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/ParticipantTypesPage.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/TagsListTable.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/TagsPage.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/EventDisplayPage.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/CompetitionsPage.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/TransactionalEmailsListTable.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/Menu.php';

require_once VEP_PLUGIN_DIR . 'src/Ajax/ParticipantHandler.php';
require_once VEP_PLUGIN_DIR . 'src/Ajax/AgreementHandler.php';
require_once VEP_PLUGIN_DIR . 'src/Ajax/EventDisplayHandler.php';

require_once VEP_PLUGIN_DIR . 'src/Email/Settings.php';
require_once VEP_PLUGIN_DIR . 'src/Email/EmailQueueRepository.php';
require_once VEP_PLUGIN_DIR . 'src/Email/TransactionalEmailService.php';
require_once VEP_PLUGIN_DIR . 'src/Email/TransactionalEmailWorker.php';
require_once VEP_PLUGIN_DIR . 'src/Email/ParticipantReminderWorker.php';
require_once VEP_PLUGIN_DIR . 'src/Email/EmailCleanupWorker.php';
require_once VEP_PLUGIN_DIR . 'src/Admin/SettingsPage.php';

require_once VEP_PLUGIN_DIR . 'src/Shortcodes/Registration.php';
require_once VEP_PLUGIN_DIR . 'src/Shortcodes/EventCountdown.php';
require_once VEP_PLUGIN_DIR . 'src/Shortcodes/ParticipantsGrid.php';
require_once VEP_PLUGIN_DIR . 'src/Shortcodes/ParticipantsTable.php';
require_once VEP_PLUGIN_DIR . 'src/Shortcodes/AgreementForm.php';
require_once VEP_PLUGIN_DIR . 'src/Shortcodes/AgreementsList.php';

require_once VEP_PLUGIN_DIR . 'src/Frontend/ParticipantPage.php';
require_once VEP_PLUGIN_DIR . 'src/Frontend/UpdateParticipantPage.php';

require_once VEP_PLUGIN_DIR . 'src/Plugin/Activator.php';
require_once VEP_PLUGIN_DIR . 'src/Plugin/Deactivator.php';
require_once VEP_PLUGIN_DIR . 'src/Plugin/Dependencies.php';
require_once VEP_PLUGIN_DIR . 'src/Plugin/WPBakeryIntegration.php';
require_once VEP_PLUGIN_DIR . 'src/Plugin/Plugin.php';

/**
 * Display admin notice if dependencies are not met
 */
function volunteer_exchange_platform_dependency_notice() {
    if (!VolunteerExchangePlatform\Plugin\Dependencies::check()) {
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

add_action('admin_init', 'volunteer_exchange_platform_dependency_notice');

// Register hooks
register_activation_hook(__FILE__, array('VolunteerExchangePlatform\\Plugin\\Activator', 'activate'));
register_deactivation_hook(__FILE__, array('VolunteerExchangePlatform\\Plugin\\Deactivator', 'deactivate'));


// Initialize the plugin with manual dependency injection only if dependencies are met
if (VolunteerExchangePlatform\Plugin\Dependencies::check() && function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', function () {
        ( new \VolunteerExchangePlatform\Plugin\Plugin() )->run();
	});
}

/**
 * Queue a transactional email for asynchronous delivery.
 *
 * @param array $message Message payload.
 * @return int|false Queue item ID on success.
 */
function vep_queue_transactional_email( $message ) {
    $service = new \VolunteerExchangePlatform\Email\TransactionalEmailService( null, false );
    $result = $service->enqueue( $message );

    return apply_filters( 'vep_queue_transactional_email_result', $result, $message );
}
