<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

/**
 * A per-exam eligibility list: when an exam's restrict_to_roster flag is on,
 * AttemptService::startAttempt() only lets a candidate begin a new attempt
 * if they're a member here. Membership is a plain (exam_id, candidate_id)
 * pair — nothing about the candidate's own record changes when they're
 * added to or removed from a roster.
 */
final class ExamCandidateRosterRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_exam_candidates';
    }

    public function isMember(int $examId, int $candidateId): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$this->table()} WHERE exam_id = %d AND candidate_id = %d",
            $examId,
            $candidateId
        ));
    }

    public function add(int $examId, int $candidateId): void
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $wpdb->prepare() below builds the full query; this is just the INSERT IGNORE verb wpdb->insert() has no equivalent for.
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$this->table()} (exam_id, candidate_id, created_at) VALUES (%d, %d, %s)",
            $examId,
            $candidateId,
            current_time('mysql')
        ));
    }

    public function remove(int $examId, int $candidateId): void
    {
        global $wpdb;
        $wpdb->delete($this->table(), ['exam_id' => $examId, 'candidate_id' => $candidateId]);
    }

    public function countForExam(int $examId): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table()} WHERE exam_id = %d",
            $examId
        ));
    }

    /** @return array<int, array<string, mixed>> this exam's roster, joined with candidate details */
    public function candidatesForExam(int $examId): array
    {
        global $wpdb;
        $candidatesTable = $wpdb->prefix . 'cbt_candidates';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.* FROM {$candidatesTable} c
             INNER JOIN {$this->table()} r ON r.candidate_id = c.id
             WHERE r.exam_id = %d
             ORDER BY c.last_name ASC, c.first_name ASC",
            $examId
        ), ARRAY_A);

        return $rows ?: [];
    }
}
