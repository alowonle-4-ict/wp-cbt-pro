<?php

declare(strict_types=1);

namespace WPCBTPro\Camera;

final class CameraSessionRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_camera_sessions';
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

    /**
     * @param int[] $attemptIds
     * @return array<int, array<string, mixed>> keyed by attempt_id
     */
    public function findManyByAttempts(array $attemptIds): array
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
            $byAttempt[(int) $row['attempt_id']] = $row;
        }
        return $byAttempt;
    }

    public function create(int $attemptId, string $state): int
    {
        global $wpdb;
        $wpdb->insert($this->table(), ['attempt_id' => $attemptId, 'state' => $state]);
        return (int) $wpdb->insert_id;
    }

    public function updateState(int $id, string $state, ?string $connectedAt = null, ?string $disconnectedAt = null): void
    {
        global $wpdb;

        $data = ['state' => $state];
        if ($connectedAt !== null) {
            $data['connected_at'] = $connectedAt;
        }
        if ($disconnectedAt !== null) {
            $data['disconnected_at'] = $disconnectedAt;
        }

        $wpdb->update($this->table(), $data, ['id' => $id]);
    }
}
