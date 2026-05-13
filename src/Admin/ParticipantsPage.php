<?php
/**
 * Participants admin page
 *
 * @package VEP
 * @subpackage Admin
 * @since 1.0.0
 */

namespace VolunteerExchangePlatform\Admin;

use VolunteerExchangePlatform\Services\EventService;
use VolunteerExchangePlatform\Services\ParticipantService;
use VolunteerExchangePlatform\Services\ParticipantTypeService;
use VolunteerExchangePlatform\Services\TagService;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Participants admin page
 * 
 * Handles the admin interface for managing participants including listing,
 * adding, editing, viewing, approving, and deleting participants.
 * Supports bulk actions and participant number assignment.
 * 
 * @package VolunteerExchangePlatform\Admin
 */
class ParticipantsPage {
    /**
     * @var ParticipantService
     */
    private $participant_service;

    /**
     * @var EventService
     */
    private $event_service;

    /**
     * @var ParticipantTypeService
     */
    private $type_service;

    /**
     * @var TagService
     */
    private $tag_service;
    
    /**
     * Constructor.
     *
     * @param ParticipantService|null     $participant_service Participant service instance.
     * @param EventService|null           $event_service Event service instance.
     * @param ParticipantTypeService|null $type_service Participant type service instance.
     * @param TagService|null             $tag_service Tag service instance.
     * @return void
     */
    public function __construct(
        ?ParticipantService $participant_service = null,
        ?EventService $event_service = null,
        ?ParticipantTypeService $type_service = null,
        ?TagService $tag_service = null
    ) {
        $this->participant_service = $participant_service ?: new ParticipantService();
        $this->event_service = $event_service ?: new EventService();
        $this->type_service = $type_service ?: new ParticipantTypeService();
        $this->tag_service = $tag_service ?: new TagService();
        add_action('admin_init', array($this, 'handle_form_submission'));
        add_action('admin_init', array($this, 'handle_quick_action'));
        add_action('admin_init', array($this, 'handle_bulk_action'));
    }
    
    /**
     * Render the page
     * 
     * Routes to the appropriate view based on the action parameter
     * 
     * @return void
     */
    public function render() {
        $action_raw = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $participant_id_raw = filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );
        $action = $action_raw ? sanitize_key( $action_raw ) : 'list';
        $participant_id = $participant_id_raw ? absint( $participant_id_raw ) : 0;
        
