<?php
/**
 * Participant service
 *
 * @package VEP
 * @subpackage Services
 */

namespace VolunteerExchangePlatform\Services;

use VolunteerExchangePlatform\Services\AbstractService;
use VolunteerExchangePlatform\Database\EventRepository;
use VolunteerExchangePlatform\Database\ParticipantRepository;
use VolunteerExchangePlatform\Database\ParticipantTypeRepository;
use VolunteerExchangePlatform\Database\TagRepository;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ParticipantService extends AbstractService {
    private $repository;
    private $event_repository;
    private $type_repository;
    private $tag_repository;

    /**
     * Constructor.
     *
     * @param ParticipantRepository|null     $repository Participant repository instance.
     * @param EventRepository|null           $event_repository Event repository instance.
     * @param ParticipantTypeRepository|null $type_repository Participant type repository instance.
     * @param TagRepository|null             $tag_repository Tag repository instance.
     * @return void
     */
    public function __construct(
        ?ParticipantRepository $repository = null,
        ?EventRepository $event_repository = null,
        ?ParticipantTypeRepository $type_repository = null,
        ?TagRepository $tag_repository = null
    ) {
        $this->repository = $repository ?: new ParticipantRepository();
        $this->event_repository = $event_repository ?: new EventRepository();
        $this->type_repository = $type_repository ?: new ParticipantTypeRepository();
        $this->tag_repository = $tag_repository ?: new TagRepository();
    }

    /**
     * Get approved participants for an event with type details.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_approved_for_event_with_type( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return array();
        }

        return $this->run_guarded(
            function () use ( $event_id ) {
                return $this->repository->get_approved_for_event_with_type( (int) $event_id );
            },
            array()
        );
    }

    /**
     * Get participant by ID.
     *
     * @param int $participant_id Participant ID.
     * @return object|null
     */
    public function get_by_id( $participant_id ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return null;
        }

        return $this->run_guarded(
            function () use ( $participant_id ) {
                return $this->repository->get_by_id( (int) $participant_id );
            },
            null
        );
    }

    /**
     * Get participant by ID including related details.
     *
     * @param int $participant_id Participant ID.
     * @return object|null
     */
    public function get_by_id_with_details( $participant_id ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return null;
        }

        return $this->run_guarded(
            function () use ( $participant_id ) {
                return $this->repository->get_by_id_with_details( (int) $participant_id );
            },
            null
        );
    }

    /**
     * Get approved participant by ID including related details.
     *
     * @param int $participant_id Participant ID.
     * @return object|null
     */
    public function get_approved_by_id_with_details( $participant_id ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return null;
        }

        return $this->run_guarded(
            function () use ( $participant_id ) {
                return $this->repository->get_approved_by_id_with_details( (int) $participant_id );
            },
            null
        );
    }

    /**
     * Get approved participants for event select controls.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_approved_for_event_select( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return array();
        }

        return $this->run_guarded(
            function () use ( $event_id ) {
                return $this->repository->get_approved_for_event_select( (int) $event_id );
            },
            array()
        );
    }

    /**
     * Get all participants for event select controls.
     *
     * @param int $event_id Event ID.
     * @return array
     */
    public function get_for_event_select( $event_id ) {
        if ( ! $this->is_valid_id( $event_id ) ) {
            return array();
        }

        return $this->run_guarded(
            function () use ( $event_id ) {
                return $this->repository->get_for_event_select( (int) $event_id );
            },
            array()
        );
    }

    /**
     * Get tag IDs assigned to a participant.
     *
     * @param int $participant_id Participant ID.
     * @return array
     */
    public function get_tag_ids( $participant_id ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return array();
        }

        return $this->run_guarded(
            function () use ( $participant_id ) {
                return $this->repository->get_tag_ids( (int) $participant_id );
            },
            array()
        );
    }

    /**
     * Get tag rows for a participant.
     *
     * @param int $participant_id Participant ID.
     * @return array
     */
    public function get_tags_for_participant( $participant_id ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return array();
        }

        return $this->run_guarded(
            function () use ( $participant_id ) {
                return $this->repository->get_tags_for_participant( (int) $participant_id );
            },
            array()
        );
    }

    /**
     * Get agreements for a participant.
     *
     * @param int $participant_id Participant ID.
     * @return array
     */
    public function get_agreements_for_participant( $participant_id ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return array();
        }

        return $this->run_guarded(
            function () use ( $participant_id ) {
                return $this->repository->get_agreements_for_participant( (int) $participant_id );
            },
            array()
        );
    }

    /**
     * Get paginated participants with filters.
     *
     * @param int    $per_page Items per page.
     * @param int    $offset Offset.
     * @param string $orderby Order by field.
     * @param string $order Sort order.
     * @param string $approval_status Approval filter.
     * @param int    $event_id Event ID filter.
     * @return array
     */
    public function get_paginated_with_filters( $per_page, $offset, $orderby, $order, $approval_status = 'all', $event_id = 0 ) {
        $per_page = max( 1, (int) $per_page );
        $offset = max( 0, (int) $offset );
        $event_id = (int) $event_id;
        if ( $event_id < 0 ) {
            $event_id = 0;
        }

        $allowed_status = array( 'all', 'pending', 'approved' );
        if ( ! in_array( $approval_status, $allowed_status, true ) ) {
            $approval_status = 'all';
        }

        return $this->run_guarded(
            function () use ( $per_page, $offset, $orderby, $order, $approval_status, $event_id ) {
                return $this->repository->get_paginated_with_filters( $per_page, $offset, $orderby, $order, $approval_status, $event_id );
            },
            array()
        );
    }

    /**
     * Count participants with filters.
     *
     * @param string $approval_status Approval filter.
     * @param int    $event_id Event ID filter.
     * @return int
     */
    public function count_with_filters( $approval_status = 'all', $event_id = 0 ) {
        $event_id = (int) $event_id;
        if ( $event_id < 0 ) {
            $event_id = 0;
        }

        $allowed_status = array( 'all', 'pending', 'approved' );
        if ( ! in_array( $approval_status, $allowed_status, true ) ) {
            $approval_status = 'all';
        }

        return (int) $this->run_guarded(
            function () use ( $approval_status, $event_id ) {
                return $this->repository->count_with_filters( $approval_status, $event_id );
            },
            0
        );
    }

    private function normalize_participant_data( $data, $is_update = false ) {
        $normalized = array();

        if ( isset( $data['event_id'] ) || ! $is_update ) {
            $event_id = isset( $data['event_id'] ) ? (int) $data['event_id'] : 0;
            if ( ! $this->is_valid_id( $event_id ) ) {
                return false;
            }
            if ( ! $this->event_repository->get_by_id( $event_id ) ) {
                return false;
            }
            $normalized['event_id'] = $event_id;
        }

        if ( isset( $data['organization_name'] ) || ! $is_update ) {
            $organization_name = sanitize_text_field( $data['organization_name'] ?? '' );
            if ( '' === $organization_name ) {
                return false;
            }
            $normalized['organization_name'] = $organization_name;
        }

        if ( isset( $data['participant_type_id'] ) || ! $is_update ) {
            $participant_type_id = isset( $data['participant_type_id'] ) ? (int) $data['participant_type_id'] : 0;
            if ( ! $this->is_valid_id( $participant_type_id ) ) {
                return false;
            }
            if ( ! $this->type_repository->get_by_id( $participant_type_id ) ) {
                return false;
            }
            $normalized['participant_type_id'] = $participant_type_id;
        }

        if ( isset( $data['contact_person_name'] ) || ! $is_update ) {
            $contact_person_name = sanitize_text_field( $data['contact_person_name'] ?? '' );
            if ( '' === $contact_person_name ) {
                return false;
            }
            $normalized['contact_person_name'] = $contact_person_name;
        }

        if ( array_key_exists( 'description', $data ) || ! $is_update ) {
            $normalized['description'] = sanitize_textarea_field( $data['description'] ?? '' );
        }

        if ( array_key_exists( 'expected_participants_count', $data ) || ! $is_update ) {
            $expected_count = $data['expected_participants_count'] ?? null;
            if ( null === $expected_count || '' === $expected_count ) {
                $normalized['expected_participants_count'] = null;
            } else {
                $normalized['expected_participants_count'] = absint( $expected_count );
            }
        }

        if ( array_key_exists( 'expected_participants_names', $data ) || ! $is_update ) {
            $normalized['expected_participants_names'] = sanitize_textarea_field( $data['expected_participants_names'] ?? '' );
        }

        if ( array_key_exists( 'contact_email', $data ) || ! $is_update ) {
            $normalized['contact_email'] = sanitize_email( $data['contact_email'] ?? '' );
        }

        if ( array_key_exists( 'contact_phone', $data ) || ! $is_update ) {
            $normalized['contact_phone'] = sanitize_text_field( $data['contact_phone'] ?? '' );
        }

        if ( array_key_exists( 'logo_url', $data ) ) {
            $normalized['logo_url'] = esc_url_raw( $data['logo_url'] ?? '' );
        }

        if ( array_key_exists( 'is_approved', $data ) ) {
            $normalized['is_approved'] = ! empty( $data['is_approved'] ) ? 1 : 0;
        }

        return $normalized;
    }

    private function normalize_tag_ids( $tag_ids ) {
        if ( ! is_array( $tag_ids ) ) {
            return array();
        }

        $clean_ids = array();
        foreach ( $tag_ids as $tag_id ) {
            $tag_id = (int) $tag_id;
            if ( $tag_id > 0 && $this->tag_repository->get_by_id( $tag_id ) ) {
                $clean_ids[] = $tag_id;
            }
        }

        return array_values( array_unique( $clean_ids ) );
    }

    /**
     * Generate a UUID v4 random key for a participant.
     *
     * @return string
     */
    private function generate_randon_key() {
        return wp_generate_uuid4();
    }

    /**
     * Create a participant.
     *
     * @param array $data Participant payload.
     * @return int|false
     */
    public function create( $data ) {
        $normalized = $this->normalize_participant_data( $data, false );
        if ( false === $normalized ) {
            return false;
        }

        $normalized['randon_key'] = $this->generate_randon_key();

        return $this->run_guarded(
            function () use ( $normalized ) {
                return $this->repository->create( $normalized );
            },
            false
        );
    }

    /**
     * Update participant.
     *
     * @param int   $participant_id Participant ID.
     * @param array $data Participant payload.
     * @return bool
     */
    public function update_participant( $participant_id, $data ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return false;
        }

        $normalized = $this->normalize_participant_data( $data, true );
        if ( false === $normalized || empty( $normalized ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $participant_id, $normalized ) {
                return $this->repository->update_participant( (int) $participant_id, $normalized );
            },
            false
        );
    }

    /**
     * Delete participant.
     *
     * @param int $participant_id Participant ID.
     * @return bool
     */
    public function delete_participant( $participant_id ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $participant_id ) {
                return $this->repository->delete_participant( (int) $participant_id );
            },
            false
        );
    }

    /**
     * Replace participant tags with provided tag IDs.
     *
     * @param int   $participant_id Participant ID.
     * @param array $tag_ids Tag IDs.
     * @return bool
     */
    public function replace_tags( $participant_id, $tag_ids ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return false;
        }

        $clean_tag_ids = $this->normalize_tag_ids( $tag_ids );

        return $this->run_guarded(
            function () use ( $participant_id, $clean_tag_ids ) {
                $this->repository->replace_tags( (int) $participant_id, $clean_tag_ids );
                return true;
            },
            false
        );
    }

    /**
     * Create participant and assign tags.
     *
     * @param array $data Participant payload.
     * @param array $tag_ids Tag IDs.
     * @return int|false
     */
    public function create_with_tags( $data, $tag_ids ) {
        $participant_id = $this->create( $data );
        if ( false === $participant_id ) {
            return false;
        }

        if ( ! $this->replace_tags( $participant_id, $tag_ids ) ) {
            return false;
        }

        return $participant_id;
    }

    /**
     * Approve participant and assign number if required.
     *
     * @param int $participant_id Participant ID.
     * @return bool
     */
    public function approve_participant( $participant_id ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $participant_id ) {
                $participant = $this->repository->get_approval_context( (int) $participant_id );
                if ( ! $participant ) {
                    return false;
                }

                if ( ! $participant->is_approved ) {
                    $next_number = $this->repository->get_next_participant_number( (int) $participant->event_id );
                    return $this->repository->set_approval( (int) $participant_id, 1, $next_number );
                }

                return $this->repository->set_approval( (int) $participant_id, 1 );
            },
            false
        );
    }

    /**
     * Remove participant approval.
     *
     * @param int $participant_id Participant ID.
     * @return bool
     */
    public function unapprove_participant( $participant_id ) {
        if ( ! $this->is_valid_id( $participant_id ) ) {
            return false;
        }

        return $this->run_guarded(
            function () use ( $participant_id ) {
                return $this->repository->set_approval( (int) $participant_id, 0 );
            },
            false
        );
    }
}
