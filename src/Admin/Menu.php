<?php
/**
 * Admin menu class
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin menu class
 *
 * Registers all admin menu pages and holds instances of page handlers.
 *
 * @package VolunteerExchangePlatform\Admin
 */
class Menu {
    private $eventsPage;
    private $participantsPage;
    private $participantTypesPage;
    private $tagsPage;
    private $eventDisplayPage;
    private $competitionsPage;
    private $emailSettingsPage;

    /**
        * Constructor.
        *
        * @param EventsPage|null           $events_page Events page handler.
        * @param ParticipantsPage|null     $participants_page Participants page handler.
        * @param ParticipantTypesPage|null $participant_types_page Participant types page handler.
        * @param TagsPage|null             $tags_page Tags page handler.
        * @param EventDisplayPage|null     $event_display_page Event display page handler.
         * @param CompetitionsPage|null     $competitions_page Competitions page handler.
        * @return void
     */
    public function __construct(
        ?EventsPage $events_page = null,
        ?ParticipantsPage $participants_page = null,
        ?ParticipantTypesPage $participant_types_page = null,
        ?TagsPage $tags_page = null,
            ?EventDisplayPage $event_display_page = null,
            ?CompetitionsPage $competitions_page = null,
        ?EmailSettingsPage $email_settings_page = null
    ) {
        $this->eventsPage = $events_page ?: new EventsPage();
        $this->participantsPage = $participants_page ?: new ParticipantsPage();
        $this->participantTypesPage = $participant_types_page ?: new ParticipantTypesPage();
        $this->tagsPage = $tags_page ?: new TagsPage();
        $this->eventDisplayPage = $event_display_page ?: new EventDisplayPage();
            $this->competitionsPage = $competitions_page ?: new CompetitionsPage();
        $this->emailSettingsPage = $email_settings_page ?: new EmailSettingsPage();

        add_action('admin_menu', array($this, 'add_menu_pages'));
    }

    /**
        * Register plugin admin menu and submenu pages.
        *
        * @return void
     */
    public function add_menu_pages() {
        // Main menu
        add_menu_page(
            __('Volunteer Exchange', 'volunteer-exchange-platform'),
            __('Volunteer Exchange', 'volunteer-exchange-platform'),
            'manage_options',
            'volunteer-exchange',
            array($this, 'main_page'),
            'dashicons-groups',
            30
        );

        // Events submenu
        add_submenu_page(
            'volunteer-exchange',
            __('Events', 'volunteer-exchange-platform'),
            __('Events', 'volunteer-exchange-platform'),
            'manage_options',
            'volunteer-exchange-events',
            array($this->eventsPage, 'render')
        );

        // Participants submenu
        add_submenu_page(
            'volunteer-exchange',
            __('Participants', 'volunteer-exchange-platform'),
            __('Participants', 'volunteer-exchange-platform'),
            'manage_options',
            'volunteer-exchange-participants',
            array($this->participantsPage, 'render')
        );

        // Participant Types submenu
        add_submenu_page(
            'volunteer-exchange',
            __('Participant Types', 'volunteer-exchange-platform'),
            __('Participant Types', 'volunteer-exchange-platform'),
            'manage_options',
            'volunteer-exchange-participant-types',
            array($this->participantTypesPage, 'render')
        );

        // Tags submenu
        add_submenu_page(
            'volunteer-exchange',
            __('We Offer Tags', 'volunteer-exchange-platform'),
            __('We Offer Tags', 'volunteer-exchange-platform'),
            'manage_options',
            'volunteer-exchange-tags',
            array($this->tagsPage, 'render')
        );

        // Event Display submenu
        add_submenu_page(
            'volunteer-exchange',
            __('Event Display', 'volunteer-exchange-platform'),
            __('Event Display', 'volunteer-exchange-platform'),
            'manage_options',
            'vep-event-display',
            array($this->eventDisplayPage, 'render')
        );

        // Competitions submenu
        add_submenu_page(
            'volunteer-exchange',
            __('Competitions', 'volunteer-exchange-platform'),
            __('Competitions', 'volunteer-exchange-platform'),
            'manage_options',
            'volunteer-exchange-competitions',
            array($this->competitionsPage, 'render')
        );

        // Email Settings submenu
        add_submenu_page(
            'volunteer-exchange',
            __('Email Settings', 'volunteer-exchange-platform'),
            __('Email Settings', 'volunteer-exchange-platform'),
            'manage_options',
            'vep-email-settings',
            array($this->emailSettingsPage, 'render')
        );

        // Remove duplicate main menu item
        remove_submenu_page('volunteer-exchange', 'volunteer-exchange');
    }

    /**
     * Redirect main plugin page to events page.
     *
     * @return void
     */
    public function main_page() {
        wp_safe_redirect(admin_url('admin.php?page=volunteer-exchange-events'));
        exit;
    }
}
