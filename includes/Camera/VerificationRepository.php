<?php

declare(strict_types=1);

namespace WPCBTPro\Camera;

use WPCBTPro\Camera\Contracts\VerificationStatus;

final class VerificationRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_verification_records';
    }

    public function insert(int $attemptId, string $status, ?int $capturedAttachmentId): int
    {
        global $wpdb;
        $wpdb->insert($this->table(), [
            'attempt_id' => $attemptId,
            'status' => $status,
            'captured_image_attachment_id' => $capturedAttachmentId,
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public function findLatestByAttempt(int $attemptId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE attempt_id = %d ORDER BY id DESC LIMIT 1",
            $attemptId
        ), ARRAY_A);
        return $row ?: null;
    }

    public function updateStatus(int $id, string $status, ?int $reviewerId = null): void
    {
        global $wpdb;
        $wpdb->update($this->table(), [
            'status' => $status,
            'reviewer_id' => $reviewerId,
        ], ['id' => $id]);
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> oldest first, so the queue clears in the order candidates were held up */
    public function allByStatus(string $status, int $limit = 100): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE status = %s ORDER BY created_at ASC LIMIT %d",
            $status,
            $limit
        ), ARRAY_A) ?: [];
    }

    public function clearCapturedImage(int $id): void
    {
        global $wpdb;
        $wpdb->update($this->table(), ['captured_image_attachment_id' => null], ['id' => $id]);
    }

    /** Reviewed records past the retention window still holding an image (§20). */
    public function allReviewedOlderThan(string $cutoff): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE status != %s AND created_at < %s AND captured_image_attachment_id IS NOT NULL",
            VerificationStatus::ReviewRequired->value,
            $cutoff
        ), ARRAY_A) ?: [];
    }
}
