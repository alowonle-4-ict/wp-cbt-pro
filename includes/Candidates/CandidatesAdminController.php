<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Institutions\InstitutionRepository;
use WPCBTPro\Security\Capabilities;

final class CandidatesAdminController
{
    /**
     * A POST/delete on this page is processed on admin_init — before
     * WordPress starts streaming the admin page's HTML — because
     * wp_safe_redirect() from inside the add_submenu_page() render
     * callback itself is always too late: WP has already sent the page
     * header by the time that callback runs, so the redirect silently
     * fails ("headers already sent") and the admin is left looking at a
     * blank page. render() only ever displays; it never mutates or redirects.
     *
     * @var array<string, string>|null
     */
    private ?array $pendingErrors = null;
    private ?string $pendingAction = null;

    public function __construct(
        private readonly CandidateRepository $repository,
        private readonly CandidateService $service,
        private readonly PhotoUploader $photoUploader,
        private readonly InstitutionContext $institutionContext,
        private readonly InstitutionRepository $institutionRepository,
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'maybeProcessRequest']);
    }

    public function maybeProcessRequest(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only: confirms this hook applies to our own page before doing anything.
        if (($_GET['page'] ?? '') !== 'wpcbtpro-candidates') {
            return;
        }

        if (!current_user_can(Capabilities::MANAGE_CBT_CANDIDATES)) {
            return; // render() will wp_die() with the proper message for a real page view.
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only routing; the real check happens in handleDelete()/handleSave() below.
        if (($_GET['action'] ?? '') === 'delete') {
            $this->handleDelete();
            return;
        }

        // handleSave() runs check_admin_referer() as its first statement; the reads below only decide whether to dispatch there.
        // phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_candidate_nonce'])) {
            $errors = $this->handleSave();
            if ($errors !== []) {
                $this->pendingErrors = $errors;
                $this->pendingAction = empty($_POST['candidate_id']) ? 'new' : 'edit';
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT_CANDIDATES)) {
            wp_die(esc_html__('You do not have permission to manage candidates.', 'wp-cbt-pro'));
        }

        $errors = $this->pendingErrors ?? [];
        $action = $this->pendingAction ?? sanitize_key($_GET['action'] ?? 'list');

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
            // phpcs:ignore WordPress.Security.NonceVerification -- read-only: resolves which record to display, not a state change.
            $id = isset($_GET['id']) ? absint($_GET['id']) : (isset($_POST['candidate_id']) ? absint($_POST['candidate_id']) : 0);
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

        $id = isset($_POST['candidate_id']) ? absint($_POST['candidate_id']) : 0;

        // Institution is fixed at creation and never changed via this form —
        // reassigning a candidate across tenants is a deliberate, separate
        // operation, not a side effect of editing a name field.
        if ($id !== 0) {
            $existing = $this->repository->find($id);
            $institutionId = $existing !== null ? (int) $existing['institution_id'] : null;
        } else {
            $institutionId = current_user_can(Capabilities::MANAGE_CBT) && !empty($_POST['institution_id'])
                ? absint($_POST['institution_id'])
                : $this->institutionContext->currentId();
        }

        $input = [
            'institution_id' => $institutionId,
            'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'phone' => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'department' => sanitize_text_field(wp_unslash($_POST['department'] ?? '')),
            'class' => sanitize_text_field(wp_unslash($_POST['class'] ?? '')),
            'registration_number' => sanitize_text_field(wp_unslash($_POST['registration_number'] ?? '')),
            'status' => sanitize_key($_POST['status'] ?? 'active'),
            'wp_user_id' => isset($_POST['wp_user_id']) ? absint($_POST['wp_user_id']) : 0,
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the id is only used to build the nonce action string; check_admin_referer() below rejects any tampering.
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('wpcbtpro_delete_candidate_' . $id);

        $this->service->delete($id);

        wp_safe_redirect(add_query_arg(['page' => 'wpcbtpro-candidates', 'deleted' => 1], admin_url('admin.php')));
        exit;
    }
}
