<?php

declare(strict_types=1);

namespace WPCBTPro\Programming;

use WPCBTPro\Attempts\AnswerRepository;
use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Programming\Contracts\ExecutionClient;
use WPCBTPro\Programming\Contracts\ExecutionClientException;
use WPCBTPro\Programming\Contracts\ExecutionJob;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Security\AuditLogger;

/**
 * The WP-Cron worker that actually crosses the boundary into the execution
 * service (§16, Fig. 6). Runs periodically, off the request path a
 * candidate ever touches — nothing here is reachable from a REST endpoint.
 */
final class CodeGradingService
{
    public function __construct(
        private readonly CodeSubmissionRepository $submissions,
        private readonly CodeExecutionResultRepository $results,
        private readonly AnswerRepository $answers,
        private readonly QuestionRepository $questions,
        private readonly ExecutionClient $client,
        private readonly AttemptService $attemptService,
        private readonly AttemptRepository $attempts,
        private readonly ExamRepository $exams,
    ) {
    }

    public function processPending(int $batchSize = 10): void
    {
        foreach ($this->submissions->findPending($batchSize) as $submission) {
            $this->processOne($submission);
        }
    }

    private function processOne(array $submission): void
    {
        $submissionId = (int) $submission['id'];

        $answer = $this->answers->findById((int) $submission['answer_id']);
        if ($answer === null) {
            $this->submissions->markFailed($submissionId, 'Answer record no longer exists.');
            return;
        }

        $question = $this->questions->find((int) $answer['question_id']);
        if ($question === null || empty($question['programming'])) {
            $this->submissions->markFailed($submissionId, 'Question configuration not found.');
            return;
        }

        $testCases = array_map(static fn (array $tc): array => [
            'id' => (int) $tc['id'],
            'input' => $tc['input'],
            'expected_output' => $tc['expected_output'],
        ], $question['programming']['test_cases']);

        if ($testCases === []) {
            $this->submissions->markFailed($submissionId, 'This question has no test cases configured.');
            return;
        }

        $job = new ExecutionJob(
            $submissionId,
            $submission['language'],
            $submission['source'],
            $question['programming']['entry_point'] ?: null,
            (int) $question['programming']['time_limit_ms'],
            (int) $question['programming']['memory_limit_mb'],
            $testCases
        );

        try {
            $report = $this->client->execute($job);
        } catch (ExecutionClientException $e) {
            $this->submissions->markFailed($submissionId, $e->getMessage());
            AuditLogger::record('code_submission.execution_failed', 'code_submission', $submissionId, [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $this->results->replaceForSubmission($submissionId, $report->testCaseResults);
        $this->submissions->markCompleted($submissionId, $report->compileError);

        $this->regradeParentAttempt((int) $answer['attempt_id']);
    }

    private function regradeParentAttempt(int $attemptId): void
    {
        $attempt = $this->attempts->find($attemptId);
        if ($attempt === null || $attempt['status'] !== 'submitted') {
            return; // not finalized yet — the normal submitAttempt() grading pass will pick this up
        }

        $exam = $this->exams->find((int) $attempt['exam_id']);
        if ($exam === null) {
            return;
        }

        $this->attemptService->regradeAttempt($exam, $attempt);
    }
}
