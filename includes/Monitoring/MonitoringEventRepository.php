<?php

declare(strict_types=1);

namespace WPCBTPro\Monitoring;

/**
 * Append-only proctoring log (§13, §14). Nothing that writes here also
 * decides what the event means — recording and interpreting a signal are
 * kept apart so this table can be a plain audit trail an invigilator reads,
 * never a place where the system quietly convicts a candidate.
 */
final class MonitoringEventRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_monitoring_events';
    }

    /** @param array<string, mixed> $payload */
    public function record(int $attemptId, string $eventType, array $payload = []): int
    {
        global $wpdb;
        $wpdb->insert($this->table(), [
            'attempt_id' => $attemptId,
            'event_type' => $eventType,
            'payload' => $payload === [] ? null : wp_json_encode($payload),
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    /** @return array<int, array<string, mixed>> */
    public function allForAttempt(int $attemptId): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE attempt_id = %d ORDER BY created_at ASC", $attemptId),
            ARRAY_A
        ) ?: [];
    }

    /**
     * @param int[] $attemptIds
     * @return array<int, array<int, array<string, mixed>>> attempt_id => events, each list oldest first
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
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE attempt_id IN ({$placeholders}) ORDER BY created_at ASC",
                $attemptIds
            ),
            ARRAY_A
        ) ?: [];

        $byAttempt = [];
        foreach ($rows as $row) {
            $byAttempt[(int) $row['attempt_id']][] = $row;
        }
        return $byAttempt;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findSnapshotsOlderThan(string $cutoff): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE event_type = 'CAMERA_SNAPSHOT' AND created_at < %s AND payload IS NOT NULL",
            $cutoff
        ), ARRAY_A) ?: [];
    }

    /** Purges the stored image reference while keeping the event itself — the timeline survives, the picture doesn't (§20). */
    public function redactPayload(int $id): void
    {
        global $wpdb;
        $wpdb->update($this->table(), ['payload' => null], ['id' => $id]);
    }
}
