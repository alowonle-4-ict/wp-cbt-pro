<?php

declare(strict_types=1);

namespace WPCBTPro\Institutions;

final class InstitutionRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_institutions';
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table()} ORDER BY name ASC", ARRAY_A);
        return $rows ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function create(string $name, string $plan = 'free'): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert($this->table(), [
            'name' => $name,
            'plan' => $plan,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Single-institution installs (the common case) should work with zero
     * setup — create one default tenant on activation rather than forcing
     * every admin to configure "institutions" before they can add a
     * candidate.
     */
    public function ensureDefault(): int
    {
        $existing = get_option('wpcbtpro_default_institution_id');
        if ($existing && $this->find((int) $existing) !== null) {
            return (int) $existing;
        }

        $all = $this->all();
        if (!empty($all)) {
            $id = (int) $all[0]['id'];
            update_option('wpcbtpro_default_institution_id', $id);
            return $id;
        }

        $id = $this->create(get_bloginfo('name') ?: 'Default Institution');
        update_option('wpcbtpro_default_institution_id', $id);
        return $id;
    }
}
