<?php
/**
 * Agreements list shortcode
 * Usage: [vep_agreements_list]
 *
 * @package VEP
 * @subpackage Shortcodes
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Shortcodes;

use VolunteerExchangePlatform\Services\EventService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Agreements list shortcode
 * Usage: [vep_agreements_list]
 * 
 * @package VolunteerExchangePlatform\Shortcodes
 */
class AgreementsList {
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
        add_shortcode('vep_agreements_list', array($this, 'render'));
    }

    /**
     * Render shortcode
     *
     * @param array $atts
     * @return string
     */
    public function render($atts) {
        // Get active event
        $active_event = $this->event_service->get_active_event();

        if (!$active_event) {
            return '<div class="vep-message vep-error">' . __('No active event at the moment.', 'volunteer-exchange-platform') . '</div>';
        }

        $agreements = $this->event_service->get_agreements_for_event($active_event->id);

        if (empty($agreements)) {
            return '<div class="vep-message vep-info">' . __('No agreements yet.', 'volunteer-exchange-platform') . '</div>';
        }

        ob_start();
        ?>
        <div class="vep-agreements-list">
            <div class="vep-agreements-search vep-form-group">
                <input
                    type="text"
                    id="vep-agreements-search-input"
                    class="vep-agreements-search-input"
                    placeholder="<?php esc_attr_e( 'Type to search all columns...', 'volunteer-exchange-platform' ); ?>"
                >
            </div>
            <div class="vep-agreements-table-wrap">
                <table class="vep-agreements-table">
                    <thead>
                        <tr>
                            <th class="vep-agreements-col-num"><?php esc_html_e('#', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Participant 1', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Participant 2', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = 1; ?>
                        <?php foreach ($agreements as $agreement): ?>
                            <?php
                            $participant1_name = $agreement->participant1_name ?? '';
                            $participant2_name = $agreement->participant2_name ?? '';
                            $participant1_is_initiator = isset( $agreement->initiator_id, $agreement->participant1_id )
                                && (int) $agreement->initiator_id === (int) $agreement->participant1_id;
                            $participant2_is_initiator = isset( $agreement->initiator_id, $agreement->participant2_id )
                                && (int) $agreement->initiator_id === (int) $agreement->participant2_id;
                            ?>
                            <tr>
                                <td class="vep-agreements-col-num"><strong><?php echo esc_html($row_num); ?></strong></td>
                                <td>
                                    <?php if ( $participant1_is_initiator ) : ?>
                                        <strong><?php echo esc_html( $participant1_name ); ?></strong>
                                    <?php else : ?>
                                        <?php echo esc_html( $participant1_name ); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $participant2_is_initiator ) : ?>
                                        <strong><?php echo esc_html( $participant2_name ); ?></strong>
                                    <?php else : ?>
                                        <?php echo esc_html( $participant2_name ); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $desc = $agreement->description ?? '';
                                    echo nl2br(esc_html($desc));
                                    ?>
                                </td>
                            </tr>
                            <?php $row_num++; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
