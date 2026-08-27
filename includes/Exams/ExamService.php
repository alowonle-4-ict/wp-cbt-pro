<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

use WPCBTPro\Security\AuditLogger;

final class ExamService
{
    private const MICROPHONE_MODES = ['off', 'camera_only', 'camera_and_mic'];
    private const RESULT_VISIBILITIES = ['immediate', 'delayed', 'manual'];
    private const STATUSES = ['draft', 'active', 'closed'];

    public function __construct(private readonly ExamRepository $repository)
    {
    }

    /** @return array<string, string> field => error message */
    public function validate(array $input): array
    {
        $errors = [];

        if (trim($input['name'] ?? '') === '') {
            $errors['name'] = __('Exam name is required.', 'wp-cbt-pro');
        }
        if ((int) ($input['duration_minutes'] ?? 0) < 1) {
            $errors['duration_minutes'] = __('Duration must be at least 1 minute.', 'wp-cbt-pro');
        }
        if (!empty($input['start_at']) && !empty($input['end_at']) && strtotime($input['end_at']) <= strtotime($input['start_at'])) {
            $errors['end_at'] = __('End date must be after the start date.', 'wp-cbt-pro');
        }
        if ((int) ($input['attempt_limit'] ?? 1) < 1) {
            $errors['attempt_limit'] = __('Attempt limit must be at least 1.', 'wp-cbt-pro');
        }
        $passMark = $input['pass_mark'] ?? '';
        if ($passMark !== '' && $passMark !== null && ((float) $passMark < 0 || (float) $passMark > 100)) {
            $errors['pass_mark'] = __('Pass mark must be between 0 and 100.', 'wp-cbt-pro');
        }
        if (empty($input['institution_id'])) {
            $errors['institution_id'] = __('An institution is required.', 'wp-cbt-pro');
        }

        return $errors;
    }

    public function create(array $input): int
    {
        $data = $this->sanitize($input);
        $data['institution_id'] = (int) $input['institution_id'];
        $data['status'] = 'draft'; // every new exam starts unpublished, regardless of posted status

        $id = $this->repository->insert($data);
        AuditLogger::record('exam.created', 'exam', $id, ['name' => $data['name']]);

        return $id;
    }

    public function update(int $id, array $input): void
    {
        $this->repository->update($id, $this->sanitize($input));
        AuditLogger::record('exam.updated', 'exam', $id);
    }

    public function delete(int $id): void
    {
        $this->repository->delete($id);
        AuditLogger::record('exam.deleted', 'exam', $id);
    }

    /**
     * @param array<int, array{question_id:int, pool_id?:?string, sort_order?:int}> $assignments
     * @param array<int, array{pool_key:string, name:string, draw_count:int}> $pools
     */
    public function saveQuestionAssignments(int $examId, array $assignments, array $pools): void
    {
        $this->repository->setPools($examId, $pools);
        $this->repository->setQuestions($examId, $assignments);

        AuditLogger::record('exam.questions_updated', 'exam', $examId, [
            'question_count' => count($assignments),
            'pool_count' => count($pools),
        ]);
    }

    private function sanitize(array $input): array
    {
        return [
            'name' => sanitize_text_field($input['name']),
            'description' => wp_kses_post($input['description'] ?? ''),
            'instructions' => wp_kses_post($input['instructions'] ?? ''),
            'subject' => sanitize_text_field($input['subject'] ?? ''),
            'duration_minutes' => max(1, (int) $input['duration_minutes']),
            'start_at' => !empty($input['start_at']) ? gmdate('Y-m-d H:i:s', (int) strtotime($input['start_at'])) : null,
            'end_at' => !empty($input['end_at']) ? gmdate('Y-m-d H:i:s', (int) strtotime($input['end_at'])) : null,
            'attempt_limit' => max(1, (int) ($input['attempt_limit'] ?? 1)),
            'pass_mark' => ($input['pass_mark'] ?? '') !== '' ? (float) $input['pass_mark'] : null,
            'randomize_questions' => !empty($input['randomize_questions']) ? 1 : 0,
            'randomize_options' => !empty($input['randomize_options']) ? 1 : 0,
            'negative_marking' => !empty($input['negative_marking']) ? 1 : 0,
            'camera_required' => !empty($input['camera_required']) ? 1 : 0,
            'microphone_mode' => in_array($input['microphone_mode'] ?? 'off', self::MICROPHONE_MODES, true)
                ? $input['microphone_mode'] : 'off',
            'identity_verification' => !empty($input['identity_verification']) ? 1 : 0,
            'snapshot_interval_seconds' => !empty($input['snapshot_interval_seconds'])
                ? (int) $input['snapshot_interval_seconds'] : null,
            'fullscreen_required' => !empty($input['fullscreen_required']) ? 1 : 0,
            'result_visibility' => in_array($input['result_visibility'] ?? 'immediate', self::RESULT_VISIBILITIES, true)
                ? $input['result_visibility'] : 'immediate',
            'status' => in_array($input['status'] ?? 'draft', self::STATUSES, true) ? $input['status'] : 'draft',
        ];
    }
}
