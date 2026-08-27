<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Institutions\InstitutionRepository;
use WPCBTPro\Security\Capabilities;

final class CandidatesAdminController
{
    public function __construct(
        private readonly CandidateRepository $repository,
        private readonly CandidateService $service,
        private readonly PhotoUploader $photoUploader,
        private readonly InstitutionContext $institutionContext,
        private readonly InstitutionRepository $institutionRepository,
    ) {
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT_CANDIDATES)) {
            wp_die(esc_html__('You do not have permission to manage candidates.', 'wp-cbt-pro'));
        }

        $action = sanitize_key($_GET['action'] ?? 'list');

        if ($action === 'delete') {
            $this->handleDelete();
            return;
        }

        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpcbtpro_candidate_nonce'])) {
            $errors = $this->handleSave();
            if ($errors === []) {
                return;
            }
            $action = empty($_POST['candidate_id']) ? 'new' : 'edit';
        }

        if (in_array($action, ['new', 'edit'], true)) {
            $this->renderForm($action, $errors);
            return;
        }

        $this->renderList();
    }

    private function scopedInstitutionId(): ?int
    {
        return current_user_can(Capabilities::MANAGE_CBT) ? null : $this->institutionContext->currentId();
    }

    private function renderList(): void
    {
        $table = new CandidatesListTable($this->repository, $this->scopedInstitutionId());
        $table->prepare_items();

        $addUrl = add_query_arg(['page' => 'wpcbtpro-candidates', 'action' => 'new'], admin_url('admin.php'));

        include WPCBTPRO_PATH . 'admin/views/candidates-list.php';
    }

    private function renderForm(string $action, array $errors): void
    {
        $candidate = null;
        if ($action === 'edit') {
            $id = (int) ($_GET['id'] ?? $_POST['candidate_id'] ?? 0);
            $candidate = $this->repository->find($id);
            if ($candidate === null) {
                wp_die(esc_html__('Candidate not found.', 'wp-cbt-pro'));
            }
        }

        $showInstitutionField = current_user_can(Capabilities::MANAGE_CBT);
        $institutions = $showInstitutionField ? $this->institutionRepository->all() : [];
        $currentInstitutionId = $this->institutionContext->currentId();

        include WPCBTPRO_PATH . 'admin/views/candidates-form.php';
    }

    /** @return array<string, string> validation errors, empty on success */
    private function handleSave(): array
    {
        check_admin_referer('wpcbtpro_save_candidate', 'wpcbtpro_candidate_nonce');

        $id = (int) ($_POST['candidate_id'] ?? 0);

        // Institution is fixed at creation and never changed via this form —
        // reassigning a candidate across tenants is a deliberate, separate
        // operation, not a side effect of editing a name field.
        if ($id !== 0) {
            $existing = $this->repository->find($id);
            $institutionId = $existing !== null ? (int) $existing['institution_id'] : null;
        } else {
            $institutionId = current_user_can(Capabilities::MANAGE_CBT) && !empty($_POST['institution_id'])
                ? (int) $_POST['institution_id']
                : $this->institutionContext->currentId();
        }

        $input = [
            'institution_id' => $institutionId,
            'first_name' => wp_unslash($_POST['first_name'] ?? ''),
            'last_name' => wp_unslash($_POST['last_name'] ?? ''),
            'email' => wp_unslash($_POST['email'] ?? ''),
            'phone' => wp_unslash($_POST['phone'] ?? ''),
            'department' => wp_unslash($_POST['department'] ?? ''),
            'class' => wp_unslash($_POST['class'] ?? ''),
            'registration_number' => wp_unslash($_POST['registration_number'] ?? ''),
            'status' => sanitize_key($_POST['status'] ?? 'active'),
            'wp_user_id' => (int) ($_POST['wp_user_id'] ?? 0),
        ];

        $errors = $this->service->validate($input);
        if ($errors !== []) {
            return $errors;
        }

        if (!empty($_FILES['wpcbtpro_photo']['name'])) {
            $attachmentId = $this->photoUploader->handleUpload('wpcbtpro_photo');
            if (is_wp_error($attachmentId)) {
                return ['photo' => $attachmentId->get_error_message()];
            }
            $input['photo_attachment_id'] = $attachmentId;
        }

        if ($id === 0) {
            $newId = $this->service->create($input);
            $redirect = add_query_arg(['page' => 'wpcbtpro-candidates', 'created' => 1], admin_url('admin.php'));
        } else {
            $this->service->update($id, $input);
            $redirect = add_query_arg(['page' => 'wpcbtpro-candidates', 'updated' => 1], admin_url('admin.php'));
        }

        wp_safe_redirect($redirect);
        exit;
    }

    private function handleDelete(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpcbtpro_delete_candidate_' . $id);

        $this->service->delete($id);

        wp_safe_redirect(add_query_arg(['page' => 'wpcbtpro-candidates', 'deleted' => 1], admin_url('admin.php')));
        exit;
    }
}
