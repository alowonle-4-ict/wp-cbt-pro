<?php

declare(strict_types=1);

namespace WPCBTPro\DSA;

/**
 * A final-snapshot table, same role as code_submissions (§35) — captured
 * once at attempt finalization via DsaType::onFinalize(), not on every
 * autosave.
 */
final class DsaStateRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_dsa_states';
    }

    public function upsert(int $answerId, string $stateJson, bool $isValid): void
    {
        global $wpdb;

        $existing = $this->findByAnswer($answerId);
        $data = ['state_json' => $stateJson, 'is_valid' => $isValid ? 1 : 0];

        if ($existing === null) {
            $wpdb->insert($this->table(), array_merge($data, ['answer_id' => $answerId]));
            return;
        }

        $wpdb->update($this->table(), $data, ['id' => (int) $existing['id']]);
    }

    public function findByAnswer(int $answerId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE answer_id = %d", $answerId), ARRAY_A);
        return $row ?: null;
    }
}
