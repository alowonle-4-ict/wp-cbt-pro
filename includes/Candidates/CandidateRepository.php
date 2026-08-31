<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

final class CandidateRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_candidates';
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findByRef(string $ref): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE candidate_ref = %s", $ref),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE email = %s", $email),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findByRegistrationNumber(int $institutionId, string $registrationNumber): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE institution_id = %d AND registration_number = %s",
                $institutionId,
                $registrationNumber
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * The "does this spreadsheet row already have a candidate?" check shared
     * by every import that can match into an existing record instead of
     * creating a duplicate (per-exam roster upload, exam-assignment upload):
     * registration number first (institution-scoped, since it's only
     * meaningful within one institution), then email (checked globally, same
     * as the unique index would enforce).
     *
     * @param array{registration_number?:string, email?:string} $input
     */
    public function findExistingForImportRow(array $input, int $institutionId): ?array
    {
        $registrationNumber = trim((string) ($input['registration_number'] ?? ''));
        if ($registrationNumber !== '') {
            $existing = $this->findByRegistrationNumber($institutionId, $registrationNumber);
            if ($existing !== null) {
                return $existing;
            }
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '') {
            $existing = $this->findByEmail($email);
            if ($existing !== null && (int) $existing['institution_id'] === $institutionId) {
                return $existing;
            }
        }

        return null;
    }

    public function findByWpUserId(int $wpUserId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE wp_user_id = %d", $wpUserId),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>> keyed by candidate id
     */
    public function findMany(array $ids): array
    {
        global $wpdb;

        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id IN ({$placeholders})", $ids),
            ARRAY_A
        ) ?: [];

        $candidates = [];
        foreach ($rows as $row) {
            $candidates[(int) $row['id']] = $row;
        }
        return $candidates;
    }

    public function countForInstitutionYear(int $institutionId, string $year): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table()} WHERE institution_id = %d AND candidate_ref LIKE %s",
            $institutionId,
            'CBT-' . $year . '-%'
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

    public function delete(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete($this->table(), ['id' => $id]) !== false;
    }

    /**
     * @param array{institution_id?:int, search?:string, per_page?:int, page?:int, orderby?:string, order?:string} $args
     * @return array<int, array<string, mixed>>
     */
    public function paginate(array $args): array
    {
        global $wpdb;

        $perPage = max(1, (int) ($args['per_page'] ?? 20));
        $page = max(1, (int) ($args['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildWhere($args);
        $orderby = $this->sanitizeOrderby($args['orderby'] ?? 'created_at');
        $order = strtoupper($args['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT * FROM {$this->table()} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $perPage;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return $rows ?: [];
    }

    public function count(array $args): int
    {
        global $wpdb;
        [$where, $params] = $this->buildWhere($args);
        $sql = "SELECT COUNT(*) FROM {$this->table()} {$where}";
        return $params === [] ? (int) $wpdb->get_var($sql) : (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /** @return array{0:string, 1:array<int, mixed>} */
    private function buildWhere(array $args): array
    {
        $clauses = [];
        $params = [];

        if (!empty($args['institution_id'])) {
            $clauses[] = 'institution_id = %d';
            $params[] = (int) $args['institution_id'];
        }

        if (!empty($args['search'])) {
            global $wpdb;
            $clauses[] = '(first_name LIKE %s OR last_name LIKE %s OR candidate_ref LIKE %s OR email LIKE %s)';
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }

    private function sanitizeOrderby(string $orderby): string
    {
        $allowed = ['created_at', 'first_name', 'last_name', 'candidate_ref', 'status'];
        return in_array($orderby, $allowed, true) ? $orderby : 'created_at';
    }
}
