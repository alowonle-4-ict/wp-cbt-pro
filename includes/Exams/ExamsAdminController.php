<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Institutions\InstitutionRepository;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Security\Capabilities;

final class ExamsAdminController
{
    public function __construct(
        private readonly ExamRepository $repository,
        private readonly ExamService $service,
        private readonly QuestionRepository $questionRepository,
        private readonly InstitutionContext $institutionContext,
        private readonly InstitutionRepository $institutionRepository,
    ) {
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT_EXAMS)) {
            wp_die(esc_html__('You do not have permission to manage exams.', 'wp-cbt-pro'));
        }

        $action = sanitize_key($_GET['action'] ?? 'list');

        if ($action === 'delete') {
            $this->handleDelete();
            return;
        }

        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpcbtpro_exam_nonce'])) {
            $errors = $this->handleSave();
            if ($errors === []) {
                return;
            }
            $action = empty($_POST['exam_id']) ? 'new' : 'edit';
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
            $id = (int) ($_GET['id'] ?? $_POST['exam_id'] ?? 0);
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

        $id = (int) ($_POST['exam_id'] ?? 0);

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
            'name' => wp_unslash($_POST['name'] ?? ''),
            'description' => wp_unslash($_POST['description'] ?? ''),
            'instructions' => wp_unslash($_POST['instructions'] ?? ''),
            'subject' => wp_unslash($_POST['subject'] ?? ''),
            'duration_minutes' => $_POST['duration_minutes'] ?? 0,
            'start_at' => wp_unslash($_POST['start_at'] ?? ''),
            'end_at' => wp_unslash($_POST['end_at'] ?? ''),
            'attempt_limit' => $_POST['attempt_limit'] ?? 1,
            'pass_mark' => wp_unslash($_POST['pass_mark'] ?? ''),
            'randomize_questions' => $_POST['randomize_questions'] ?? '',
            'randomize_options' => $_POST['randomize_options'] ?? '',
            'negative_marking' => $_POST['negative_marking'] ?? '',
            'camera_required' => $_POST['camera_required'] ?? '',
            'microphone_mode' => sanitize_key($_POST['microphone_mode'] ?? 'off'),
            'identity_verification' => $_POST['identity_verification'] ?? '',
            'snapshot_interval_seconds' => $_POST['snapshot_interval_seconds'] ?? '',
            'fullscreen_required' => $_POST['fullscreen_required'] ?? '',
            'result_visibility' => sanitize_key($_POST['result_visibility'] ?? 'immediate'),
            'status' => sanitize_key($_POST['status'] ?? 'draft'),
        ];

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
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpcbtpro_delete_exam_' . $id);

        $this->service->delete($id);

        wp_safe_redirect(add_query_arg(['page' => 'wpcbtpro-exams', 'deleted' => 1], admin_url('admin.php')));
        exit;
    }
}
