<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

use WPCBTPro\Security\AuditLogger;

final class CandidateService
{
    public function __construct(
        private readonly CandidateRepository $repository,
        private readonly CandidateRefGenerator $refGenerator,
    ) {
    }

    /**
     * @param array{institution_id:int, first_name:string, last_name:string, email?:string, phone?:string,
     *              department?:string, class?:string, registration_number?:string, photo_attachment_id?:int} $input
     * @return array<string, string> field => error message
     */
    public function validate(array $input): array
    {
        $errors = [];

        if (trim($input['first_name'] ?? '') === '') {
            $errors['first_name'] = __('First name is required.', 'wp-cbt-pro');
        }
        if (trim($input['last_name'] ?? '') === '') {
            $errors['last_name'] = __('Last name is required.', 'wp-cbt-pro');
        }
        if (!empty($input['email']) && !is_email($input['email'])) {
            $errors['email'] = __('Enter a valid email address.', 'wp-cbt-pro');
        }
        if (empty($input['institution_id'])) {
            $errors['institution_id'] = __('An institution is required.', 'wp-cbt-pro');
        }

        return $errors;
    }

    public function create(array $input): int
    {
        $institutionId = (int) $input['institution_id'];
        $ref = trim($input['candidate_ref'] ?? '') !== ''
            ? $input['candidate_ref']
            : $this->refGenerator->generate($institutionId);

        $id = $this->repository->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => $ref,
            'first_name' => sanitize_text_field($input['first_name']),
            'last_name' => sanitize_text_field($input['last_name']),
            'email' => !empty($input['email']) ? sanitize_email($input['email']) : null,
            'phone' => !empty($input['phone']) ? sanitize_text_field($input['phone']) : null,
            'department' => !empty($input['department']) ? sanitize_text_field($input['department']) : null,
            'class' => !empty($input['class']) ? sanitize_text_field($input['class']) : null,
            'registration_number' => !empty($input['registration_number']) ? sanitize_text_field($input['registration_number']) : null,
            'photo_attachment_id' => !empty($input['photo_attachment_id']) ? (int) $input['photo_attachment_id'] : null,
            'wp_user_id' => !empty($input['wp_user_id']) ? (int) $input['wp_user_id'] : null,
            'status' => 'active',
        ]);

        AuditLogger::record('candidate.created', 'candidate', $id, ['candidate_ref' => $ref]);

        return $id;
    }

    public function update(int $id, array $input): void
    {
        $data = [
            'first_name' => sanitize_text_field($input['first_name']),
            'last_name' => sanitize_text_field($input['last_name']),
            'email' => !empty($input['email']) ? sanitize_email($input['email']) : null,
            'phone' => !empty($input['phone']) ? sanitize_text_field($input['phone']) : null,
            'department' => !empty($input['department']) ? sanitize_text_field($input['department']) : null,
            'class' => !empty($input['class']) ? sanitize_text_field($input['class']) : null,
            'registration_number' => !empty($input['registration_number']) ? sanitize_text_field($input['registration_number']) : null,
            'status' => in_array($input['status'] ?? 'active', ['active', 'suspended', 'archived'], true)
                ? $input['status']
                : 'active',
        ];

        if (!empty($input['photo_attachment_id'])) {
            $data['photo_attachment_id'] = (int) $input['photo_attachment_id'];
        }

        $data['wp_user_id'] = !empty($input['wp_user_id']) ? (int) $input['wp_user_id'] : null;

        $this->repository->update($id, $data);

        AuditLogger::record('candidate.updated', 'candidate', $id);
    }

    public function delete(int $id): void
    {
        $this->repository->delete($id);
        AuditLogger::record('candidate.deleted', 'candidate', $id);
    }
}
