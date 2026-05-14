<?php
/**
 * Competitions admin page
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\CompetitionService;
use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Competitions admin page
 *
 * @package VolunteerExchangePlatform\Admin
 */
class CompetitionsPage {
    
    /**
     * Competition service
     *
     * @var CompetitionService
     */
    private $competition_service;
    
    /**
     * Event service
     *
     * @var EventService
     */
    private $event_service;
    
    /**
     * Participant service
     *
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * Constructor
     *
     * @param CompetitionService|null $competition_service Competition service
     * @param EventService|null       $event_service Event service
     * @param ParticipantService|null $participant_service Participant service
     */
    public function __construct(
        ?CompetitionService $competition_service = null,
        ?EventService $event_service = null,
        ?ParticipantService $participant_service = null
    ) {
        $this->competition_service = $competition_service ?: new CompetitionService();
        $this->event_service = $event_service ?: new EventService();
        $this->participant_service = $participant_service ?: new ParticipantService();

        add_action( 'admin_post_vep_save_competition', array( $this, 'handle_form_submission' ) );
        add_action( 'admin_post_vep_delete_competition', array( $this, 'handle_delete_submission' ) );
        add_action( 'wp_ajax_vep_set_competition_winner', array( $this, 'handle_set_competition_winner' ) );
    }

    /**
     * Render competitions page
     *
     * @return void
     */
    public function render() {
        $active_event = $this->event_service->get_active_event();

        if ( ! $active_event ) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Competitions', 'volunteer-exchange-platform' ); ?></h1>
                <div class="notice notice-warning">
                    <p><?php esc_html_e( 'No active event. Please activate an event first.', 'volunteer-exchange-platform' ); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        $this->competition_service->ensure_default_competitions();
        $this->competition_service->auto_select_winners_for_event( $active_event->id );

        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
        $competition_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

        switch ( $action ) {
            case 'add':
                $this->render_add_page( $active_event );
                break;
            case 'edit':
                if ( $competition_id ) {
                    $this->render_edit_page( $competition_id, $active_event );
                } else {
                    $this->render_list_page( $active_event );
                }
                break;
            default:
                $this->render_list_page( $active_event );
                break;
        }
    }

    /**
     * Render list page with active and inactive competitions
     *
     * @param object $active_event Active event object
     * @return void
     */
    private function render_list_page( $active_event ) {
        $active_competitions = $this->competition_service->get_active_competitions( $active_event->id );
        $inactive_competitions = $this->competition_service->get_inactive_competitions( $active_event->id );
        $participants = $this->participant_service->get_approved_for_event_with_type( $active_event->id );
        $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
        $notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : 'success';

        ?>
        <div class="wrap vep-competitions-page" data-competition-nonce="<?php echo esc_attr( wp_create_nonce( 'vep_competitions_nonce' ) ); ?>">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Competitions', 'volunteer-exchange-platform' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-competitions&action=add' ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Add New', 'volunteer-exchange-platform' ); ?>
            </a>
            <hr class="wp-header-end">

            <?php if ( '' !== $message ) : ?>
                <div class="notice notice-<?php echo esc_attr( in_array( $notice, array( 'success', 'error', 'warning', 'info' ), true ) ? $notice : 'success' ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>

            <!-- Active Competitions -->
            <h2><?php esc_html_e( 'Active Competitions', 'volunteer-exchange-platform' ); ?></h2>
            <div class="vep-competitions-grid" id="vep-active-competitions">
                <?php
                $index = 1;
                foreach ( $active_competitions as $competition ) {
                    $competition = $this->competition_service->get_competition_with_label( $competition );
                    $can_delete = $this->competition_service->can_delete_competition( $competition->id );
                    ?>
                    <div class="vep-competition-card<?php echo $this->has_winner( $competition ) ? '' : ' vep-competition-no-winner'; ?>" data-competition-id="<?php echo intval( $competition->id ); ?>">
                        <div class="vep-competition-header">
                            <span class="dashicons dashicons-move vep-competition-drag-handle" aria-hidden="true"></span>
                            <span class="vep-competition-number"><?php echo intval( $index ); ?></span>
                            <span class="vep-competition-title"><?php echo esc_html( $competition->title ); ?></span>
                        </div>
                        <div class="vep-competition-type"><?php echo esc_html( $competition->type_label ); ?></div>
                        <?php if ( ! empty( $competition->description ) ) : ?>
                            <div class="vep-competition-description"><?php echo esc_html( $competition->description ); ?></div>
                        <?php endif; ?>
                        <div class="vep-competition-winner">
                            <?php
                            if ( 'custom' === $competition->type ) {
                                echo $this->get_winner_selector_html( $competition, $participants ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is properly escaped in method
                            } else {
                                echo wp_kses_post( $this->get_winner_html( $competition ) );
                            }
                            ?>
                        </div>
                        <div class="vep-competition-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-competitions&action=edit&id=' . $competition->id ) ); ?>" class="button button-small">
                                <?php esc_html_e( 'Edit', 'volunteer-exchange-platform' ); ?>
                            </a>
                            <button
                                class="button button-small vep-reset-winner"
                                data-competition-id="<?php echo intval( $competition->id ); ?>"
                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'vep_set_competition_winner_' . $competition->id ) ); ?>"
                            >
                                <?php esc_html_e( 'Reset Winner', 'volunteer-exchange-platform' ); ?>
                            </button>
                            <button class="button button-small vep-toggle-active" data-competition-id="<?php echo intval( $competition->id ); ?>" data-action="deactivate">
                                <?php esc_html_e( 'Deactivate', 'volunteer-exchange-platform' ); ?>
                            </button>
                            <?php if ( $can_delete ) : ?>
                                <button class="button button-small button-link-delete vep-delete-competition" data-competition-id="<?php echo intval( $competition->id ); ?>">
                                    <?php esc_html_e( 'Delete', 'volunteer-exchange-platform' ); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    $index++;
                }
                ?>
            </div>