        switch ($action) {
            case 'add':
                $this->render_form();
                break;
            case 'edit':
                $this->render_form($participant_id);
                break;
            case 'view':
                $this->render_view($participant_id);
                break;
            case 'edit-agreement':
                $this->render_edit_agreement($participant_id);
                break;
            default:
                $this->render_list();
                break;
        }
    }
    
    /**
     * Render list of participants
     * 
     * Displays the WP_List_Table with all participants, filtering options,
     * and success messages
     * 
     * @return void
     */
    private function render_list() {
        $list_table = new \VolunteerExchangePlatform\Admin\ParticipantsListTable($this->participant_service, $this->event_service);
        $list_table->prepare_items();
        $message = filter_input( INPUT_GET, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $notice_raw = filter_input( INPUT_GET, 'notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $approval_status_raw = filter_input( INPUT_GET, 'approval_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $event_id_param_present = filter_has_var( INPUT_GET, 'event_id' );
        $event_id_raw = filter_input( INPUT_GET, 'event_id', FILTER_VALIDATE_INT );
        $notice_type = $notice_raw ? sanitize_key( $notice_raw ) : 'success';
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Participants', 'volunteer-exchange-platform'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-participants&action=add')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'volunteer-exchange-platform'); ?>
            </a>
            <hr class="wp-header-end">
            
            <?php if ( ! empty( $message ) ): ?>
                <div class="notice notice-<?php echo esc_attr(in_array($notice_type, array('success', 'warning', 'error', 'info'), true) ? $notice_type : 'success'); ?> is-dismissible">
                    <p><?php echo esc_html( urldecode( (string) $message ) ); ?></p>
                </div>
            <?php endif; ?>
            
            <?php $list_table->views(); ?>
            <form method="get">
                <input type="hidden" name="page" value="volunteer-exchange-participants" />
                <?php 
                if ( ! empty( $approval_status_raw ) ) {
                    echo '<input type="hidden" name="approval_status" value="' . esc_attr( sanitize_key( $approval_status_raw ) ) . '" />';
                }
                if ( $event_id_param_present ) {
                    echo '<input type="hidden" name="event_id" value="' . esc_attr( absint( (int) $event_id_raw ) ) . '" />';
                }
                $list_table->display(); 
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render form for adding or editing a participant
     * 
     * Validates that an active event exists before allowing new participant creation.
     * New participants are automatically added to the active event.
     * 
     * @param int $participant_id The participant ID (0 for new participant)
     * @return void
     */
    private function render_form($participant_id = 0) {
        $participant = null;
        $selected_tags = array();
        
        if ($participant_id > 0) {
            $participant = $this->participant_service->get_by_id($participant_id);
            $selected_tags = $this->participant_service->get_tag_ids($participant_id);
        }
        
        // Get active event
        $active_event = $this->event_service->get_active_event();
        
        // If no active event, show error
        if (!$participant_id && !$active_event) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Add New Participant', 'volunteer-exchange-platform'); ?></h1>
                <div class="notice notice-error">
                    <p><?php esc_html_e('No active event found. Please create and activate an event before adding participants.', 'volunteer-exchange-platform'); ?></p>
                </div>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-events&action=add')); ?>" class="button button-primary">
                        <?php esc_html_e('Create Event', 'volunteer-exchange-platform'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-participants')); ?>" class="button">
                        <?php esc_html_e('Back to Participants', 'volunteer-exchange-platform'); ?>
                    </a>
                </p>
            </div>
            <?php
            return;
        }
        
        $events = $this->event_service->get_all_for_select();
        $types = $this->type_service->get_all_for_select();
        $tags = $this->tag_service->get_all_for_select();
        
        $title = $participant ? __('Edit Participant', 'volunteer-exchange-platform') : __('Add New Participant', 'volunteer-exchange-platform');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <form method="post" action="" enctype="multipart/form-data">
                <?php wp_nonce_field('vep_participant_form', 'vep_participant_nonce'); ?>
                <input type="hidden" name="participant_id" value="<?php echo esc_attr($participant_id); ?>">
                
                <table class="form-table">
                    <?php if ($participant_id > 0): ?>
                        <!-- Editing existing participant - show event dropdown -->
                        <tr>
                            <th scope="row">
                                <label for="event_id"><?php esc_html_e('Event', 'volunteer-exchange-platform'); ?></label>
                            </th>
                            <td>
                                <select id="event_id" name="event_id" required>
                                    <option value=""><?php esc_html_e('Select Event', 'volunteer-exchange-platform'); ?></option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo esc_attr($event->id); ?>" <?php selected($participant && $participant->event_id == $event->id); ?>>
                                            <?php echo esc_html($event->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php else: ?>
                        <!-- Adding new participant - automatically use active event -->
                        <input type="hidden" name="event_id" value="<?php echo esc_attr($active_event->id); ?>">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Event', 'volunteer-exchange-platform'); ?>
                            </th>
                            <td>
                                <strong><?php echo esc_html($active_event->name); ?></strong>
                                <p class="description"><?php esc_html_e('New participants are automatically added to the active event.', 'volunteer-exchange-platform'); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row">
                            <label for="organization_name"><?php esc_html_e('Organization Name', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="organization_name" name="organization_name" class="regular-text" 
                                   value="<?php echo $participant ? esc_attr($participant->organization_name) : ''; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="description"><?php esc_html_e('Organization Description', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <textarea id="description" name="description" rows="5" class="large-text"><?php echo $participant ? esc_textarea($participant->description ?? '') : ''; ?></textarea>
                            <p class="description"><?php esc_html_e('Describe what this organization does.', 'volunteer-exchange-platform'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="expected_participants_count"><?php esc_html_e('Participants Expected', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="expected_participants_count" name="expected_participants_count" class="small-text" min="0" step="1"
                                value="<?php echo ($participant && isset($participant->expected_participants_count) && $participant->expected_participants_count !== null && $participant->expected_participants_count !== '') ? esc_attr($participant->expected_participants_count) : ''; ?>">
                            <p class="description"><?php esc_html_e('The number of participants expected to attend the event.', 'volunteer-exchange-platform'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="expected_participants_names"><?php esc_html_e('Participant Names', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <textarea id="expected_participants_names" name="expected_participants_names" rows="3" class="large-text"><?php echo $participant ? esc_textarea($participant->expected_participants_names ?? '') : ''; ?></textarea>
                            <p class="description"><?php esc_html_e('The names of the participants expected to attend the event.', 'volunteer-exchange-platform'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="participant_type_id"><?php esc_html_e('Participant Type', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <select id="participant_type_id" name="participant_type_id" required>
                                <option value=""><?php esc_html_e('Select Type', 'volunteer-exchange-platform'); ?></option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo esc_attr($type->id); ?>" <?php selected($participant && $participant->participant_type_id == $type->id); ?>>
                                        <?php echo esc_html($type->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="contact_person_name"><?php esc_html_e('Contact Person Name', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="contact_person_name" name="contact_person_name" class="regular-text" 
                                   value="<?php echo $participant ? esc_attr($participant->contact_person_name) : ''; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="contact_email"><?php esc_html_e('Contact Email', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="contact_email" name="contact_email" class="regular-text" 
                                   value="<?php echo $participant ? esc_attr($participant->contact_email ?? '') : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="contact_phone"><?php esc_html_e('Contact Phone', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="tel" id="contact_phone" name="contact_phone" class="regular-text" 
                                   value="<?php echo $participant ? esc_attr($participant->contact_phone ?? '') : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="link"><?php esc_html_e('Link to homepage', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="link" name="link" class="regular-text"
                                   value="<?php echo $participant ? esc_attr($participant->link ?? '') : ''; ?>" placeholder="https://">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="logo"><?php esc_html_e('Logo', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <?php if ($participant && $participant->logo_url): ?>
                                <p>
                                    <img src="<?php echo esc_url($participant->logo_url); ?>" style="max-width: 150px; height: auto;">
                                </p>
                            <?php endif; ?>
                            <input type="file" id="logo" name="logo" accept="image/*">
                            <p class="description"><?php esc_html_e('Upload a new logo image.', 'volunteer-exchange-platform'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('We Offer Tags', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <?php foreach ($tags as $tag): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="tags[]" value="<?php echo esc_attr($tag->id); ?>" 
                                           <?php checked(in_array($tag->id, $selected_tags)); ?>>
                                    <?php echo esc_html($tag->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="is_approved"><?php esc_html_e('Approved', 'volunteer-exchange-platform'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_approved" name="is_approved" value="1" 
                                       <?php checked($participant && $participant->is_approved); ?>>
                                <?php esc_html_e('Approve this participant', 'volunteer-exchange-platform'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php echo $participant ? esc_attr__('Update Participant', 'volunteer-exchange-platform') : esc_attr__('Create Participant', 'volunteer-exchange-platform'); ?>">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-participants')); ?>" class="button">
                        <?php esc_html_e('Cancel', 'volunteer-exchange-platform'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render view participant with agreements and details
     * 
     * Displays participant details including contact info, tags, participant number,
     * and all associated agreements
     * 
     * @param int $participant_id The participant ID to view
     * @return void
     */
    private function render_view($participant_id) {
        $participant = $this->participant_service->get_by_id_with_details($participant_id);
        
        if (!$participant) {
            echo '<div class="wrap"><p>' . esc_html__('Participant not found.', 'volunteer-exchange-platform') . '</p></div>';
            return;
        }
        
        // Get tags
        $tags = $this->participant_service->get_tags_for_participant($participant_id);
        
        // Get agreements
        $agreements = $this->participant_service->get_agreements_for_participant($participant_id);
        $show_reminder_button = $this->participant_service->should_send_update_reminder($participant_id);
        $tag_ids = $this->participant_service->get_tag_ids($participant_id);
        $missing_update_fields = $this->participant_service->get_update_reminder_missing_fields($participant, $tag_ids);
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html($participant->organization_name); ?>
            </h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-participants')); ?>">&larr; <?php esc_html_e('Back to Participants', 'volunteer-exchange-platform'); ?></a></p>
            
            <h2><?php esc_html_e('Participant Details', 'volunteer-exchange-platform'); ?></h2>
            <table class="form-table">
                <?php if ($participant->participant_number): ?>
                <tr>
                    <th><?php esc_html_e('Participant Number', 'volunteer-exchange-platform'); ?></th>
                    <td><strong style="font-size: 18px; color: #2271b1;">#<?php echo esc_html($participant->participant_number); ?></strong></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e('Event', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($participant->event_name ?? ''); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Type', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($participant->type_name ?? ''); ?></td>
                </tr>
                <?php if ($participant->description): ?>
                <tr>
                    <th><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo nl2br(esc_html($participant->description)); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($participant->expected_participants_count) && $participant->expected_participants_count !== null && $participant->expected_participants_count !== ''): ?>
                <tr>
                    <th><?php esc_html_e('Participants Expected', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($participant->expected_participants_count); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($participant->expected_participants_names) && !empty($participant->expected_participants_names)): ?>
                <tr>
                    <th><?php esc_html_e('Participant Names', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo nl2br(esc_html($participant->expected_participants_names)); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e('Contact Person', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($participant->contact_person_name ?? ''); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Email', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <?php if ($participant->contact_email): ?>
                            <a href="<?php echo esc_attr('mailto:' . $participant->contact_email); ?>">
                                <?php echo esc_html($participant->contact_email); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Phone', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <?php if ($participant->contact_phone): ?>
                            <a href="<?php echo esc_attr('tel:' . $participant->contact_phone); ?>">
                                <?php echo esc_html($participant->contact_phone); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($participant->logo_url): ?>
                <tr>
                    <th><?php esc_html_e('Logo', 'volunteer-exchange-platform'); ?></th>
                    <td><img src="<?php echo esc_url($participant->logo_url); ?>" style="max-width: 200px; height: auto;"></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e('We Offer', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <?php 
                        if (!empty($tags)) {
                            echo esc_html(implode(', ', array_map(function($tag) { return $tag->name; }, $tags)));
                        } else {
                            esc_html_e('No tags', 'volunteer-exchange-platform');
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Status', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <div>
                            <?php echo $participant->is_approved ? '<span style="color: green;">' . esc_html__('Approved', 'volunteer-exchange-platform') . '</span>' : '<span style="color: orange;">' . esc_html__('Pending Approval', 'volunteer-exchange-platform') . '</span>'; ?>
                        </div>
                        <?php if (!$participant->is_approved): ?>
                            <form method="post" style="margin-top: 10px;">
                                <?php wp_nonce_field('vep_participant_approve_' . $participant_id); ?>
                                <input type="hidden" name="page" value="volunteer-exchange-participants">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?php echo esc_attr($participant_id); ?>">
                                <button type="submit" class="button button-secondary" style="background: #00a32a; border-color: #00a32a; color: white;">
                                    <?php esc_html_e('✓ Approve Participant', 'volunteer-exchange-platform'); ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="margin-top: 10px;">
                                <?php wp_nonce_field('vep_participant_unapprove_' . $participant_id); ?>
                                <input type="hidden" name="page" value="volunteer-exchange-participants">
                                <input type="hidden" name="action" value="unapprove">
                                <input type="hidden" name="id" value="<?php echo esc_attr($participant_id); ?>">
                                <button type="submit" class="button button-secondary" style="background: #d63638; border-color: #d63638; color: white;">
                                    <?php esc_html_e('✗ Unapprove', 'volunteer-exchange-platform'); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ( ! empty( $missing_update_fields ) ) : ?>
                <tr>
                    <th><?php esc_html_e('Missing to fill in', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <ul style="margin: 6px 0 0 18px; list-style: disc;">
                            <?php foreach ( $missing_update_fields as $missing_field ) : ?>
                                <li><?php echo esc_html( (string) ( $missing_field['name'] ?? '' ) ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ( $show_reminder_button ) : ?>
                <tr>
                    <th><?php esc_html_e('Reminder', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=volunteer-exchange-participants&action=send_reminder&id=' . $participant_id . '&return_to=view' ), 'vep_participant_send_reminder_' . $participant_id ) ); ?>">
                            <?php esc_html_e('Send Reminder', 'volunteer-exchange-platform'); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ( $participant->is_approved ) : ?>
                <tr>
                    <th><?php esc_html_e('Edit Link', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <?php
                            $randon_key = isset( $participant->randon_key ) ? sanitize_text_field( (string) $participant->randon_key ) : '';
                            $edit_url = \VolunteerExchangePlatform\Frontend\UpdateParticipantPage::get_update_participant_url( $randon_key );
                        ?>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button type="button" class="button button-secondary" onclick="vepCopyEditLink(<?php echo esc_attr( $participant_id ); ?>, this)">
                                <?php esc_html_e('Copy', 'volunteer-exchange-platform'); ?>
                            </button>
                            <code id="vep-edit-link-<?php echo esc_attr( $participant_id ); ?>" style="padding: 6px 8px; background-color: #f5f5f5; border-radius: 3px; font-size: 12px; word-break: break-all;">
                                <?php echo esc_html( $edit_url ); ?>
                            </code>
                        </div>
                        <script>
                            function vepCopyEditLink(participantId, button) {
                                const codeElement = document.getElementById('vep-edit-link-' + participantId);
                                if (!codeElement || !button) return;
                                
                                const text = codeElement.textContent;
                                navigator.clipboard.writeText(text).then(() => {
                                    const originalText = button.textContent;
                                    button.textContent = '<?php esc_html_e('Copied!', 'volunteer-exchange-platform'); ?>';
                                    setTimeout(() => {
                                        button.textContent = originalText;
                                    }, 2000);
                                });
                            }
                        </script>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <h2><?php esc_html_e('Agreements', 'volunteer-exchange-platform'); ?></h2>
            <?php if (empty($agreements)): ?>
                <p><?php esc_html_e('No agreements yet.', 'volunteer-exchange-platform'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Other Participant', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Initiator', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Created', 'volunteer-exchange-platform'); ?></th>
                            <th><?php esc_html_e('Actions', 'volunteer-exchange-platform'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agreements as $agreement): ?>
                            <tr>
                                <td><?php echo esc_html($agreement->id); ?></td>
                                <td><?php echo esc_html($agreement->other_participant_name); ?></td>
                                <td><?php echo esc_html($agreement->initiator_name); ?></td>
                                <td><?php echo esc_html(wp_trim_words($agreement->description, 10)); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($agreement->created_at))); ?></td>
                                <td>
                                    <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=volunteer-exchange-participants&action=edit-agreement&id=' . $agreement->id . '&return_id=' . $participant_id)); ?>">
                                        <?php esc_html_e('Edit', 'volunteer-exchange-platform'); ?>
                                    </a>
                                    <a class="button button-small" style="margin-left: 6px;" href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=volunteer-exchange-participants&action=delete-agreement&id=' . $agreement->id . '&return_id=' . $participant_id), 'vep_agreement_delete_' . $agreement->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __('Are you sure you want to delete this agreement?', 'volunteer-exchange-platform') ); ?>');">
                                        <?php esc_html_e('Delete', 'volunteer-exchange-platform'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render agreement edit form
     *
     * @param int $agreement_id The agreement ID to edit
     * @return void
     */
    private function render_edit_agreement($agreement_id) {
        $return_id = absint( filter_input( INPUT_GET, 'return_id', FILTER_VALIDATE_INT ) );
        $cancel_url = $return_id > 0
            ? admin_url( 'admin.php?page=volunteer-exchange-participants&action=view&id=' . $return_id )
            : admin_url( 'admin.php?page=volunteer-exchange-participants' );

        // Always load agreement data from agreement storage.
        $agreement = $this->event_service->get_agreement_by_id($agreement_id);
        
        if (!$agreement) {
            echo '<div class="wrap"><p>' . esc_html__('Agreement not found.', 'volunteer-exchange-platform') . '</p></div>';
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Edit Agreement', 'volunteer-exchange-platform'); ?></h1>
            <p><a href="<?php echo esc_url($cancel_url); ?>">&larr; <?php esc_html_e('Back to Participants', 'volunteer-exchange-platform'); ?></a></p>
            
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('ID', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($agreement->id); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Participant 1', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($agreement->participant1_name ?? ''); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Participant 2', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($agreement->participant2_name ?? ''); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Initiator', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <select name="initiator_id" id="initiator_id" form="vep-agreement-edit-form">
                            <option value="<?php echo esc_attr( (int) ( $agreement->participant1_id ?? 0 ) ); ?>" <?php selected( (int) ( $agreement->initiator_id ?? 0 ), (int) ( $agreement->participant1_id ?? 0 ) ); ?>>
                                <?php echo esc_html($agreement->participant1_name ?? ''); ?>
                            </option>
                            <option value="<?php echo esc_attr( (int) ( $agreement->participant2_id ?? 0 ) ); ?>" <?php selected( (int) ( $agreement->initiator_id ?? 0 ), (int) ( $agreement->participant2_id ?? 0 ) ); ?>>
                                <?php echo esc_html($agreement->participant2_name ?? ''); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Created', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($agreement->created_at))); ?></td>
                </tr>
                <tr>
                    <th><label for="description"><?php esc_html_e('Description', 'volunteer-exchange-platform'); ?></label></th>
                    <td>
                        <form id="vep-agreement-edit-form" method="post" action="">
                            <?php wp_nonce_field('vep_agreement_edit', 'vep_agreement_nonce'); ?>
                            <input type="hidden" name="agreement_id" value="<?php echo esc_attr($agreement->id); ?>">
                            <input type="hidden" name="return_id" value="<?php echo esc_attr($return_id); ?>">
                            <textarea id="description" name="description" rows="5" class="large-text"><?php echo esc_textarea($agreement->description ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Update the description of this agreement.', 'volunteer-exchange-platform'); ?></p>
                            <p class="submit">
                                <input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e('Update Agreement', 'volunteer-exchange-platform'); ?>">
                                <a href="<?php echo esc_url($cancel_url); ?>" class="button">
                                    <?php esc_html_e('Cancel', 'volunteer-exchange-platform'); ?>
                                </a>
                            </p>
                        </form>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Handle form submission for creating or updating a participant
     * 
     * Validates nonce, checks permissions, handles logo uploads, and saves participant data.
     * Updates associated tags and ensures new participants are added to active event.
     * 
     * @return void
     */
    public function handle_form_submission() {
        // Only process on our page
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'volunteer-exchange-participants' !== $page ) {
            return;
        }

        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
        if ( 'POST' !== strtoupper( $request_method ) ) {
            return;
        }

        // Quick actions (approve, unapprove, delete, send_reminder) are handled by handle_quick_action()
        $post_action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
        if ( in_array( $post_action, array( 'approve', 'unapprove', 'delete', 'send_reminder' ), true ) ) {
            return;
        }

        // Check for agreement form submission
        $agreement_nonce = isset( $_POST['vep_agreement_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vep_agreement_nonce'] ) ) : '';
        if ( ! empty( $agreement_nonce ) ) {
            if ( ! wp_verify_nonce( $agreement_nonce, 'vep_agreement_edit' ) ) {
                wp_die(esc_html__('Security check failed.', 'volunteer-exchange-platform'));
            }
            
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
            }
            
            $agreement_id = isset( $_POST['agreement_id'] ) ? absint( wp_unslash( $_POST['agreement_id'] ) ) : 0;
            if ( $agreement_id > 0 ) {
                $description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
                $update_data = array( 'description' => $description );
                if ( isset( $_POST['initiator_id'] ) ) {
                    $initiator_id = absint( wp_unslash( $_POST['initiator_id'] ) );
                    if ( $initiator_id > 0 ) {
                        $update_data['initiator_id'] = $initiator_id;
                    }
                }
                $this->event_service->update_agreement(
                    $agreement_id,
                    $update_data
                );
                $return_id = isset( $_POST['return_id'] ) ? absint( wp_unslash( $_POST['return_id'] ) ) : 0;
                if ( $return_id > 0 ) {
                    wp_safe_redirect(admin_url('admin.php?page=volunteer-exchange-participants&action=view&id=' . $return_id . '&message=' . urlencode(__('Agreement updated successfully.', 'volunteer-exchange-platform'))));
                } else {
                    wp_safe_redirect(admin_url('admin.php?page=volunteer-exchange-participants&message=' . urlencode(__('Agreement updated successfully.', 'volunteer-exchange-platform'))));
                }
                exit;
            }
            return;
        }

        $nonce = isset( $_POST['vep_participant_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vep_participant_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_participant_form' ) ) {
            wp_die(esc_html__('Security check failed.', 'volunteer-exchange-platform'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }
        
        $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
        
        // For new participants, ensure active event exists and use it
        if ($participant_id === 0) {
            $active_event = $this->event_service->get_active_event();
            if (!$active_event) {
                wp_die(esc_html__('No active event found. Please create and activate an event before adding participants.', 'volunteer-exchange-platform'));
            }
            $event_id = $active_event->id;
        } else {
            $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
        }
        
        $expected_count_raw = isset( $_POST['expected_participants_count'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_participants_count'] ) ) : '';
        $expected_count = '' !== $expected_count_raw ? absint( $expected_count_raw ) : null;

        $data = array(
            'event_id' => $event_id,
            'organization_name' => isset( $_POST['organization_name'] ) ? sanitize_text_field( wp_unslash( $_POST['organization_name'] ) ) : '',
            'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'expected_participants_count' => $expected_count,
            'expected_participants_names' => isset( $_POST['expected_participants_names'] ) ? sanitize_textarea_field( wp_unslash( $_POST['expected_participants_names'] ) ) : '',
            'participant_type_id' => isset( $_POST['participant_type_id'] ) ? absint( wp_unslash( $_POST['participant_type_id'] ) ) : 0,
            'contact_person_name' => isset( $_POST['contact_person_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_person_name'] ) ) : '',
            'contact_email' => isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '',
            'contact_phone' => isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '',
            'link' => isset( $_POST['link'] ) ? esc_url_raw( wp_unslash( $_POST['link'] ) ) : '',
            'is_approved' => isset( $_POST['is_approved'] ) ? 1 : 0
        );
        
        // Handle logo upload
        $logo_name = isset( $_FILES['logo']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['logo']['name'] ) ) : '';
        if ( '' !== $logo_name ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_handle_upload expects the raw $_FILES entry.
            $file = $_FILES['logo'];
            $upload = wp_handle_upload($file, array('test_form' => false));
            
            if (isset($upload['url'])) {
                $data['logo_url'] = $upload['url'];
            }
        }
        
        if ($participant_id > 0) {
            // Update
            $this->participant_service->update_participant($participant_id, $data);
        } else {
            // Insert
            $participant_id = $this->participant_service->create($data);
        }

        $tag_ids = array();
        if ( isset( $_POST['tags'] ) && is_array( $_POST['tags'] ) ) {
            $tag_ids = array_map( 'absint', wp_unslash( $_POST['tags'] ) );
        }
        $this->participant_service->replace_tags($participant_id, $tag_ids);
        
        wp_safe_redirect(admin_url('admin.php?page=volunteer-exchange-participants&message=' . urlencode(__('Participant saved successfully.', 'volunteer-exchange-platform'))));
        exit;
    }
    
    /**
     * Handle quick actions for participants (approve, unapprove, delete)
     * 
     * Validates permissions and performs the requested action.
        * Approval assigns the first available participant number if not already assigned.
     * 
     * @return void
     */
    public function handle_quick_action() {
        // Only process on our page
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'volunteer-exchange-participants' !== $page ) {
            return;
        }

        // Check for action in GET or POST
        $action = '';
        $participant_id = 0;
        $nonce = '';

        if ( isset( $_POST['action'], $_POST['id'] ) ) {
            // POST form submission
            $action = sanitize_key( wp_unslash( $_POST['action'] ) );
            $participant_id = absint( wp_unslash( $_POST['id'] ) );
            $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        } elseif ( isset( $_GET['action'], $_GET['id'] ) ) {
            // GET link action
            $action = sanitize_key( wp_unslash( $_GET['action'] ) );
            $participant_id = absint( wp_unslash( $_GET['id'] ) );
            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        } else {
            return;
        }
        
        if (!in_array($action, array('approve', 'unapprove', 'delete', 'send_reminder', 'delete-agreement'))) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }

        $nonce_action = 'delete-agreement' === $action
            ? 'vep_agreement_delete_' . $participant_id
            : 'vep_participant_' . $action . '_' . $participant_id;

        if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            wp_die(esc_html__('Security check failed.', 'volunteer-exchange-platform'));
        }

        $result = false;
        
        if ($action === 'approve') {
            $result = $this->participant_service->approve_participant($participant_id);
            $message = $result ? __('Participant approved.', 'volunteer-exchange-platform') : __('Could not approve participant.', 'volunteer-exchange-platform');
        } elseif ($action === 'unapprove') {
            $result = $this->participant_service->unapprove_participant($participant_id);
            $message = $result ? __('Participant unapproved. Note: Participant number is retained.', 'volunteer-exchange-platform') : __('Could not unapprove participant.', 'volunteer-exchange-platform');
        } elseif ($action === 'send_reminder') {
            $result = $this->participant_service->queue_update_participant_reminder($participant_id);
            $message = $result ? __('Reminder queued for sending.', 'volunteer-exchange-platform') : __('Reminder was not sent (participant may already be fully updated or missing required data).', 'volunteer-exchange-platform');
        } elseif ($action === 'delete-agreement') {
            $result = $this->event_service->delete_agreement($participant_id);
            $message = $result ? __('Agreement deleted.', 'volunteer-exchange-platform') : __('Could not delete agreement.', 'volunteer-exchange-platform');
        } elseif ($action === 'delete') {
            $result = $this->participant_service->delete_participant($participant_id);
            $message = $result ? __('Participant deleted.', 'volunteer-exchange-platform') : __('Could not delete participant.', 'volunteer-exchange-platform');
        }

        $notice = $result ? 'success' : 'error';

        // If action came from POST (detail page form) or has return_to=view, redirect back to the participant view
        $return_to = isset( $_GET['return_to'] ) ? sanitize_key( wp_unslash( $_GET['return_to'] ) ) : '';
        $return_id = isset( $_GET['return_id'] ) ? absint( wp_unslash( $_GET['return_id'] ) ) : 0;
        $came_from_detail = isset( $_POST['action'] ) || $return_to === 'view' || ( 'delete-agreement' === $action && $return_id > 0 );
        $detail_id = ( 'delete-agreement' === $action && $return_id > 0 ) ? $return_id : $participant_id;
        if ( $came_from_detail && $action !== 'delete' ) {
            wp_safe_redirect(admin_url('admin.php?page=volunteer-exchange-participants&action=view&id=' . $detail_id . '&notice=' . $notice . '&message=' . urlencode($message)));
        } else {
            wp_safe_redirect(admin_url('admin.php?page=volunteer-exchange-participants&notice=' . $notice . '&message=' . urlencode($message)));
        }
        exit;
    }
    
    /**
     * Handle bulk actions on multiple participants
     * 
     * Supports bulk approve and bulk delete operations.
        * Bulk approval assigns the first available participant numbers as needed.
     * 
     * @return void
     */
    public function handle_bulk_action() {
        // Only process on our page
        $page_raw = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $page = $page_raw ? sanitize_key( $page_raw ) : '';
        if ( 'volunteer-exchange-participants' !== $page ) {
            return;
        }
        
        // Check if bulk action was submitted
        $action_raw = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $action = $action_raw ? sanitize_key( $action_raw ) : '-1';
        if ( '-1' === $action ) {
            $action2_raw = filter_input( INPUT_GET, 'action2', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
            $action2 = $action2_raw ? sanitize_key( $action2_raw ) : '-1';
            if ( '-1' === $action2 ) {
                return;
            }
            $action = $action2;
        }
        
        if (!in_array($action, array('bulk_approve', 'bulk_delete'))) {
            return;
        }
        
        $participant_ids_raw = filter_input( INPUT_GET, 'participant', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
        if ( ! is_array( $participant_ids_raw ) ) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }

        $bulk_nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( empty( $bulk_nonce ) || ! wp_verify_nonce( $bulk_nonce, 'bulk-participants' ) ) {
            wp_die(esc_html__('Security check failed.', 'volunteer-exchange-platform'));
        }
        
        $participant_ids = array_map( 'absint', $participant_ids_raw );
        
        $count = 0;
        foreach ($participant_ids as $participant_id) {
            if ($action === 'bulk_approve') {
                $this->participant_service->approve_participant($participant_id);
                $count++;
            } elseif ($action === 'bulk_delete') {
                $this->participant_service->delete_participant($participant_id);
                $count++;
            }
        }
        
        if ($action === 'bulk_approve') {
            $message = sprintf(
                /* translators: %d is the number of participants */
                _n('%d participant approved.', '%d participants approved.', $count, 'volunteer-exchange-platform'),
                $count
            );
        } else {
            $message = sprintf(
                /* translators: %d is the number of participants */
                _n('%d participant deleted.', '%d participants deleted.', $count, 'volunteer-exchange-platform'),
                $count
            );
        }
        
        wp_safe_redirect(admin_url('admin.php?page=volunteer-exchange-participants&message=' . urlencode($message)));
        exit;
    }
}
