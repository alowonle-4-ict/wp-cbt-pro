<?php

declare(strict_types=1);

namespace WPCBTPro\Attempts;

final class AttemptRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_attempts';
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    /** The attempt a candidate is currently sitting for this exam, if any. */
    public function findInProgress(int $examId, int $candidateId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE exam_id = %d AND candidate_id = %d AND status = 'in_progress' LIMIT 1",
            $examId,
            $candidateId
        ), ARRAY_A);
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function allForCandidate(int $candidateId): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE candidate_id = %d ORDER BY created_at DESC",
            $candidateId
        ), ARRAY_A) ?: [];
    }

    public function countForCandidateExam(int $examId, int $candidateId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table()} WHERE exam_id = %d AND candidate_id = %d",
            $examId,
            $candidateId
        ));
    }

    public function insert(array $data): int
    {
        global $wpdb;
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($this->table(), $data);
        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;
        return $wpdb->update($this->table(), $data, ['id' => $id]) !== false;
    }

    /**
     * @param string[] $statuses
     * @return array<int, array<string, mixed>>
     */
    public function allByStatuses(array $statuses): array
    {
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE status IN ({$placeholders}) ORDER BY server_end ASC",
            $statuses
        ), ARRAY_A) ?: [];
    }

    /** @return array<int, array<string, mixed>> every attempt still in progress whose server_end has passed */
    public function findExpiredInProgress(): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE status = 'in_progress' AND server_end <= %s",
            current_time('mysql')
        ), ARRAY_A) ?: [];
    }
}