            <!-- Inactive Competitions -->
            <?php if ( ! empty( $inactive_competitions ) ) : ?>
                <h2><?php esc_html_e( 'Inactive Competitions', 'volunteer-exchange-platform' ); ?></h2>
                <div class="vep-competitions-grid vep-competitions-inactive">
                    <?php
                    foreach ( $inactive_competitions as $competition ) {
                        $competition = $this->competition_service->get_competition_with_label( $competition );
                        $can_delete = $this->competition_service->can_delete_competition( $competition->id );
                        ?>
                        <div class="vep-competition-card vep-competition-inactive" data-competition-id="<?php echo intval( $competition->id ); ?>">
                            <div class="vep-competition-header">
                                <span class="vep-competition-title"><?php echo esc_html( $competition->title ); ?></span>
                            </div>
                            <div class="vep-competition-type"><?php echo esc_html( $competition->type_label ); ?></div>
                            <?php if ( ! empty( $competition->description ) ) : ?>
                                <div class="vep-competition-description"><?php echo esc_html( $competition->description ); ?></div>
                            <?php endif; ?>
                            <div class="vep-competition-winner">
                                <?php
                                if ( 'custom' === $competition->type ) {
                                    echo $this->get_winner_selector_html( $competition, $participants ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is properly escaped in method
                                } else {
                                    echo wp_kses_post( $this->get_winner_html( $competition ) );
                                }
                                ?>
                            </div>
                            <div class="vep-competition-actions">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-competitions&action=edit&id=' . $competition->id ) ); ?>" class="button button-small">
                                    <?php esc_html_e( 'Edit', 'volunteer-exchange-platform' ); ?>
                                </a>
                                <button
                                    class="button button-small vep-reset-winner"
                                    data-competition-id="<?php echo intval( $competition->id ); ?>"
                                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'vep_set_competition_winner_' . $competition->id ) ); ?>"
                                >
                                    <?php esc_html_e( 'Reset Winner', 'volunteer-exchange-platform' ); ?>
                                </button>
                                <button class="button button-small vep-toggle-active" data-competition-id="<?php echo intval( $competition->id ); ?>" data-action="activate">
                                    <?php esc_html_e( 'Activate', 'volunteer-exchange-platform' ); ?>
                                </button>
                                <?php if ( $can_delete ) : ?>
                                    <button class="button button-small button-link-delete vep-delete-competition" data-competition-id="<?php echo intval( $competition->id ); ?>">
                                        <?php esc_html_e( 'Delete', 'volunteer-exchange-platform' ); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            <?php endif; ?>

