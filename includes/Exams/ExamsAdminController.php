<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Institutions\InstitutionRepository;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Security\Capabilities;

final class ExamsAdminController
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
        private readonly ExamRepository $repository,
        private readonly ExamService $service,
        private readonly QuestionRepository $questionRepository,
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
        if (($_GET['page'] ?? '') !== 'wpcbtpro-exams') {
            return;
        }

        if (!current_user_can(Capabilities::MANAGE_CBT_EXAMS)) {
            return; // render() will wp_die() with the proper message for a real page view.
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only routing; the real check happens in handleDelete()/handleSave() below.
        if (($_GET['action'] ?? '') === 'delete') {
            $this->handleDelete();
            return;
        }

        // handleSave() runs check_admin_referer() as its first statement; the reads below only decide whether to dispatch there.
        // phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_exam_nonce'])) {
            $errors = $this->handleSave();
            if ($errors !== []) {
                $this->pendingErrors = $errors;
                $this->pendingAction = empty($_POST['exam_id']) ? 'new' : 'edit';
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT_EXAMS)) {
            wp_die(esc_html__('You do not have permission to manage exams.', 'wp-cbt-pro'));
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
        $table = new ExamsListTable($this->repository, $this->scopedInstitutionId());
        $table->prepare_items();

        $addUrl = add_query_arg(['page' => 'wpcbtpro-exams', 'action' => 'new'], admin_url('admin.php'));

        include WPCBTPRO_PATH . 'admin/views/exams-list.php';
    }

    private function renderForm(string $action, array $errors): void
    {
        $exam = null;
        $assignments = [];
        $pools = [];

        if ($action === 'edit') {
            // phpcs:ignore WordPress.Security.NonceVerification -- read-only: resolves which record to display, not a state change.
            $id = isset($_GET['id']) ? absint($_GET['id']) : (isset($_POST['exam_id']) ? absint($_POST['exam_id']) : 0);
            $exam = $this->repository->find($id);
            if ($exam === null) {
                wp_die(esc_html__('Exam not found.', 'wp-cbt-pro'));
            }
            $assignments = $this->repository->questionsForExam($id);
            $pools = $this->repository->poolsForExam($id);
        }

        $institutionId = $exam['institution_id'] ?? $this->institutionContext->currentId();
        $availableQuestions = $institutionId !== null
            ? $this->questionRepository->allActiveForInstitution((int) $institutionId)
            : [];

        $assignedByQuestionId = [];
        foreach ($assignments as $row) {
            $assignedByQuestionId[(int) $row['question_id']] = $row;
        }

        $showInstitutionField = current_user_can(Capabilities::MANAGE_CBT);
        $institutions = $showInstitutionField ? $this->institutionRepository->all() : [];

        include WPCBTPRO_PATH . 'admin/views/exams-form.php';
    }

    /** @return array<string, string> validation errors, empty on success */
    private function handleSave(): array
    {
        check_admin_referer('wpcbtpro_save_exam', 'wpcbtpro_exam_nonce');

        $id = isset($_POST['exam_id']) ? absint($_POST['exam_id']) : 0;

        if ($id !== 0) {
            $existing = $this->repository->find($id);
            $institutionId = $existing !== null ? (int) $existing['institution_id'] : null;
        } else {
            $institutionId = current_user_can(Capabilities::MANAGE_CBT) && !empty($_POST['institution_id'])
                ? absint($_POST['institution_id'])
                : $this->institutionContext->currentId();
        }

        // duration_minutes, attempt_limit, pass_mark, the *_required/*_marking/randomize_* flags, and snapshot_interval_seconds
        // are read raw here but ExamService::sanitize() (called from validate()/create()/update()) casts every one of them
        // to (int)/(float)/1-or-0 before anything reaches the database, so no further sanitization belongs at this read site.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput
        $input = [
            'institution_id' => $institutionId,
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            'instructions' => sanitize_textarea_field(wp_unslash($_POST['instructions'] ?? '')),
            'subject' => sanitize_text_field(wp_unslash($_POST['subject'] ?? '')),
            'duration_minutes' => $_POST['duration_minutes'] ?? 0,
            'start_at' => sanitize_text_field(wp_unslash($_POST['start_at'] ?? '')),
            'end_at' => sanitize_text_field(wp_unslash($_POST['end_at'] ?? '')),
            'attempt_limit' => $_POST['attempt_limit'] ?? 1,
            'pass_mark' => sanitize_text_field(wp_unslash($_POST['pass_mark'] ?? '')),
            'randomize_questions' => $_POST['randomize_questions'] ?? '',
            'randomize_options' => $_POST['randomize_options'] ?? '',
            'negative_marking' => $_POST['negative_marking'] ?? '',
            'camera_required' => $_POST['camera_required'] ?? '',
            'microphone_mode' => sanitize_key($_POST['microphone_mode'] ?? 'off'),
            'identity_verification' => $_POST['identity_verification'] ?? '',
            'snapshot_interval_seconds' => $_POST['snapshot_interval_seconds'] ?? '',
            'fullscreen_required' => $_POST['fullscreen_required'] ?? '',
            'result_visibility' => sanitize_key($_POST['result_visibility'] ?? 'immediate'),
            'restrict_to_roster' => $_POST['restrict_to_roster'] ?? '',
            'status' => sanitize_key($_POST['status'] ?? 'draft'),
        ];
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput

        $errors = $this->service->validate($input);
        if ($errors !== []) {
            return $errors;
        }

        if ($id === 0) {
            $id = $this->service->create($input);
            $redirectArgs = ['page' => 'wpcbtpro-exams', 'action' => 'edit', 'id' => $id, 'created' => 1];
        } else {
            $this->service->update($id, $input);
            $redirectArgs = ['page' => 'wpcbtpro-exams', 'action' => 'edit', 'id' => $id, 'updated' => 1];
        }

        [$assignments, $pools] = $this->extractQuestionAssignments($_POST);
        $this->service->saveQuestionAssignments($id, $assignments, $pools);

        wp_safe_redirect(add_query_arg($redirectArgs, admin_url('admin.php')));
        exit;
    }

    /**
     * @return array{
     *     0: array<int, array{question_id:int, pool_id:?string, sort_order:int}>,
     *     1: array<int, array{pool_key:string, name:string, draw_count:int}>
     * }
     */
    private function extractQuestionAssignments(array $post): array
    {
        $assignments = [];
        foreach ((array) ($post['assign'] ?? []) as $questionId => $row) {
            if (empty($row['include'])) {
                continue;
            }

            $poolKey = trim(sanitize_key($row['pool'] ?? ''));
            $assignments[] = [
                'question_id' => (int) $questionId,
                'pool_id' => $poolKey !== '' ? $poolKey : null,
                'sort_order' => (int) ($row['order'] ?? 0),
            ];
        }

        $pools = [];
        foreach ((array) ($post['pools'] ?? []) as $pool) {
            $key = trim(sanitize_key($pool['key'] ?? ''));
            $name = trim(sanitize_text_field($pool['name'] ?? ''));
            if ($key === '' || $name === '') {
                continue;
            }

            $pools[] = [
                'pool_key' => $key,
                'name' => $name,
                'draw_count' => max(1, (int) ($pool['draw_count'] ?? 1)),
            ];
        }

        return [$assignments, $pools];
    }

    private function handleDelete(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the id is only used to build the nonce action string; check_admin_referer() below rejects any tampering.
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('wpcbtpro_delete_exam_' . $id);

        $this->service->delete($id);

        wp_safe_redirect(add_query_arg(['page' => 'wpcbtpro-exams', 'deleted' => 1], admin_url('admin.php')));
        exit;
    }
}
