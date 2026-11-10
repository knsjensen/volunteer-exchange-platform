<?php
/**
 * AJAX handler for reloading the event participants list table (admin).
 */
namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Admin\EventParticipantsListTable;
use VolunteerExchangePlatform\Services\ParticipantEmailService;
use VolunteerExchangePlatform\Services\ParticipantService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EventParticipantsTableHandler {
    public function __construct() {
        add_action( 'wp_ajax_vep_reload_event_participants_table', array( $this, 'handle_reload' ) );
    }

    public function handle_reload() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Not allowed' );
        }

        $event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
        if ( ! $event_id ) {
            wp_send_json_error( 'Missing event_id' );
        }

        // Inject navigation params into $_GET so WP_List_Table::prepare_items() picks them up.
        $_GET['page']   = 'volunteer-exchange-events';
        $_GET['action'] = 'view';
        $_GET['id']     = $event_id;

        $nav_params = array( 'per_page', 'paged', 'approval_status', 'orderby', 'order' );
        foreach ( $nav_params as $param ) {
            if ( isset( $_POST[ $param ] ) && '' !== $_POST[ $param ] ) {
                $value              = sanitize_key( wp_unslash( $_POST[ $param ] ) );
                $_GET[ $param ]     = $value;
                $_REQUEST[ $param ] = $value; // Needed for WP_List_Table::get_pagenum().
            }
        }

        $message = '';
        $service = new ParticipantService();
        $email_service = new ParticipantEmailService( $service );

        // --- Handle single participant quick-actions (approve, unapprove, delete, send_reminder) ---
        $participant_action = isset( $_POST['participant_action'] ) ? sanitize_key( wp_unslash( $_POST['participant_action'] ) ) : '';
        $participant_id     = isset( $_POST['participant_id'] )     ? absint( $_POST['participant_id'] )     : 0;
        $nonce              = isset( $_POST['_wpnonce'] )           ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

        if ( $participant_action && $participant_id > 0 ) {
            if ( ! wp_verify_nonce( $nonce, 'vep_participant_' . $participant_action . '_' . $participant_id ) ) {
                wp_send_json_error( 'Security check failed' );
            }
            switch ( $participant_action ) {
                case 'approve':
                    $service->approve_participant( $participant_id );
                    $message = __( 'Participant approved.', 'volunteer-exchange-platform' );
                    break;
                case 'unapprove':
                    $service->unapprove_participant( $participant_id );
                    $message = __( 'Approval removed.', 'volunteer-exchange-platform' );
                    break;
                case 'delete':
                    $service->delete_participant( $participant_id );
                    $message = __( 'Participant deleted.', 'volunteer-exchange-platform' );
                    break;
                case 'send_reminder':
                    if ( $email_service->queue_update_participant_reminder( $participant_id ) ) {
                        $message = __( 'Reminder queued.', 'volunteer-exchange-platform' );
                    } else {
                        $message = __( 'Reminder was not queued (participant is not approved, has no email, has unsubscribed, or has nothing missing).', 'volunteer-exchange-platform' );
                    }
                    break;
            }
        }

        // --- Handle bulk actions (bulk_approve, bulk_delete) ---
        $bulk_action       = isset( $_POST['bulk_action'] )      ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
        $participants_list = isset( $_POST['participants_list'] ) ? sanitize_text_field( wp_unslash( $_POST['participants_list'] ) ) : '';
        $raw_participants  = $participants_list !== '' ? array_filter( array_map( 'absint', explode( ',', $participants_list ) ) ) : array();

        if ( in_array( $bulk_action, array( 'bulk_approve', 'bulk_delete' ), true ) && ! empty( $raw_participants ) ) {
            if ( ! wp_verify_nonce( $nonce, 'bulk-participants' ) ) {
                wp_send_json_error( 'Security check failed' );
            }
            $processed = 0;
            foreach ( array_map( 'absint', $raw_participants ) as $pid ) {
                if ( $pid <= 0 ) continue;
                if ( 'bulk_approve' === $bulk_action ) {
                    $service->approve_participant( $pid );
                } else {
                    $service->delete_participant( $pid );
                }
                $processed++;
            }
            if ( $processed > 0 ) {
                if ( 'bulk_approve' === $bulk_action ) {
                    /* translators: %d = number of participants */
                    $message = $processed === 1
                        ? __( '1 participant approved.', 'volunteer-exchange-platform' )
                        : sprintf( __( '%d participants approved.', 'volunteer-exchange-platform' ), $processed );
                } else {
                    /* translators: %d = number of participants */
                    $message = $processed === 1
                        ? __( '1 participant deleted.', 'volunteer-exchange-platform' )
                        : sprintf( __( '%d participants deleted.', 'volunteer-exchange-platform' ), $processed );
                }
            }
        }

        // --- Render the refreshed section and return as JSON ---
        $table = new EventParticipantsListTable( $event_id );
        $table->prepare_items();

        ob_start();
        ?>
        <div id="vep-event-participants-section">
            <?php $table->views(); ?>
            <form method="get" id="vep-event-participants-form" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page"   value="volunteer-exchange-events">
                <input type="hidden" name="action" value="view">
                <input type="hidden" name="id"     value="<?php echo esc_attr( (string) $event_id ); ?>">
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html, 'message' => $message ) );
    }
}