            <hr>
            <p>
                <button
                    type="button"
                    class="button button-link-delete vep-reset-all-winners"
                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'vep_competitions_nonce' ) ); ?>"
                >
                    <?php esc_html_e( 'Reset All Winners', 'volunteer-exchange-platform' ); ?>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * Handle setting competition winner via AJAX
     *
     * @return void
     */
    public function handle_set_competition_winner() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'volunteer-exchange-platform' ) ) );
        }

        $competition_id = isset( $_POST['competition_id'] ) ? (int) $_POST['competition_id'] : 0;
        $winner_id = isset( $_POST['winner_id'] ) ? (int) $_POST['winner_id'] : 0;
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( ! $competition_id || ! wp_verify_nonce( $nonce, 'vep_set_competition_winner_' . $competition_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'volunteer-exchange-platform' ) ) );
        }

        $active_event = $this->event_service->get_active_event();
        $event_id     = $active_event ? (int) $active_event->id : 0;

        $updated = $this->competition_service->set_winner( $competition_id, $winner_id > 0 ? $winner_id : null, $event_id );

        if ( false !== $updated ) {
            wp_send_json_success( array( 'message' => esc_html__( 'Winner set successfully', 'volunteer-exchange-platform' ) ) );
        } else {
            wp_send_json_error( array( 'message' => esc_html__( 'Failed to set winner', 'volunteer-exchange-platform' ) ) );
        }
    }

    /**
     * Handle AJAX toggle of competition active state.
     *
     * @return void
     */
    public function handle_toggle_competition_active() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'volunteer-exchange-platform' ) ) );
        }

        $competition_id = isset( $_POST['competition_id'] ) ? (int) $_POST['competition_id'] : 0;
        $action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( ! $competition_id || ! wp_verify_nonce( $nonce, 'vep_competitions_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'volunteer-exchange-platform' ) ) );
        }

        $updated = ( 'activate' === $action_type )
            ? $this->competition_service->set_active( $competition_id )
            : $this->competition_service->set_inactive( $competition_id );

        if ( false !== $updated ) {
            wp_send_json_success( array( 'message' => esc_html__( 'Competition updated', 'volunteer-exchange-platform' ) ) );
        }

        wp_send_json_error( array( 'message' => esc_html__( 'Failed to update competition', 'volunteer-exchange-platform' ) ) );
    }

    /**
     * Handle AJAX deletion of a custom competition.
     *
     * @return void
     */
    public function handle_delete_competition_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'volunteer-exchange-platform' ) ) );
        }

        $competition_id = isset( $_POST['competition_id'] ) ? (int) $_POST['competition_id'] : 0;
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( ! $competition_id || ! wp_verify_nonce( $nonce, 'vep_competitions_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'volunteer-exchange-platform' ) ) );
        }

        if ( ! $this->competition_service->can_delete_competition( $competition_id ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'This competition cannot be deleted', 'volunteer-exchange-platform' ) ) );
        }

        if ( $this->competition_service->delete_competition( $competition_id ) ) {
            wp_send_json_success( array( 'message' => esc_html__( 'Competition deleted successfully', 'volunteer-exchange-platform' ) ) );
        }

        wp_send_json_error( array( 'message' => esc_html__( 'Failed to delete competition', 'volunteer-exchange-platform' ) ) );
    }

    /**
     * Handle AJAX reorder of active competitions.
     *
     * @return void
     */
    public function handle_reorder_competitions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'volunteer-exchange-platform' ) ) );
        }

        $order = isset( $_POST['order'] ) ? (array) $_POST['order'] : array();
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'vep_competitions_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'volunteer-exchange-platform' ) ) );
        }

        if ( empty( $order ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'No competitions provided', 'volunteer-exchange-platform' ) ) );
        }

        $order_map = array();

        foreach ( array_values( $order ) as $index => $competition_id ) {
            $competition_id = (int) $competition_id;

            if ( $competition_id > 0 ) {
                $order_map[ $competition_id ] = $index + 1;
            }
        }

        if ( empty( $order_map ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'No competitions provided', 'volunteer-exchange-platform' ) ) );
        }

        if ( $this->competition_service->reorder_competitions( $order_map ) ) {
            wp_send_json_success( array( 'message' => esc_html__( 'Competitions reordered', 'volunteer-exchange-platform' ) ) );
        }

        wp_send_json_error( array( 'message' => esc_html__( 'Failed to reorder competitions', 'volunteer-exchange-platform' ) ) );
    }

    /**
     * Render add competition page
     *
     * @param object $active_event Active event object
     * @return void
     */
    private function render_add_page( $active_event ) {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Add New Competition', 'volunteer-exchange-platform' ); ?></h1>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="vep_save_competition">
                <?php wp_nonce_field( 'vep_save_competition_new' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="new_title"><?php esc_html_e( 'Title', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <input type="text" name="title" id="new_title" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_description"><?php esc_html_e( 'Description', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <textarea name="description" id="new_description" class="large-text" rows="4"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="new_winner_input_type"><?php esc_html_e( 'Winner Input Type', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <select name="winner_input_type" id="new_winner_input_type" class="regular-text">
                                <option value="dropdown"><?php esc_html_e( 'Dropdown (participant)', 'volunteer-exchange-platform' ); ?></option>
                                <option value="text"><?php esc_html_e( 'Text field (free text)', 'volunteer-exchange-platform' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <div class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Add Competition', 'volunteer-exchange-platform' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-competitions' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'volunteer-exchange-platform' ); ?></a>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render edit page
     *
     * @param int    $competition_id Competition ID
     * @param object $active_event Active event object
     * @return void
     */
    private function render_edit_page( $competition_id, $active_event ) {
        $competition_repo = new \VolunteerExchangePlatform\Database\CompetitionRepository();
        $competition = $competition_repo->get_by_id( $competition_id );

        if ( ! $competition ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Competition not found.', 'volunteer-exchange-platform' ) . '</p></div>';
            return;
        }

        $participants = $this->participant_service->get_approved_for_event_with_type( $active_event->id );
        $competition = $this->competition_service->get_competition_with_label( $competition );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $competition->title ); ?></h1>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vep-competition-edit-page">
                <input type="hidden" name="action" value="vep_save_competition">
                <input type="hidden" name="competition_id" value="<?php echo intval( $competition->id ); ?>">
                <?php wp_nonce_field( 'vep_save_competition_' . $competition->id ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="title"><?php esc_html_e( 'Title', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <input type="text" name="title" id="title" value="<?php echo esc_attr( $competition->title ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description"><?php esc_html_e( 'Description', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <textarea name="description" id="description" class="large-text" rows="4"><?php echo esc_textarea( $competition->description ?? '' ); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="type"><?php esc_html_e( 'Type', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <input type="text" value="<?php echo esc_attr( $competition->type_label ); ?>" class="regular-text" disabled>
                        </td>
                    </tr>
                    <?php if ( 'custom' === $competition->type ) : ?>
                    <tr>
                        <th scope="row"><label for="winner_input_type"><?php esc_html_e( 'Winner Input Type', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <select name="winner_input_type" id="winner_input_type" class="regular-text">
                                <option value="dropdown" <?php selected( ( $competition->winner_input_type ?? 'dropdown' ), 'dropdown' ); ?>><?php esc_html_e( 'Dropdown (participant)', 'volunteer-exchange-platform' ); ?></option>
                                <option value="text" <?php selected( ( $competition->winner_input_type ?? 'dropdown' ), 'text' ); ?>><?php esc_html_e( 'Text field (free text)', 'volunteer-exchange-platform' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr id="vep-winner-dropdown-row" <?php echo ( 'custom' === $competition->type && ( $competition->winner_input_type ?? 'dropdown' ) === 'text' ) ? 'style="display:none"' : ''; ?>>
                        <th scope="row"><label for="winner_id"><?php esc_html_e( 'Winner', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <div class="vep-competition-winner-field">
                                <select name="winner_id" id="winner_id" class="regular-text vep-choices">
                                    <option value=""><?php esc_html_e( 'Automatic', 'volunteer-exchange-platform' ); ?></option>
                                    <?php foreach ( $participants as $participant ) : ?>
                                        <option value="<?php echo intval( $participant->id ); ?>" <?php selected( $competition->winner_id, $participant->id ); ?>>
                                            <?php echo esc_html( $participant->organization_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </td>
                    </tr>
                    <?php if ( 'custom' === $competition->type ) : ?>
                    <tr id="vep-winner-text-row" <?php echo ( ( $competition->winner_input_type ?? 'dropdown' ) !== 'text' ) ? 'style="display:none"' : ''; ?>>
                        <th scope="row"><label for="winner_text"><?php esc_html_e( 'Winner', 'volunteer-exchange-platform' ); ?></label></th>
                        <td>
                            <input type="text" name="winner_text" id="winner_text" value="<?php echo esc_attr( $competition->winner_text ?? '' ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Status', 'volunteer-exchange-platform' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" value="1" <?php checked( $competition->is_active, 1 ); ?>>
                                <?php esc_html_e( 'Active', 'volunteer-exchange-platform' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <script>
                (function() {
                    var typeSelect = document.getElementById('winner_input_type');
                    var dropdownRow = document.getElementById('vep-winner-dropdown-row');
                    var textRow = document.getElementById('vep-winner-text-row');
                    if (typeSelect && dropdownRow && textRow) {
                        typeSelect.addEventListener('change', function() {
                            if (this.value === 'text') {
                                dropdownRow.style.display = 'none';
                                textRow.style.display = '';
                            } else {
                                dropdownRow.style.display = '';
                                textRow.style.display = 'none';
                            }
                        });
                    }
                })();
                </script>

                <div class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'volunteer-exchange-platform' ); ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=volunteer-exchange-competitions' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'volunteer-exchange-platform' ); ?></a>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Handle form submission
     *
     * @return void
     */
    public function handle_form_submission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'volunteer-exchange-platform' ) );
        }

        $active_event = $this->event_service->get_active_event();

        if ( ! $active_event ) {
            wp_die( esc_html__( 'No active event', 'volunteer-exchange-platform' ) );
        }

        $competition_id = isset( $_POST['competition_id'] ) ? (int) $_POST['competition_id'] : 0;

        if ( $competition_id > 0 ) {
            check_admin_referer( 'vep_save_competition_' . $competition_id );
        } else {
            check_admin_referer( 'vep_save_competition_new' );
        }

        $title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $description  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
        $winner_id    = isset( $_POST['winner_id'] ) ? (int) $_POST['winner_id'] : null;
        $is_active    = isset( $_POST['is_active'] ) ? 1 : 0;
        $winner_input_type_raw = isset( $_POST['winner_input_type'] ) ? sanitize_text_field( wp_unslash( $_POST['winner_input_type'] ) ) : 'dropdown';
        $winner_input_type = in_array( $winner_input_type_raw, array( 'dropdown', 'text' ), true ) ? $winner_input_type_raw : 'dropdown';
        $winner_text  = isset( $_POST['winner_text'] ) ? sanitize_text_field( wp_unslash( $_POST['winner_text'] ) ) : '';

        if ( ! $title ) {
            wp_die( esc_html__( 'Title is required', 'volunteer-exchange-platform' ) );
        }

        if ( $competition_id > 0 ) {
            $updated = $this->competition_service->update_competition(
                $competition_id,
                array(
                    'title'             => $title,
                    'description'       => $description,
                    'winner_input_type' => $winner_input_type,
                    'is_active'         => $is_active,
                )
            );

            if ( $updated ) {
                if ( 'text' === $winner_input_type ) {
                    $this->competition_service->set_winner_text( $competition_id, $winner_text, $active_event->id );
                } else {
                    $this->competition_service->set_winner( $competition_id, $winner_id > 0 ? $winner_id : null, $active_event->id );
                }
            }

            $notice = $updated ? 'success' : 'error';
            $message = $updated
                ? __( 'Competition updated successfully.', 'volunteer-exchange-platform' )
                : __( 'Failed to update competition.', 'volunteer-exchange-platform' );

            wp_safe_redirect( admin_url( 'admin.php?page=volunteer-exchange-competitions&notice=' . $notice . '&message=' . urlencode( $message ) ) );
            exit;
        }

        $competition_id = $this->competition_service->create_competition( array(
            'type'              => 'custom',
            'title'             => $title,
            'description'       => $description,
            'is_active'         => 1,
            'winner_input_type' => $winner_input_type,
        ) );

        if ( $competition_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=volunteer-exchange-competitions&notice=success&message=' . urlencode( __( 'Competition created successfully.', 'volunteer-exchange-platform' ) ) ) );
            exit;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=volunteer-exchange-competitions&notice=error&message=' . urlencode( __( 'Failed to create competition.', 'volunteer-exchange-platform' ) ) ) );
        exit;
    }

    /**
     * Handle delete submission.
     *
     * @return void
     */
    public function handle_delete_submission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'volunteer-exchange-platform' ) );
        }

        $competition_id = isset( $_POST['competition_id'] ) ? (int) $_POST['competition_id'] : 0;

        if ( $competition_id <= 0 ) {
            wp_safe_redirect( admin_url( 'admin.php?page=volunteer-exchange-competitions&notice=error&message=' . urlencode( __( 'Competition not found.', 'volunteer-exchange-platform' ) ) ) );
            exit;
        }

        check_admin_referer( 'vep_delete_competition_' . $competition_id );

        $deleted = $this->competition_service->delete_competition( $competition_id );
        $notice = $deleted ? 'success' : 'error';
        $message = $deleted
            ? __( 'Competition deleted successfully', 'volunteer-exchange-platform' )
            : __( 'Failed to delete competition', 'volunteer-exchange-platform' );

        wp_safe_redirect( admin_url( 'admin.php?page=volunteer-exchange-competitions&notice=' . $notice . '&message=' . urlencode( $message ) ) );
        exit;
    }

    /**
     * Render competition winner summary.
     *
     * @param object $competition Competition object.
     * @return string
     */
    /**
     * Get winner selector HTML for custom competitions
     *
     * @param object $competition Competition object
     * @param array  $participants List of participant objects
     * @return string
     */
    private function get_winner_selector_html( $competition, $participants ) {
        $nonce            = wp_create_nonce( 'vep_set_competition_winner_' . $competition->id );
        $winner_input_type = isset( $competition->winner_input_type ) ? $competition->winner_input_type : 'dropdown';

        if ( 'text' === $winner_input_type ) {
            $current_text = isset( $competition->winner_text ) ? $competition->winner_text : '';
            $html  = '<div class="vep-competition-winner-text-wrap">';
            $html .= '<input type="text" class="vep-competition-winner-text" value="' . esc_attr( $current_text ) . '" data-competition-id="' . intval( $competition->id ) . '" data-nonce="' . esc_attr( $nonce ) . '" placeholder="' . esc_attr__( 'Enter winner name', 'volunteer-exchange-platform' ) . '">';
            $html .= '<button type="button" class="button button-small vep-competition-winner-text-save" data-competition-id="' . intval( $competition->id ) . '">' . esc_html__( 'Save', 'volunteer-exchange-platform' ) . '</button>';
            $html .= '</div>';
            return $html;
        }

        $html  = '<select name="winner_id" class="vep-competition-winner-select vep-choices" data-competition-id="' . intval( $competition->id ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
        $html .= '<option value="">' . esc_html__( 'Select Winner', 'volunteer-exchange-platform' ) . '</option>';
        
        foreach ( $participants as $participant ) {
            $selected = ( (int) $competition->winner_id === (int) $participant->id ) ? ' selected' : '';
            $html .= '<option value="' . intval( $participant->id ) . '"' . $selected . '>' . esc_html( $participant->organization_name ) . '</option>';
        }
        
        $html .= '</select>';
        return $html;
    }

    /**
     * Render competition winner summary.
     *
     * @param object $competition Competition object.
     * @return string
     */
    /**
     * Determine whether a competition already has a winner assigned.
     *
     * @param object $competition Competition object.
     * @return bool
     */
    private function has_winner( $competition ): bool {
        if ( 'custom' === $competition->type
            && isset( $competition->winner_input_type )
            && 'text' === $competition->winner_input_type ) {
            return ! empty( $competition->winner_text );
        }
        return ! empty( $competition->winner_id );
    }

    private function get_winner_html( $competition ) {
        // For system competitions, show text display
        if ( empty( $competition->winner_id ) ) {
            return '<em>' . esc_html__( 'No winner set', 'volunteer-exchange-platform' ) . '</em>';
        }

        $winner = $this->participant_service->get_by_id( (int) $competition->winner_id );
        $winner_name = $winner && ! empty( $winner->organization_name ) ? $winner->organization_name : __( 'Unknown participant', 'volunteer-exchange-platform' );

        return '<strong>' . esc_html__( 'Winner:', 'volunteer-exchange-platform' ) . '</strong> ' . esc_html( $winner_name );
    }
}

