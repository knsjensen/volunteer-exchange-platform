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
                    <td><?php echo esc_html($participant->contact_email ?? ''); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Phone', 'volunteer-exchange-platform'); ?></th>
                    <td><?php echo esc_html($participant->contact_phone ?? ''); ?></td>
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
                        <?php echo $participant->is_approved ? '<span style="color: green;">' . esc_html__('Approved', 'volunteer-exchange-platform') . '</span>' : '<span style="color: orange;">' . esc_html__('Pending Approval', 'volunteer-exchange-platform') . '</span>'; ?>
                        <?php if (!$participant->is_approved): ?>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=volunteer-exchange-participants&action=approve&id=' . $participant_id ), 'vep_participant_approve_' . $participant_id ) ); ?>" style="background: #00a32a; border-color: #00a32a;">
                                <?php esc_html_e('✓ Approve Participant', 'volunteer-exchange-platform'); ?>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=volunteer-exchange-participants&action=unapprove&id=' . $participant_id ), 'vep_participant_unapprove_' . $participant_id ) ); ?>" style="background: #d63638; border-color: #d63638;">
                                <?php esc_html_e('✗ Unapprove Participant', 'volunteer-exchange-platform'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ( $show_reminder_button ) : ?>
                <tr>
                    <th><?php esc_html_e('Reminder', 'volunteer-exchange-platform'); ?></th>
                    <td>
                        <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=volunteer-exchange-participants&action=send_reminder&id=' . $participant_id ), 'vep_participant_send_reminder_' . $participant_id ) ); ?>">
                            <?php esc_html_e('Send Reminder', 'volunteer-exchange-platform'); ?>
                        </a>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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
     * Approval assigns a sequential participant number if not already assigned.
     * 
     * @return void
     */
    public function handle_quick_action() {
        // Only process on our page
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'volunteer-exchange-participants' !== $page ) {
            return;
        }

        if ( ! isset( $_GET['action'], $_GET['id'] ) ) {
            return;
        }

        $action = sanitize_key( wp_unslash( $_GET['action'] ) );
        $participant_id = absint( wp_unslash( $_GET['id'] ) );
        
        if (!in_array($action, array('approve', 'unapprove', 'delete', 'send_reminder'))) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'volunteer-exchange-platform'));
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'vep_participant_' . $action . '_' . $participant_id ) ) {
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
        } elseif ($action === 'delete') {
            $result = $this->participant_service->delete_participant($participant_id);
            $message = $result ? __('Participant deleted.', 'volunteer-exchange-platform') : __('Could not delete participant.', 'volunteer-exchange-platform');
        }

        $notice = $result ? 'success' : 'error';
        wp_safe_redirect(admin_url('admin.php?page=volunteer-exchange-participants&notice=' . $notice . '&message=' . urlencode($message)));
        exit;
    }
    
    /**
     * Handle bulk actions on multiple participants
     * 
     * Supports bulk approve and bulk delete operations.
     * Bulk approval assigns sequential participant numbers as needed.
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
