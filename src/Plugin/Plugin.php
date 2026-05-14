<?php
/**
 * Plugin bootstrap
 *
 * @package VEP
 * @subpackage Plugin
 */

namespace VolunteerExchangePlatform\Plugin;

use VolunteerExchangePlatform\Admin\CompetitionsPage;
use VolunteerExchangePlatform\Admin\EventDisplayPage;
use VolunteerExchangePlatform\Admin\EventsPage;
use VolunteerExchangePlatform\Admin\Menu;
use VolunteerExchangePlatform\Admin\ParticipantsPage;
use VolunteerExchangePlatform\Admin\ParticipantTypesPage;
use VolunteerExchangePlatform\Admin\SettingsPage;
use VolunteerExchangePlatform\Admin\TagsPage;
use VolunteerExchangePlatform\Ajax\AgreementHandler;
use VolunteerExchangePlatform\Ajax\CompetitionHandler;
use VolunteerExchangePlatform\Ajax\EventDisplayHandler;
use VolunteerExchangePlatform\Ajax\ParticipantHandler;
use VolunteerExchangePlatform\Database\CompetitionRepository;
use VolunteerExchangePlatform\Database\EventRepository;
use VolunteerExchangePlatform\Database\Installer;
use VolunteerExchangePlatform\Database\ParticipantRepository;
use VolunteerExchangePlatform\Database\ParticipantTypeRepository;
use VolunteerExchangePlatform\Database\TagRepository;
use VolunteerExchangePlatform\Email\TransactionalEmailService;
use VolunteerExchangePlatform\Email\EmailCleanupWorker;
use VolunteerExchangePlatform\Email\TransactionalEmailWorker;
use VolunteerExchangePlatform\Email\ParticipantReminderWorker;
use VolunteerExchangePlatform\Frontend\ParticipantPage;
use VolunteerExchangePlatform\Frontend\UpdateParticipantPage;
use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\CompetitionService;
use VolunteerExchangePlatform\Services\ParticipantService;
use VolunteerExchangePlatform\Services\ParticipantTypeService;
use VolunteerExchangePlatform\Services\TagService;
use VolunteerExchangePlatform\Shortcodes\AgreementForm;
use VolunteerExchangePlatform\Shortcodes\AgreementsList;
use VolunteerExchangePlatform\Shortcodes\EventCountdown;
use VolunteerExchangePlatform\Shortcodes\ParticipantsGrid;
use VolunteerExchangePlatform\Shortcodes\ParticipantsTable;
use VolunteerExchangePlatform\Shortcodes\Registration;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    /**
     * Resolve cache-busting version for an asset.
     *
     * @param string $relative_path Asset path relative to plugin root.
     * @return string
     */
    private function asset_version( $relative_path ) {
        $plugin_root = dirname( __DIR__, 2 ) . '/';
        $full_path = $plugin_root . ltrim( $relative_path, '/' );

        if ( file_exists( $full_path ) ) {
            return (string) filemtime( $full_path );
        }

        return (string) VEP_VERSION;
    }

    /**
     * Run plugin bootstrap.
     *
     * @return void
     */
    public function run() {
        if ( ! function_exists( 'add_action' ) ) {
            return;
        }

        $installer = new Installer();
        $installer->maybe_upgrade();

        $repositories = $this->build_repositories();
        $services = $this->build_services( $repositories );

        if ( is_admin() ) {
            $this->init_admin( $services );
        }

        $this->init_public( $services );
        $this->init_email();
        new WPBakeryIntegration();
        $this->init_ajax( $services );
        $this->register_hooks();
    }

    private function build_repositories() {
        return array(
            'competition' => new CompetitionRepository(),
            'event' => new EventRepository(),
            'participant' => new ParticipantRepository(),
            'participant_type' => new ParticipantTypeRepository(),
            'tag' => new TagRepository(),
        );
    }

    private function build_services( array $repositories ) {
        $event_service = new EventService(
            $repositories['event'],
            $repositories['participant']
        );

        $participant_service = new ParticipantService(
            $repositories['participant'],
            $repositories['event'],
            $repositories['participant_type'],
            $repositories['tag']
        );

        $competition_service = new CompetitionService(
            $repositories['competition'],
            $repositories['event'],
            $repositories['participant']
        );

        $participant_type_service = new ParticipantTypeService( $repositories['participant_type'] );
        $tag_service = new TagService( $repositories['tag'] );

        return array(
            'competition' => $competition_service,
            'event' => $event_service,
            'participant' => $participant_service,
            'participant_type' => $participant_type_service,
            'tag' => $tag_service,
        );
    }

    private function init_admin( array $services ) {
        $events_page = new EventsPage( $services['event'], $services['participant'], $services['participant_type'], $services['tag'], $services['competition'] );
        $participants_page = new ParticipantsPage( $services['participant'], $services['event'], $services['participant_type'], $services['tag'] );
        $participant_types_page = new ParticipantTypesPage( $services['participant_type'] );
        $tags_page = new TagsPage( $services['tag'] );
        $event_display_page = new EventDisplayPage( $services['event'] );
        $competitions_page   = new CompetitionsPage( $services['competition'], $services['event'], $services['participant'] );
        $settings_page = new SettingsPage();

        new Menu(
            $events_page,
            $participants_page,
            $participant_types_page,
            $tags_page,
            $event_display_page,
            $competitions_page,
            $settings_page
        );
    }

    private function init_public( array $services ) {
        new Registration( $services['event'], $services['participant_type'] );
        new EventCountdown( $services['event'] );
        new ParticipantsGrid( $services['event'], $services['participant'] );
        new ParticipantsTable( $services['event'], $services['participant'] );
        new AgreementForm( $services['event'], $services['participant'] );
        new AgreementsList( $services['event'] );
        new ParticipantPage( $services['participant'] );
        new UpdateParticipantPage( $services['participant'], $services['participant_type'], $services['tag'] );
    }

    private function init_ajax( array $services ) {
        new ParticipantHandler( $services['participant'] );
        new AgreementHandler( $services['event'], $services['competition'] );
        new EventDisplayHandler( $services['event'], $services['competition'] );
        new CompetitionHandler( $services['competition'], $services['event'] );
    }

    private function init_email() {
        new TransactionalEmailService();
        new TransactionalEmailWorker();
        new ParticipantReminderWorker();
        new EmailCleanupWorker();
    }

    private function register_hooks() {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_scripts' ) );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin hook.
     * @return void
     */
    public function admin_enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'volunteer-exchange' ) === false && strpos( $hook, 'vep-event-display' ) === false ) {
            return;
        }

        $plugin_root = dirname( __DIR__, 2 ) . '/';
        $fontawesome_candidates = array(
            'assets/vendor/fontawesome-free-7.1.0-web/css/all.min.css',
            'assets/vendor/fontawesome/css/all.min.css',
        );

        foreach ( $fontawesome_candidates as $fontawesome_relative_path ) {
            if ( file_exists( $plugin_root . $fontawesome_relative_path ) ) {
                wp_enqueue_style(
                    'font-awesome-admin',
                    VEP_PLUGIN_URL . $fontawesome_relative_path,
                    array(),
                    VEP_VERSION,
                    'all'
                );
                break;
            }
        }

        if ( file_exists( $plugin_root . 'assets/vendor/choices-11.2.0/public/assets/styles/choices.min.css' ) ) {
            wp_enqueue_style( 'choices-css', VEP_PLUGIN_URL . 'assets/vendor/choices-11.2.0/public/assets/styles/choices.min.css', array(), VEP_VERSION );
        }

        if ( file_exists( $plugin_root . 'assets/vendor/choices-11.2.0/public/assets/scripts/choices.min.js' ) ) {
            wp_enqueue_script( 'choices-js', VEP_PLUGIN_URL . 'assets/vendor/choices-11.2.0/public/assets/scripts/choices.min.js', array(), VEP_VERSION, true );
        }

        wp_enqueue_style( 'vep-admin-style', VEP_PLUGIN_URL . 'assets/css/admin.css', array(), $this->asset_version( 'assets/css/admin.css' ) );
        wp_enqueue_script( 'vep-admin-script', VEP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable', 'choices-js' ), $this->asset_version( 'assets/js/admin.js' ), true );

        wp_localize_script(
            'vep-admin-script',
            'vepAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'vep_admin_nonce' ),
                'competitionNonce' => wp_create_nonce( 'vep_competitions_nonce' ),
                'placeholderText' => __( 'Please select icon', 'volunteer-exchange-platform' ),
                'i18n' => array(
                    'agreementSingular'       => __( 'agreement', 'volunteer-exchange-platform' ),
                    'agreementPlural'         => __( 'agreements', 'volunteer-exchange-platform' ),
                    'noAgreementsYet'         => __( 'No agreements yet...', 'volunteer-exchange-platform' ),
                    'noRecentAgreementsYet'   => __( 'No recent agreements yet...', 'volunteer-exchange-platform' ),
                    'topParticipantsTitle'    => __( 'Top Participants', 'volunteer-exchange-platform' ),
                    'latestAgreementsTitle'   => __( 'Latest Agreements', 'volunteer-exchange-platform' ),
                    'agreementBetweenLabel'   => __( 'Agreement between', 'volunteer-exchange-platform' ),
                    'setCountdownTimeFirst'   => __( 'Please set a countdown time first.', 'volunteer-exchange-platform' ),
                    'confirmCompetitionDelete' => __( 'Are you sure?', 'volunteer-exchange-platform' ),
                    'reorderCompetitionFailed' => __( 'Failed to reorder competitions', 'volunteer-exchange-platform' ),
                    'competitionActionFailed'  => __( 'An error occurred. Please try again.', 'volunteer-exchange-platform' ),
                    'winnerSaved'             => __( 'Winner set successfully', 'volunteer-exchange-platform' ),
                    'winnerReset'             => __( 'Winner reset successfully', 'volunteer-exchange-platform' ),
                    'resetAllWinnersConfirm'  => __( 'Are you sure you want to reset all winners? This cannot be undone.', 'volunteer-exchange-platform' ),
                    'copyActiveEmailsSuccess'  => __( 'Copied %d active participant emails to clipboard.', 'volunteer-exchange-platform' ),
                    'copyActiveEmailsFailed'   => __( 'Could not copy active participant emails. Please try again.', 'volunteer-exchange-platform' ),
                    'noActiveEmailsFound'      => __( 'No active participant emails found.', 'volunteer-exchange-platform' ),
                    'statsChartLabel'          => __( 'Agreements per minute', 'volunteer-exchange-platform' ),
                    'statsLoadingError'        => __( 'Could not load statistics.', 'volunteer-exchange-platform' ),
                    'winnerBackFirst'          => __( 'Tilbage til konkurrence oversigt', 'volunteer-exchange-platform' ),
                    'winnerBackOther'          => __( 'Konkurrence oversigt', 'volunteer-exchange-platform' ),
                    'winnerPrev'               => __( 'Forrige vinder', 'volunteer-exchange-platform' ),
                    'winnerNext'               => __( 'N\u00e6ste vinder', 'volunteer-exchange-platform' ),
                    'winnerFinish'             => __( 'Afslut', 'volunteer-exchange-platform' ),
                    'noCompetitionsAvailable'  => __( 'No competitions available.', 'volunteer-exchange-platform' ),
                ),
                'closingText'        => get_option( 'vep_display_closing_text', __( 'Tak for denne gang', 'volunteer-exchange-platform' ) ),
                'timeUpText'         => get_option( 'vep_display_time_up_text', __( 'Tiden er gået', 'volunteer-exchange-platform' ) ),
                'hideButtons'        => (bool) get_option( 'vep_display_hide_buttons', 1 ),
                'showCompetitions'   => (bool) get_option( 'vep_display_show_competitions', 1 ),
                'choicesI18n' => array(
                    'searchPlaceholderValue' => __( 'Search...', 'volunteer-exchange-platform' ),
                    'itemSelectText' => __( 'Press to select', 'volunteer-exchange-platform' ),
                    'noResultsText' => __( 'No results found', 'volunteer-exchange-platform' ),
                    'noChoicesText' => __( 'No options available', 'volunteer-exchange-platform' ),
                ),
            )
        );
    }

    /**
     * Enqueue frontend scripts and styles.
     *
     * @return void
     */
    public function frontend_enqueue_scripts() {
        $plugin_root = dirname( __DIR__, 2 ) . '/';

        wp_enqueue_style(
            'vep-frontend-style',
            VEP_PLUGIN_URL . 'assets/css/public.css',
            array(),
            $this->asset_version( 'assets/css/public.css' )
        );

        // if ( file_exists( $plugin_root . 'assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css' ) ) {
        //     wp_enqueue_style( 'bootstrap', VEP_PLUGIN_URL . 'assets/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css', array(), VEP_VERSION );
        // }

        // if ( file_exists( $plugin_root . 'assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js' ) ) {
        //     wp_enqueue_script( 'bootstrap', VEP_PLUGIN_URL . 'assets/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js', array(), VEP_VERSION, true );
        // }

        if ( file_exists( $plugin_root . 'assets/vendor/choices-11.2.0/public/assets/styles/choices.min.css' ) ) {
            wp_enqueue_style( 'choices-css', VEP_PLUGIN_URL . 'assets/vendor/choices-11.2.0/public/assets/styles/choices.min.css', array(), VEP_VERSION );
        }

        if ( file_exists( $plugin_root . 'assets/vendor/choices-11.2.0/public/assets/scripts/choices.min.js' ) ) {
            wp_enqueue_script( 'choices-js', VEP_PLUGIN_URL . 'assets/vendor/choices-11.2.0/public/assets/scripts/choices.min.js', array(), VEP_VERSION, true );
        }

        wp_enqueue_script(
            'vep-frontend-script',
            VEP_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            $this->asset_version( 'assets/js/frontend.js' ),
            true
        );

        wp_localize_script(
            'vep-frontend-script',
            'vepFrontend',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'vep_frontend_nonce' ),
                'i18n' => array(
                    'submitting' => __( 'Submitting...', 'volunteer-exchange-platform' ),
                    'creating' => __( 'Creating...', 'volunteer-exchange-platform' ),
                    'genericError' => __( 'An error occurred. Please try again.', 'volunteer-exchange-platform' ),
                    'selectAtLeastOne' => __( 'Please select at least one option.', 'volunteer-exchange-platform' ),
                ),
                'choicesI18n' => array(
                    'searchPlaceholderValue' => __( 'Search...', 'volunteer-exchange-platform' ),
                    'itemSelectText' => __( 'Press to select', 'volunteer-exchange-platform' ),
                    'noResultsText' => __( 'No results found', 'volunteer-exchange-platform' ),
                    'noChoicesText' => __( 'No options available', 'volunteer-exchange-platform' ),
                ),
            )
        );
    }
}
