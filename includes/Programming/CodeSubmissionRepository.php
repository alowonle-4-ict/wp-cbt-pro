<?php

declare(strict_types=1);

namespace WPCBTPro\Programming;

/**
 * A code_submissions row is the final snapshot taken once, at attempt
 * finalization (§35) — distinct from the continuously-autosaved draft in
 * wp_cbt_answers, which keeps changing until the moment the exam ends.
 */
final class CodeSubmissionRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_code_submissions';
    }

    public function insert(int $answerId, string $language, string $source): int
    {
        global $wpdb;
        $wpdb->insert($this->table(), [
            'answer_id' => $answerId,
            'language' => $language,
            'source' => $source,
            'status' => 'pending',
            'submitted_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public function findByAnswer(int $answerId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE answer_id = %d ORDER BY id DESC LIMIT 1", $answerId),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function findPending(int $limit = 10): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE status = 'pending' ORDER BY submitted_at ASC LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];
    }

    public function markCompleted(int $id, ?string $compileError): void
    {
        global $wpdb;
        $wpdb->update($this->table(), [
            'status' => 'completed',
            'compile_error' => $compileError,
            'graded_at' => current_time('mysql'),
        ], ['id' => $id]);
    }

    public function markFailed(int $id, string $reason): void
    {
        global $wpdb;
        $wpdb->update($this->table(), [
            'status' => 'failed',
            'compile_error' => $reason,
            'graded_at' => current_time('mysql'),
        ], ['id' => $id]);
    }
}
