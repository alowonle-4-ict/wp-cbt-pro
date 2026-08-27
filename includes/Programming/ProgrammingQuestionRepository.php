<?php

declare(strict_types=1);

namespace WPCBTPro\Programming;

/**
 * The extension tables behind a 'programming' question (§16.1, §35).
 * question_id is the primary key here, not an independent id — a
 * programming question's type-specific row lives one-to-one with its
 * wp_cbt_questions row.
 */
final class ProgrammingQuestionRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_programming_questions';
    }

    private function testCasesTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_programming_test_cases';
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

        $row['test_cases'] = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->testCasesTable()} WHERE question_id = %d ORDER BY sort_order ASC", $questionId),
            ARRAY_A
        ) ?: [];

        return $row;
    }

    /** @param array{language:string, starter_code?:string, entry_point?:string, time_limit_ms:int, memory_limit_mb:int} $data */
    public function upsert(int $questionId, array $data): void
    {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table()} WHERE question_id = %d",
            $questionId
        ));

        $data['question_id'] = $questionId;

        if ((int) $exists > 0) {
            $wpdb->update($this->table(), $data, ['question_id' => $questionId]);
            return;
        }

        $wpdb->insert($this->table(), $data);
    }

    /** @param array<int, array{input:?string, expected_output:?string, weight:float, is_hidden:bool}> $testCases */
    public function replaceTestCases(int $questionId, array $testCases): void
    {
        global $wpdb;

        $wpdb->delete($this->testCasesTable(), ['question_id' => $questionId]);

        foreach ($testCases as $index => $testCase) {
            $wpdb->insert($this->testCasesTable(), [
                'question_id' => $questionId,
                'input' => $testCase['input'],
                'expected_output' => $testCase['expected_output'],
                'weight' => $testCase['weight'],
                'is_hidden' => !empty($testCase['is_hidden']) ? 1 : 0,
                'sort_order' => $index,
            ]);
        }
    }
}
