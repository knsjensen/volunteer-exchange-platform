<?php
/**
 * Handles the key-based unsubscribe page.
 *
 * Supports two unsubscribe types:
 *  - deltager-paamindelse / participant-reminder → reminder_unsubscribed
 *  - aftale-paamindelse  / agreement-reminder   → agreement_overview_unsubscribed
 *
 * URL formats:
 *  /vep/afmeld/{type}/{key}          (Danish, typed)
 *  /vep/unsubscribe/{type}/{key}     (English, typed)
 *  /vep/afmeld/{key}                 (backwards-compat → deltager-paamindelse)
 *  /vep/unsubscribe/{key}            (backwards-compat → deltager-paamindelse)
 *
 * @package VEP
 * @subpackage Frontend
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Frontend;

use VolunteerExchangePlatform\Services\ParticipantService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UnsubscribePage {

    /** @var ParticipantService */
    private $participant_service;

    /** Supported types: slug → DB field */
    const TYPES = array(
        'deltager-paamindelse'  => 'reminder_unsubscribed',
        'participant-reminder'  => 'reminder_unsubscribed',
        'aftale-paamindelse'    => 'agreement_overview_unsubscribed',
        'agreement-reminder'    => 'agreement_overview_unsubscribed',
    );

    const DEFAULT_TYPE = 'deltager-paamindelse';

    /**
     * Constructor.
     *
     * @param ParticipantService|null $participant_service Participant service.
     */
    public function __construct( ?ParticipantService $participant_service = null ) {
        $this->participant_service = $participant_service ?: new ParticipantService();

        add_action( 'init',              array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars',        array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_page' ) );
    }

    /**
     * Register rewrite rules.
     *
     * @return void
     */
    public function add_rewrite_rules() {
        // Two-segment (type + key) — added first = highest priority.
        add_rewrite_rule(
            '^vep/unsubscribe/([^/]+)/([^/]+)/?$',
            'index.php?vep_unsubscribe_type=$matches[1]&vep_unsubscribe_key=$matches[2]',
            'top'
        );
        add_rewrite_rule(
            '^vep/afmeld/([^/]+)/([^/]+)/?$',
            'index.php?vep_unsubscribe_type=$matches[1]&vep_unsubscribe_key=$matches[2]',
            'top'
        );
        // One-segment backwards compat — defaults to deltager-paamindelse.
        add_rewrite_rule(
            '^vep/unsubscribe/([^/]+)/?$',
            'index.php?vep_unsubscribe_type=' . self::DEFAULT_TYPE . '&vep_unsubscribe_key=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^vep/afmeld/([^/]+)/?$',
            'index.php?vep_unsubscribe_type=' . self::DEFAULT_TYPE . '&vep_unsubscribe_key=$matches[1]',
            'top'
        );
    }

    /**
     * Register custom query vars.
     *
     * @param array $vars Existing query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'vep_unsubscribe_key';
        $vars[] = 'vep_unsubscribe_type';
        return $vars;
    }

    /**
     * Resolve a type slug to its canonical Danish slug.
     *
     * @param string $raw_type Raw type from URL.
     * @return string|null Canonical slug, or null if unsupported.
     */
    private function resolve_type( $raw_type ) {
        if ( isset( self::TYPES[ $raw_type ] ) ) {
            return $raw_type;
        }
        return null;
    }

    /**
     * Handle page rendering. The unsubscribe is applied only on an explicit POST.
     *
     * @return void
     */
    public function handle_page() {
        $randon_key = get_query_var( 'vep_unsubscribe_key' );
        if ( ! $randon_key ) {
            return;
        }

        $randon_key  = sanitize_text_field( (string) $randon_key );
        $raw_type    = sanitize_key( (string) get_query_var( 'vep_unsubscribe_type', self::DEFAULT_TYPE ) );
        $type        = $this->resolve_type( $raw_type ) ?: self::DEFAULT_TYPE;
        $db_field    = self::TYPES[ $type ];

        $participant = $this->participant_service->get_by_randon_key( $randon_key );

        // Key-based pages must never be cached.
        nocache_headers();

        // Unsubscribe only on an explicit POST. A plain GET (e.g. from a mail link scanner)
        // only shows a confirmation page.
        $is_post   = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] );
        $confirmed = $is_post && isset( $_POST['vep_unsubscribe_confirm'] ) && '1' === (string) wp_unslash( $_POST['vep_unsubscribe_confirm'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Anonymous email link; the secret key is the credential.

        get_header();
        echo '<div class="vep-page-wrapper">';

        if ( ! $participant ) {
            echo '<div class="vep-unsubscribe-page"><div class="vep-message vep-error">' .
                esc_html__( 'Participant not found.', 'volunteer-exchange-platform' ) .
                '</div></div>';
        } else {
            if ( $confirmed ) {
                if ( 'reminder_unsubscribed' === $db_field ) {
                    $this->participant_service->set_reminder_unsubscribed( (int) $participant->id, 1 );
                    $participant->reminder_unsubscribed = 1;
                } else {
                    $this->participant_service->set_agreement_overview_unsubscribed( (int) $participant->id, 1 );
                    $participant->agreement_overview_unsubscribed = 1;
                }
            }
            echo $this->render( $participant, $randon_key, $type, $db_field ); // Output is escaped within render().
        }

        echo '</div>';
        get_footer();
        exit;
    }

    /**
     * Render the unsubscribe page content.
     *
     * @param object $participant Participant row.
     * @param string $randon_key  Participant key.
     * @param string $type        Canonical type slug.
     * @param string $db_field    DB field name.
     * @return string
     */
    private function render( $participant, $randon_key, $type, $db_field ) {
        $is_unsubscribed = ! empty( $participant->{ $db_field } );

        if ( 'agreement_overview_unsubscribed' === $db_field ) {
            $title       = __( 'Agreement email preferences', 'volunteer-exchange-platform' );
            $msg_unsub   = __( 'You are now unsubscribed from agreement overview emails.', 'volunteer-exchange-platform' );
            $msg_sub     = __( 'You are subscribed to agreement overview emails.', 'volunteer-exchange-platform' );
            $btn_resub   = __( 'Re-subscribe to agreement overview emails', 'volunteer-exchange-platform' );
            $btn_unsub   = __( 'Unsubscribe from agreement overview emails', 'volunteer-exchange-platform' );
        } else {
            $title       = __( 'Reminder preferences', 'volunteer-exchange-platform' );
            $msg_unsub   = __( 'You are now unsubscribed from future reminders.', 'volunteer-exchange-platform' );
            $msg_sub     = __( 'You are subscribed to reminders.', 'volunteer-exchange-platform' );
            $btn_resub   = __( 'Re-subscribe to reminders', 'volunteer-exchange-platform' );
            $btn_unsub   = __( 'Unsubscribe from reminders', 'volunteer-exchange-platform' );
        }

        ob_start();
        ?>
        <div class="vep-unsubscribe-page">
            <h1 class="vep-page-title"><?php echo esc_html( $title ); ?></h1>

            <div id="vep-unsubscribe-message" class="vep-unsubscribe-status-message <?php echo $is_unsubscribed ? 'vep-unsubscribed' : 'vep-subscribed'; ?>">
                <?php echo esc_html( $is_unsubscribed ? $msg_unsub : $msg_sub ); ?>
            </div>

            <div id="vep-unsubscribe-ajax-message" class="vep-ajax-feedback" style="display:none;" aria-live="polite"></div>

            <button
                type="button"
                id="vep-resubscribe-btn"
                class="vep-button vep-button-secondary"
                data-randon-key="<?php echo esc_attr( $randon_key ); ?>"
                data-unsubscribe-type="<?php echo esc_attr( $type ); ?>"
                style="<?php echo $is_unsubscribed ? '' : 'display:none;'; ?>"
            >
                <?php echo esc_html( $btn_resub ); ?>
            </button>

            <?php if ( ! $is_unsubscribed ) : ?>
                <form method="post" action="" id="vep-unsubscribe-form" class="vep-unsubscribe-form">
                    <input type="hidden" name="vep_unsubscribe_confirm" value="1">
                    <button type="submit" class="vep-button vep-button-danger">
                        <?php echo esc_html( $btn_unsub ); ?>
                    </button>
                </form>
            <?php endif; ?>

            <button
                type="button"
                id="vep-unsubscribe-btn"
                class="vep-button vep-button-danger"
                data-randon-key="<?php echo esc_attr( $randon_key ); ?>"
                data-unsubscribe-type="<?php echo esc_attr( $type ); ?>"
                style="display:none;"
            >
                <?php echo esc_html( $btn_unsub ); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get unsubscribe URL for a participant key and type.
     *
     * Works with pretty and plain permalinks.
     *
     * @param string $randon_key Participant key.
     * @param string $type       Type slug (see self::TYPES). Defaults to participant reminders.
     * @return string
     */
    public static function get_unsubscribe_url( $randon_key, $type = self::DEFAULT_TYPE ) {
        $randon_key = sanitize_text_field( (string) $randon_key );
        $type       = isset( self::TYPES[ $type ] ) ? $type : self::DEFAULT_TYPE;

        if ( empty( get_option( 'permalink_structure' ) ) ) {
            return add_query_arg(
                array(
                    'vep_unsubscribe_type' => $type,
                    'vep_unsubscribe_key'  => rawurlencode( $randon_key ),
                ),
                home_url( '/' )
            );
        }

        return home_url( user_trailingslashit( 'vep/afmeld/' . $type . '/' . rawurlencode( $randon_key ) ) );
    }
}
