<?php

declare(strict_types=1);

namespace WPCBTPro\Attempts;

/**
 * A per-(exam, candidate) exception to that exam's normal duration_minutes
 * and attempt_limit — an admin granting one candidate extra time or an
 * extra attempt, without touching the exam itself (which would give it to
 * every candidate). Read by AttemptService::startAttempt().
 */
final class CandidateExamOverrideRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_candidate_exam_overrides';
    }

    /** @return array{extra_minutes:int, extra_attempts:int} zeros when no override exists */
    public function find(int $examId, int $candidateId): array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE exam_id = %d AND candidate_id = %d",
            $examId,
            $candidateId
        ), ARRAY_A);

        return [
            'extra_minutes' => (int) ($row['extra_minutes'] ?? 0),
            'extra_attempts' => (int) ($row['extra_attempts'] ?? 0),
        ];
    }

    /** Adds to whatever override already exists — "grant one more attempt" is cumulative, not a reset. */
    public function addExtraAttempts(int $examId, int $candidateId, int $count): void
    {
        $this->upsert($examId, $candidateId, ['extra_attempts' => $this->find($examId, $candidateId)['extra_attempts'] + $count]);
    }

    public function addExtraMinutes(int $examId, int $candidateId, int $minutes): void
    {
        $this->upsert($examId, $candidateId, ['extra_minutes' => $this->find($examId, $candidateId)['extra_minutes'] + $minutes]);
    }

    /** @param array{extra_minutes?:int, extra_attempts?:int} $fields */
    private function upsert(int $examId, int $candidateId, array $fields): void
    {
        global $wpdb;

        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table()} WHERE exam_id = %d AND candidate_id = %d",
            $examId,
            $candidateId
        ));

        $fields['updated_at'] = current_time('mysql');

        if ($exists > 0) {
            $wpdb->update($this->table(), $fields, ['exam_id' => $examId, 'candidate_id' => $candidateId]);
            return;
        }

        $wpdb->insert($this->table(), array_merge(['exam_id' => $examId, 'candidate_id' => $candidateId], $fields));
    }
}
