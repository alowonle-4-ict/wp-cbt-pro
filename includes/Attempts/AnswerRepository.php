<?php

declare(strict_types=1);

namespace WPCBTPro\Attempts;

/**
 * Upserts are keyed on the (attempt_id, question_id) unique index in the
 * schema — so a retried autosave from a flaky connection can never create a
 * duplicate answer or silently overwrite a newer one with a stale request
 * (§10).
 */
final class AnswerRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_answers';
    }

    public function find(int $attemptId, int $questionId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE attempt_id = %d AND question_id = %d",
            $attemptId,
            $questionId
        ), ARRAY_A);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> keyed by question_id */
    public function allForAttempt(int $attemptId): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE attempt_id = %d", $attemptId),
            ARRAY_A
        ) ?: [];

        $byQuestion = [];
        foreach ($rows as $row) {
            $byQuestion[(int) $row['question_id']] = $row;
        }
        return $byQuestion;
    }

    /**
     * @param int[] $attemptIds
     * @return array<int, array<int, array<string, mixed>>> attempt_id => (question_id => row)
     */
    public function allForAttempts(array $attemptIds): array
    {
        global $wpdb;

        $attemptIds = array_values(array_unique(array_map('intval', $attemptIds)));
        if ($attemptIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($attemptIds), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE attempt_id IN ({$placeholders})", $attemptIds),
            ARRAY_A
        ) ?: [];

        $byAttempt = [];
        foreach ($rows as $row) {
            $byAttempt[(int) $row['attempt_id']][(int) $row['question_id']] = $row;
        }
        return $byAttempt;
    }

    public function upsert(int $attemptId, int $questionId, string $value, bool $markedForReview): void
    {
        global $wpdb;

        $existing = $this->find($attemptId, $questionId);
        $data = [
            'value' => $value,
            'marked_for_review' => $markedForReview ? 1 : 0,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing === null) {
            $wpdb->insert($this->table(), array_merge($data, [
                'attempt_id' => $attemptId,
                'question_id' => $questionId,
            ]));
            return;
        }

        $wpdb->update($this->table(), $data, ['id' => (int) $existing['id']]);
    }
}
