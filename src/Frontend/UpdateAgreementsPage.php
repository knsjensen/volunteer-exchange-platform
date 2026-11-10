<?php
/**
 * Handles the key-based participant agreements update page.
 *
 * @package VEP
 * @subpackage Frontend
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Frontend;

use VolunteerExchangePlatform\Services\AgreementStatus;
use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateAgreementsPage {

    /** @var ParticipantService */
    private $participant_service;

    /** @var EventService */
    private $event_service;

    /**
     * Constructor.
     *
     * @param ParticipantService|null $participant_service Participant service.
     * @param EventService|null       $event_service       Event service.
     */
    public function __construct(
        ?ParticipantService $participant_service = null,
        ?EventService $event_service = null
    ) {
        $this->participant_service = $participant_service ?: new ParticipantService();
        $this->event_service       = $event_service ?: new EventService();

        add_action( 'init',              array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars',        array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_page' ) );
    }

    /**
     * Register rewrite rules for both English and Danish slugs.
     *
     * @return void
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^vep/update-agreements/([^/]+)/?$',
            'index.php?vep_update_agreements_key=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^vep/opdateraftaler/([^/]+)/?$',
            'index.php?vep_update_agreements_key=$matches[1]',
            'top'
        );
    }

    /**
     * Register custom query var.
     *
     * @param array $vars Existing query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'vep_update_agreements_key';
        return $vars;
    }

    /**
     * Handle page rendering.
     *
     * @return void
     */
    public function handle_page() {
        $randon_key = get_query_var( 'vep_update_agreements_key' );
        if ( ! $randon_key ) {
            return;
        }

        $randon_key  = sanitize_text_field( (string) $randon_key );
        $participant = $this->participant_service->get_by_randon_key( $randon_key );

        nocache_headers();
        get_header();
        echo '<div class="vep-page-wrapper">';

        if ( ! $participant ) {
            echo '<div class="vep-agreements-page"><div class="vep-message vep-error">' .
                esc_html__( 'Participant not found.', 'volunteer-exchange-platform' ) .
                '</div></div>';
        } else {
            echo $this->render( $participant, $randon_key ); // Output is escaped within render().
        }

        echo '</div>';
        get_footer();
        exit;
    }

    /**
     * Render the agreements page content.
     *
     * @param object $participant Participant row.
     * @param string $randon_key  Participant key.
     * @return string
     */
    private function render( $participant, $randon_key ) {
        $agreements      = $this->participant_service->get_agreements_for_participant( (int) $participant->id );
        $is_unsubscribed = ! empty( $participant->agreement_overview_unsubscribed );
        $event           = isset( $participant->event_id ) ? $this->event_service->get_by_id( (int) $participant->event_id ) : null;
        $event_name      = $event ? ( $event->name ?? '' ) : '';

        $status_labels = AgreementStatus::labels();

        ob_start();
        ?>
        <div class="vep-agreements-page" data-randon-key="<?php echo esc_attr( $randon_key ); ?>">
            <h1 class="vep-page-title">
                <?php
                if ( $event_name !== '' ) {
                    /* translators: %s: event name */
                    echo esc_html( sprintf( __( 'My exchange agreements from %s', 'volunteer-exchange-platform' ), $event_name ) );
                } else {
                    esc_html_e( 'My exchange agreements', 'volunteer-exchange-platform' );
                }
                ?>
            </h1>

            <?php if ( empty( $agreements ) ) : ?>
                <p class="vep-no-agreements"><?php esc_html_e( 'No agreements found.', 'volunteer-exchange-platform' ); ?></p>
            <?php else : ?>
                <div class="vep-agreements-grid">
                    <?php foreach ( $agreements as $agreement ) : ?>
                        <?php
                        $partner_name   = isset( $agreement->other_participant_name ) ? (string) $agreement->other_participant_name : '—';
                        $description    = isset( $agreement->description ) ? (string) $agreement->description : '';
                        $current_status = isset( $agreement->status ) ? (string) $agreement->status : AgreementStatus::default_status();
                        if ( ! array_key_exists( $current_status, $status_labels ) ) {
                            $current_status = AgreementStatus::default_status();
                        }
                        ?>
                        <div class="vep-agreement-card" data-agreement-id="<?php echo esc_attr( (int) $agreement->id ); ?>">
                            <div class="vep-agreement-card-header">
                                <span class="vep-agreement-partner"><?php
                                /* translators: %s: partner organization name */
                                echo esc_html( sprintf( __( 'Exchange agreement with %s', 'volunteer-exchange-platform' ), $partner_name ) );
                                ?></span>
                                <span class="vep-agreement-save-indicator" aria-live="polite"></span>
                            </div>

                            <?php if ( '' !== $description ) : ?>
                                <div class="vep-agreement-description">
                                    <?php echo nl2br( esc_html( $description ) ); ?>
                                </div>
                            <?php endif; ?>

                            <div class="vep-agreement-status-row">
                                <label class="vep-agreement-status-label" for="vep-status-<?php echo esc_attr( (int) $agreement->id ); ?>">
                                    <?php esc_html_e( 'Status', 'volunteer-exchange-platform' ); ?>
                                </label>
                                <select
                                    id="vep-status-<?php echo esc_attr( (int) $agreement->id ); ?>"
                                    class="vep-agreement-status-select"
                                    data-agreement-id="<?php echo esc_attr( (int) $agreement->id ); ?>"
                                >
                                    <?php foreach ( $status_labels as $value => $label ) : ?>
                                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="vep-agreements-footer">
                <div id="vep-agreements-unsub-message" class="vep-ajax-feedback" style="display:none;" aria-live="polite"></div>

                <div id="vep-agreements-sub-state" class="vep-unsub-state" <?php echo $is_unsubscribed ? 'style="display:none;"' : ''; ?>>
                    <p class="vep-unsub-label"><?php esc_html_e( 'Unsubscribe from agreement overview emails', 'volunteer-exchange-platform' ); ?></p>
                    <button
                        type="button"
                        id="vep-agreements-unsubscribe-btn"
                        class="vep-button vep-button-danger"
                        data-randon-key="<?php echo esc_attr( $randon_key ); ?>"
                        data-unsubscribe-type="aftale-paamindelse"
                    >
                        <?php esc_html_e( 'Unsubscribe', 'volunteer-exchange-platform' ); ?>
                    </button>
                </div>

                <div id="vep-agreements-unsub-state" class="vep-unsub-state" <?php echo $is_unsubscribed ? '' : 'style="display:none;"'; ?>>
                    <p class="vep-unsub-label"><?php esc_html_e( 'You are unsubscribed from agreement overview emails.', 'volunteer-exchange-platform' ); ?></p>
                    <button
                        type="button"
                        id="vep-agreements-resubscribe-btn"
                        class="vep-button vep-button-secondary"
                        data-randon-key="<?php echo esc_attr( $randon_key ); ?>"
                        data-unsubscribe-type="aftale-paamindelse"
                    >
                        <?php esc_html_e( 'Re-subscribe', 'volunteer-exchange-platform' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get the update-agreements URL for a participant key.
     *
     * Works with pretty and plain permalinks.
     *
     * @param string $randon_key Participant key.
     * @return string
     */
    public static function get_update_agreements_url( $randon_key ) {
        $randon_key = sanitize_text_field( (string) $randon_key );

        if ( empty( get_option( 'permalink_structure' ) ) ) {
            return add_query_arg( 'vep_update_agreements_key', rawurlencode( $randon_key ), home_url( '/' ) );
        }

        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
        $slug   = ( is_string( $locale ) && 0 === strpos( strtolower( $locale ), 'da' ) ) ? 'opdateraftaler' : 'update-agreements';

        return home_url( user_trailingslashit( 'vep/' . $slug . '/' . rawurlencode( $randon_key ) ) );
    }
}
