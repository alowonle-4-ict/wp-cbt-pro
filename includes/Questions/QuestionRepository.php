<?php

declare(strict_types=1);

namespace WPCBTPro\Questions;

final class QuestionRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_questions';
    }

    private function optionsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_question_options';
    }

    private function programmingTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_programming_questions';
    }

    private function programmingTestCasesTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_programming_test_cases';
    }

    private function dsaTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_dsa_questions';
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array{label:string, is_correct?:bool, sort_order?:int}> $options
     */
    public function insert(array $data, array $options = []): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $wpdb->insert($this->table(), $data);
        $questionId = (int) $wpdb->insert_id;

        foreach ($options as $option) {
            $wpdb->insert($this->optionsTable(), [
                'question_id' => $questionId,
                'label' => $option['label'],
                'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                'sort_order' => $option['sort_order'] ?? 0,
            ]);
        }

        return $questionId;
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        if ($row === null) {
            return null;
        }

        $row['options'] = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->optionsTable()} WHERE question_id = %d ORDER BY sort_order ASC", $id),
            ARRAY_A
        ) ?: [];

        $row['programming'] = null;
        if ($row['type'] === 'programming') {
            $programming = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$this->programmingTable()} WHERE question_id = %d", $id),
                ARRAY_A
            );
            if ($programming !== null) {
                $programming['test_cases'] = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM {$this->programmingTestCasesTable()} WHERE question_id = %d ORDER BY sort_order ASC", $id),
                    ARRAY_A
                ) ?: [];
                $row['programming'] = $programming;
            }
        }

        $row['dsa'] = null;
        if ($row['type'] === 'dsa') {
            $dsa = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$this->dsaTable()} WHERE question_id = %d", $id),
                ARRAY_A
            );
            if ($dsa !== null) {
                $dsa['operations'] = json_decode((string) $dsa['operations_json'], true) ?: [];
                $row['dsa'] = $dsa;
            }
        }

        return $row;
    }

    /**
     * Batched counterpart to find() — grading an attempt calls this once for
     * every question on the exam, so fetching one-by-one turned every
     * submission into 1 + 3N queries. This does it in a fixed 5 regardless
     * of how many questions the exam has (§15 performance).
     *
     * @param int[] $ids
     * @return array<int, array<string, mixed>> keyed by question id
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

        $questions = [];
        foreach ($rows as $row) {
            $row['options'] = [];
            $row['programming'] = null;
            $row['dsa'] = null;
            $questions[(int) $row['id']] = $row;
        }

        if ($questions === []) {
            return [];
        }

        $questionIds = array_keys($questions);
        $qPlaceholders = implode(',', array_fill(0, count($questionIds), '%d'));

        $options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->optionsTable()} WHERE question_id IN ({$qPlaceholders}) ORDER BY sort_order ASC",
                $questionIds
            ),
            ARRAY_A
        ) ?: [];
        foreach ($options as $option) {
            $questions[(int) $option['question_id']]['options'][] = $option;
        }

        $programmingRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->programmingTable()} WHERE question_id IN ({$qPlaceholders})",
                $questionIds
            ),
            ARRAY_A
        ) ?: [];
        $programmingByQuestion = [];
        foreach ($programmingRows as $programming) {
            $programming['test_cases'] = [];
            $programmingByQuestion[(int) $programming['question_id']] = $programming;
        }

        if ($programmingByQuestion !== []) {
            $testCases = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->programmingTestCasesTable()} WHERE question_id IN ({$qPlaceholders}) ORDER BY sort_order ASC",
                    $questionIds
                ),
                ARRAY_A
            ) ?: [];
            foreach ($testCases as $testCase) {
                $programmingByQuestion[(int) $testCase['question_id']]['test_cases'][] = $testCase;
            }

            foreach ($programmingByQuestion as $questionId => $programming) {
                $questions[$questionId]['programming'] = $programming;
            }
        }

        $dsaRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->dsaTable()} WHERE question_id IN ({$qPlaceholders})",
                $questionIds
            ),
            ARRAY_A
        ) ?: [];
        foreach ($dsaRows as $dsa) {
            $dsa['operations'] = json_decode((string) $dsa['operations_json'], true) ?: [];
            $questions[(int) $dsa['question_id']]['dsa'] = $dsa;
        }

        return $questions;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        return $wpdb->update($this->table(), $data, ['id' => $id]) !== false;
    }

    /**
     * Replaces the full option list for a question — the MCQ/True-False
     * counterpart to insert()'s $options handling, for editing an existing
     * question rather than creating one.
     *
     * @param array<int, array{label:string, is_correct?:bool, sort_order?:int}> $options
     */
    public function replaceOptions(int $questionId, array $options): void
    {
        global $wpdb;

        $wpdb->delete($this->optionsTable(), ['question_id' => $questionId]);

        foreach ($options as $option) {
            $wpdb->insert($this->optionsTable(), [
                'question_id' => $questionId,
                'label' => $option['label'],
                'is_correct' => !empty($option['is_correct']) ? 1 : 0,
                'sort_order' => $option['sort_order'] ?? 0,
            ]);
        }
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        $wpdb->delete($this->optionsTable(), ['question_id' => $id]);
        $wpdb->delete($this->programmingTable(), ['question_id' => $id]);
        $wpdb->delete($this->programmingTestCasesTable(), ['question_id' => $id]);
        $wpdb->delete($this->dsaTable(), ['question_id' => $id]);
        return $wpdb->delete($this->table(), ['id' => $id]) !== false;
    }

    /**
     * @param array{institution_id?:int, search?:string, status?:string, per_page?:int, page?:int} $args
     * @return array<int, array<string, mixed>>
     */
    public function paginate(array $args): array
    {
        global $wpdb;

        $perPage = max(1, (int) ($args['per_page'] ?? 20));
        $page = max(1, (int) ($args['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildWhere($args);
        $sql = "SELECT * FROM {$this->table()} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $perPage;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }

    public function count(array $args): int
    {
        global $wpdb;
        [$where, $params] = $this->buildWhere($args);
        $sql = "SELECT COUNT(*) FROM {$this->table()} {$where}";
        return $params === [] ? (int) $wpdb->get_var($sql) : (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /** @return array<int, array<string, mixed>> all active questions for an institution, for pickers */
    public function allActiveForInstitution(int $institutionId): array
    {
        return $this->paginate(['institution_id' => $institutionId, 'status' => 'active', 'per_page' => 500]);
    }

    /** @return array{0:string, 1:array<int, mixed>} */
    private function buildWhere(array $args): array
    {
        global $wpdb;

        $clauses = [];
        $params = [];

        if (!empty($args['institution_id'])) {
            $clauses[] = 'institution_id = %d';
            $params[] = (int) $args['institution_id'];
        }

        if (!empty($args['status'])) {
            $clauses[] = 'status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $clauses[] = '(content LIKE %s OR subject LIKE %s OR topic LIKE %s)';
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            array_push($params, $like, $like, $like);
        }

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }
}
