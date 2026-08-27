<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Programming;

use WPCBTPro\Programming\CodeExecutionResultRepository;
use WPCBTPro\Programming\CodeSubmissionRepository;
use WPCBTPro\Questions\Contracts\Score;
use WPCBTPro\Questions\Contracts\ScoringStrategy;

/**
 * Resolves a programming answer's score from whatever the execution
 * service has reported so far (§16.2, §24, §34) — never by running
 * anything itself. Until a code_submissions row reaches status
 * 'completed', this returns Score::pendingManualReview(), which is what
 * tells AttemptService the question isn't resolved yet, not "wrong."
 */
final class ProgrammingScoringStrategy implements ScoringStrategy
{
    public function __construct(
        private readonly CodeSubmissionRepository $submissions,
        private readonly CodeExecutionResultRepository $executionResults,
    ) {
    }

    public function score(array $question, array $answerRow): Score
    {
        $marks = (float) ($question['marks'] ?? 0);
        $submission = $this->submissions->findByAnswer((int) $answerRow['id']);
        $status = $submission['status'] ?? 'pending';

        if ($submission === null || $status === 'pending') {
            return Score::pendingManualReview($marks);
        }

        if ($status === 'failed') {
            // A transport/config failure grading this submission — not the
            // candidate's fault. Held pending rather than scored as wrong,
            // pending an admin re-run (not yet built as a UI action).
            return Score::pendingManualReview($marks);
        }

        if (!empty($submission['compile_error'])) {
            return new Score(0.0, $marks, false, ['compile_error' => $submission['compile_error']]);
        }

        $testCases = $question['programming']['test_cases'] ?? [];
        if ($testCases === []) {
            return Score::pendingManualReview($marks);
        }

        $results = $this->executionResults->allForSubmission((int) $submission['id']);
        $resultsByTestCase = [];
        foreach ($results as $result) {
            $resultsByTestCase[(int) $result['test_case_id']] = $result;
        }

        $totalWeight = 0.0;
        $earnedWeight = 0.0;
        $passedCount = 0;

        foreach ($testCases as $testCase) {
            $weight = (float) ($testCase['weight'] ?? 1);
            $totalWeight += $weight;

            $result = $resultsByTestCase[(int) $testCase['id']] ?? null;
            if ($result !== null && !empty($result['passed'])) {
                $earnedWeight += $weight;
                $passedCount++;
            }
        }

        $fraction = $totalWeight > 0 ? $earnedWeight / $totalWeight : 0.0;
        $earned = round($fraction * $marks, 2);
        $allPassed = $passedCount === count($testCases);

        return new Score($earned, $marks, $allPassed, [
            'tests_passed' => $passedCount,
            'tests_total' => count($testCases),
        ]);
    }
}
