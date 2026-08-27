<?php

declare(strict_types=1);

namespace WPCBTPro\Results;

final class ResultRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_results';
    }

    public function findByAttempt(int $attemptId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE attempt_id = %d", $attemptId),
            ARRAY_A
        );
        return $row ?: null;
    }

    /** Insert or replace the result row for an attempt — grading is always a full recompute, never a patch. */
    public function upsert(int $attemptId, array $data): void
    {
        global $wpdb;

        $existing = $this->findByAttempt($attemptId);
        $data['attempt_id'] = $attemptId;

        if ($existing === null) {
            $wpdb->insert($this->table(), $data);
            return;
        }

        $wpdb->update($this->table(), $data, ['id' => (int) $existing['id']]);
    }

    /**
     * One row per completed attempt for an exam, joined with just enough
     * attempt data (candidate_id, attempt status) to build a report without
     * a second query per row for that part.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allForExam(int $examId): array
    {
        global $wpdb;
        $attemptsTable = $wpdb->prefix . 'cbt_attempts';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, a.candidate_id, a.exam_id, a.status AS attempt_status, a.submitted_at
             FROM {$this->table()} r
             INNER JOIN {$attemptsTable} a ON a.id = r.attempt_id
             WHERE a.exam_id = %d
             ORDER BY r.percentage DESC",
            $examId
        ), ARRAY_A) ?: [];
    }

    public function release(int $resultId): void
    {
        global $wpdb;
        $wpdb->update($this->table(), ['released_at' => current_time('mysql')], ['id' => $resultId]);
    }

    /** @return int how many previously-unreleased results were released */
    public function releaseAllForExam(int $examId): int
    {
        $released = 0;
        foreach ($this->allForExam($examId) as $result) {
            if (empty($result['released_at'])) {
                $this->release((int) $result['id']);
                $released++;
            }
        }
        return $released;
    }
}
