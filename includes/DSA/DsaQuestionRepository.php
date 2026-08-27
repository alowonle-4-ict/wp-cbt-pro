<?php

declare(strict_types=1);

namespace WPCBTPro\DSA;

final class DsaQuestionRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_dsa_questions';
    }

    public function find(int $questionId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE question_id = %d", $questionId),
            ARRAY_A
        );
        if ($row === null) {
            return null;
        }

        $row['operations'] = json_decode((string) $row['operations_json'], true) ?: [];
        return $row;
    }

    /**
     * @param array{structure: string, mode: string, operations: array<int, array{op: string, arg: string|null}>} $data
     */
    public function upsert(int $questionId, array $data): void
    {
        global $wpdb;

        $row = [
            'question_id' => $questionId,
            'structure' => $data['structure'],
            'mode' => $data['mode'],
            'operations_json' => wp_json_encode($data['operations']),
        ];

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table()} WHERE question_id = %d", $questionId));

        if ((int) $exists > 0) {
            $wpdb->update($this->table(), $row, ['question_id' => $questionId]);
            return;
        }

        $wpdb->insert($this->table(), $row);
    }
}
