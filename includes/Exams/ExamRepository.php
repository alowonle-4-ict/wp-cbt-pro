<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

final class ExamRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_exams';
    }

    private function examQuestionsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_exam_questions';
    }

    private function poolsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_exam_pools';
    }

    /** @var array<int, array<string, mixed>> request-lifetime memo, since one dashboard render can ask for the same exam's question set once per candidate sharing it */
    private array $questionsForExamCache = [];

    /** @var array<int, array<string, array<string, mixed>>> */
    private array $poolsForExamCache = [];

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>> keyed by exam id
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

        $exams = [];
        foreach ($rows as $row) {
            $exams[(int) $row['id']] = $row;
        }
        return $exams;
    }

    public function insert(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $wpdb->insert($this->table(), $data);
        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        return $wpdb->update($this->table(), $data, ['id' => $id]) !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        $wpdb->delete($this->examQuestionsTable(), ['exam_id' => $id]);
        $wpdb->delete($this->poolsTable(), ['exam_id' => $id]);
        return $wpdb->delete($this->table(), ['id' => $id]) !== false;
    }

    /**
     * @param array{institution_id?:int, search?:string, per_page?:int, page?:int} $args
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

    /** @return array<int, array<string, mixed>> rows: id, question_id, pool_id, sort_order */
    public function questionsForExam(int $examId): array
    {
        if (isset($this->questionsForExamCache[$examId])) {
            return $this->questionsForExamCache[$examId];
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->examQuestionsTable()} WHERE exam_id = %d ORDER BY sort_order ASC", $examId),
            ARRAY_A
        ) ?: [];

        return $this->questionsForExamCache[$examId] = $rows;
    }

    /**
     * Replaces the full question assignment list for an exam.
     *
     * @param array<int, array{question_id:int, pool_id?:?string, sort_order?:int}> $assignments
     */
    public function setQuestions(int $examId, array $assignments): void
    {
        global $wpdb;

        unset($this->questionsForExamCache[$examId]);
        $wpdb->delete($this->examQuestionsTable(), ['exam_id' => $examId]);

        foreach ($assignments as $assignment) {
            $wpdb->insert($this->examQuestionsTable(), [
                'exam_id' => $examId,
                'question_id' => (int) $assignment['question_id'],
                'pool_id' => $assignment['pool_id'] !== '' ? ($assignment['pool_id'] ?? null) : null,
                'sort_order' => $assignment['sort_order'] ?? 0,
            ]);
        }
    }

    /** @return array<string, array<string, mixed>> pool_key => row */
    public function poolsForExam(int $examId): array
    {
        if (isset($this->poolsForExamCache[$examId])) {
            return $this->poolsForExamCache[$examId];
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->poolsTable()} WHERE exam_id = %d", $examId),
            ARRAY_A
        ) ?: [];

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['pool_key']] = $row;
        }
        return $this->poolsForExamCache[$examId] = $byKey;
    }

    /**
     * Replaces the full pool definition list for an exam.
     *
     * @param array<int, array{pool_key:string, name:string, draw_count:int}> $pools
     */
    public function setPools(int $examId, array $pools): void
    {
        global $wpdb;

        unset($this->poolsForExamCache[$examId]);
        $wpdb->delete($this->poolsTable(), ['exam_id' => $examId]);

        foreach ($pools as $pool) {
            $wpdb->insert($this->poolsTable(), [
                'exam_id' => $examId,
                'pool_key' => $pool['pool_key'],
                'name' => $pool['name'],
                'draw_count' => (int) $pool['draw_count'],
            ]);
        }
    }

    /** Exams whose results are configured to release once the exam window closes, and that window has now passed. */
    public function findDelayedReleaseDue(): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE result_visibility = 'delayed' AND end_at IS NOT NULL AND end_at <= %s",
            current_time('mysql')
        ), ARRAY_A) ?: [];
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

        if (!empty($args['search'])) {
            $clauses[] = '(name LIKE %s OR subject LIKE %s)';
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            array_push($params, $like, $like);
        }

        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $params];
    }
}
