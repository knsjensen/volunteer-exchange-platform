<?php
/**
 * AJAX handler for reloading the participant agreements list table (admin).
 */
namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Admin\ParticipantAgreementsListTable;
use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParticipantAgreementsTableHandler {
    public function __construct() {
        add_action( 'wp_ajax_vep_reload_participant_agreements_table', array( $this, 'handle_reload' ) );
    }

    public function handle_reload() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Not allowed' );
        }

        $participant_id = isset( $_POST['participant_id'] ) ? absint( $_POST['participant_id'] ) : 0;
        $event_id       = isset( $_POST['event_id'] )       ? absint( $_POST['event_id'] )       : 0;

        if ( ! $participant_id ) {
            wp_send_json_error( 'Missing participant_id' );
        }

        // Inject GET params so ParticipantAgreementsListTable::prepare_items() picks them up.
        $_GET['page']      = 'volunteer-exchange-events';
        $_GET['action']    = 'view-participant';
        $_GET['id']        = $participant_id;
        $_GET['return_id'] = $event_id;

        $nav_params = array( 'participant_agreement_status', 'orderby', 'order' );
        foreach ( $nav_params as $param ) {
            if ( isset( $_POST[ $param ] ) && '' !== $_POST[ $param ] ) {
                $value              = sanitize_key( wp_unslash( $_POST[ $param ] ) );
                $_GET[ $param ]     = $value;
                $_REQUEST[ $param ] = $value;
            }
        }

        if ( isset( $_POST['agreement_s'] ) && '' !== $_POST['agreement_s'] ) {
            $s                       = sanitize_text_field( wp_unslash( $_POST['agreement_s'] ) );
            $_GET['agreement_s']     = $s;
            $_REQUEST['agreement_s'] = $s;
        }

        $message = '';

        // Handle delete action.
        $agreement_action = isset( $_POST['agreement_action'] ) ? sanitize_key( wp_unslash( $_POST['agreement_action'] ) ) : '';
        $agreement_id     = isset( $_POST['agreement_id'] )     ? absint( $_POST['agreement_id'] )                        : 0;
        $nonce            = isset( $_POST['_wpnonce'] )         ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) )  : '';

        if ( 'delete' === $agreement_action && $agreement_id > 0 ) {
            if ( ! wp_verify_nonce( $nonce, 'vep_agreement_delete_' . $agreement_id ) ) {
                wp_send_json_error( 'Security check failed' );
            }
            $event_service = new EventService();
            $result        = $event_service->delete_agreement( $agreement_id );
            $message       = $result
                ? __( 'Agreement deleted.', 'volunteer-exchange-platform' )
                : __( 'Could not delete agreement.', 'volunteer-exchange-platform' );
        }

        $service = new ParticipantService();
        $table   = new ParticipantAgreementsListTable( $participant_id, $event_id, $service );
        $table->prepare_items();

        ob_start();
        ?>
        <div id="vep-participant-agreements-section">
            <?php $table->views(); ?>
            <form method="get" id="vep-participant-agreements-form" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page"      value="volunteer-exchange-events">
                <input type="hidden" name="action"    value="view-participant">
                <input type="hidden" name="id"        value="<?php echo esc_attr( (string) $participant_id ); ?>">
                <input type="hidden" name="return_id" value="<?php echo esc_attr( (string) $event_id ); ?>">
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html, 'message' => $message ) );
    }
}
