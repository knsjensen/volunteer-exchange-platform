<?php
/**
 * AJAX handler for reloading the event agreements list table (admin).
 */
namespace VolunteerExchangePlatform\Ajax;

use VolunteerExchangePlatform\Admin\EventAgreementsListTable;
use VolunteerExchangePlatform\Services\EventService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EventAgreementsTableHandler {
    public function __construct() {
        add_action( 'wp_ajax_vep_reload_event_agreements_table', array( $this, 'handle_reload' ) );
    }

    public function handle_reload() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Not allowed' );
        }

        $event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
        if ( ! $event_id ) {
            wp_send_json_error( 'Missing event_id' );
        }

        // Inject navigation params into $_GET so EventAgreementsListTable::prepare_items() picks them up.
        // filter_input(INPUT_GET) reads the original request and ignores runtime assignments.
        $_GET['page']   = 'volunteer-exchange-events';
        $_GET['action'] = 'view';
        $_GET['id']     = $event_id;

        $nav_params = array( 'per_page', 'paged', 'agreement_status', 'orderby', 'order' );
        foreach ( $nav_params as $param ) {
            if ( isset( $_POST[ $param ] ) && '' !== $_POST[ $param ] ) {
                $value              = sanitize_key( wp_unslash( $_POST[ $param ] ) );
                $_GET[ $param ]     = $value;
                $_REQUEST[ $param ] = $value; // Needed for WP_List_Table::get_pagenum().
            }
        }

        // Search term — free text, cannot use sanitize_key.
        if ( isset( $_POST['s'] ) && '' !== $_POST['s'] ) {
            $s              = sanitize_text_field( wp_unslash( $_POST['s'] ) );
            $_GET['s']      = $s;
            $_REQUEST['s']  = $s;
        }

        $message = '';
        $service  = new EventService();

        // Handle delete action.
        $agreement_action = isset( $_POST['agreement_action'] ) ? sanitize_key( wp_unslash( $_POST['agreement_action'] ) ) : '';
        $agreement_id     = isset( $_POST['agreement_id'] )     ? absint( $_POST['agreement_id'] )                        : 0;
        $nonce            = isset( $_POST['_wpnonce'] )         ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) )  : '';

        if ( 'delete' === $agreement_action && $agreement_id > 0 ) {
            if ( ! wp_verify_nonce( $nonce, 'vep_agreement_delete_' . $agreement_id ) ) {
                wp_send_json_error( 'Security check failed' );
            }
            $result  = $service->delete_agreement( $agreement_id );
            $message = $result
                ? __( 'Agreement deleted.', 'volunteer-exchange-platform' )
                : __( 'Could not delete agreement.', 'volunteer-exchange-platform' );
        }

        // Render the refreshed table and return as JSON.
        $table = new EventAgreementsListTable( $event_id, $service );
        $table->prepare_items();

        ob_start();
        ?>
        <div id="vep-event-agreements-section">
            <?php $table->views(); ?>
            <form method="get" id="vep-event-agreements-form">
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
