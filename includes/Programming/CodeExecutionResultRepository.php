<?php

declare(strict_types=1);

namespace WPCBTPro\Programming;

/**
 * Per-test-case verdicts from the execution service (§16.2, §24). Never
 * candidate-writable — the only writer is the grading pipeline that talks
 * to the sandbox.
 */
final class CodeExecutionResultRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'cbt_code_execution_results';
    }

    /** @param array<int, \WPCBTPro\Programming\Contracts\TestCaseResult> $results */
    public function replaceForSubmission(int $submissionId, array $results): void
    {
        global $wpdb;

        $wpdb->delete($this->table(), ['submission_id' => $submissionId]);

        foreach ($results as $result) {
            $wpdb->insert($this->table(), [
                'submission_id' => $submissionId,
                'test_case_id' => $result->testCaseId,
                'passed' => $result->passed ? 1 : 0,
                'stdout' => $result->stdout,
                'stderr' => $result->stderr,
                'exit_code' => $result->exitCode,
                'runtime_ms' => $result->runtimeMs,
                'memory_kb' => $result->memoryKb,
                'verdict' => $result->verdict,
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function allForSubmission(int $submissionId): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE submission_id = %d", $submissionId),
            ARRAY_A
        ) ?: [];
    }
}
