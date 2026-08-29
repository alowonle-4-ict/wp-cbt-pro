<?php

declare(strict_types=1);

namespace WPCBTPro\Attempts;

use WPCBTPro\Exams\ExamQuestionResolver;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Exams\RandomizationService;
use WPCBTPro\Questions\Contracts\Score;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Questions\Registry\QuestionTypeRegistry;
use WPCBTPro\Results\ResultRepository;
use WPCBTPro\Security\AuditLogger;

/**
 * Owns the one invariant the whole exam runtime depends on: the server is
 * the only party that ever decides how much time is left, whether an answer
 * is correct, or what an attempt is worth (§9, §19, §34). The candidate's
 * browser only ever displays what this service already decided.
 */
final class AttemptService
{
    public function __construct(
        private readonly AttemptRepository $attempts,
        private readonly AnswerRepository $answers,
        private readonly \WPCBTPro\Results\ResultRepository $results,
        private readonly ExamRepository $examRepository,
        private readonly ExamQuestionResolver $resolver,
        private readonly RandomizationService $randomizer,
        private readonly QuestionRepository $questionRepository,
        private readonly QuestionTypeRegistry $registry,
        private readonly CandidateExamOverrideRepository $overrides,
    ) {
    }

    /**
     * @throws \RuntimeException on any rule violation — callers (REST, page controller) translate the message
     */
    public function startAttempt(int $examId, int $candidateId): array
    {
        $exam = $this->examRepository->find($examId);
        if ($exam === null || $exam['status'] !== 'active') {
            throw new \RuntimeException(__('This exam is not currently available.', 'wp-cbt-pro'));
        }

        $now = current_time('timestamp');
        if (!empty($exam['start_at']) && $now < strtotime($exam['start_at'])) {
            throw new \RuntimeException(__('This exam has not started yet.', 'wp-cbt-pro'));
        }
        if (!empty($exam['end_at']) && $now > strtotime($exam['end_at'])) {
            throw new \RuntimeException(__('This exam is no longer available.', 'wp-cbt-pro'));
        }

        $existing = $this->attempts->findActive($examId, $candidateId);
        if ($existing !== null) {
            return $existing;
        }

        $override = $this->overrides->find($examId, $candidateId);
        $attemptLimit = (int) $exam['attempt_limit'] + $override['extra_attempts'];
        if ($this->attempts->countForCandidateExam($examId, $candidateId) >= $attemptLimit) {
            throw new \RuntimeException(__('You have used all of your attempts for this exam.', 'wp-cbt-pro'));
        }

        $seed = $this->randomizer->generateSeed();
        $serverStart = current_time('mysql');
        $durationMinutes = (int) $exam['duration_minutes'] + $override['extra_minutes'];
        $serverEnd = gmdate('Y-m-d H:i:s', $now + ($durationMinutes * MINUTE_IN_SECONDS));

        $id = $this->attempts->insert([
            'exam_id' => $examId,
            'candidate_id' => $candidateId,
            'seed' => $seed,
            'server_start' => $serverStart,
            'server_end' => $serverEnd,
            'status' => 'in_progress',
        ]);

        AuditLogger::record('attempt.started', 'attempt', $id, ['exam_id' => $examId, 'candidate_id' => $candidateId]);

        return $this->attempts->find($id);
    }

    /** @return int[] the ordered question ids this attempt's candidate will see */
    public function resolvedQuestionIds(array $exam, array $attempt): array
    {
        return $this->resolver->resolve($exam, $attempt['seed']);
    }

    /** @return array<string, mixed>|null the question at $index in this attempt's resolved order, options reordered if configured */
    public function questionAt(array $exam, array $attempt, int $index): ?array
    {
        $ids = $this->resolvedQuestionIds($exam, $attempt);
        if (!isset($ids[$index])) {
            return null;
        }

        $question = $this->questionRepository->find($ids[$index]);
        if ($question === null) {
            return null;
        }

        if (!empty($exam['randomize_options']) && !empty($question['options'])) {
            $question['options'] = $this->resolver->resolveOptionOrder(
                $question['options'],
                $attempt['seed'],
                (int) $question['id'],
                true
            );
        }

        // Hidden test cases must never reach the candidate (§18) — this is
        // the one chokepoint every candidate-facing render path goes
        // through, so the filter lives here rather than in each view.
        if (!empty($question['programming']['test_cases'])) {
            $question['programming']['test_cases'] = array_values(array_filter(
                $question['programming']['test_cases'],
                static fn (array $tc): bool => empty($tc['is_hidden'])
            ));
        }

        return $question;
    }

    public function isExpired(array $attempt): bool
    {
        return current_time('timestamp') >= strtotime($attempt['server_end']);
    }

    /** Applied when the camera disconnect policy is "pause" (§9, §11). Time keeps running — pausing halts writes, not the clock. */
    public function pauseAttempt(array $attempt): void
    {
        if ($attempt['status'] === 'in_progress') {
            $this->attempts->update((int) $attempt['id'], ['status' => 'paused']);
            AuditLogger::record('attempt.paused', 'attempt', (int) $attempt['id']);
        }
    }

    public function resumeAttemptIfPaused(array $attempt): void
    {
        if ($attempt['status'] === 'paused') {
            $this->attempts->update((int) $attempt['id'], ['status' => 'in_progress']);
            AuditLogger::record('attempt.resumed', 'attempt', (int) $attempt['id']);
        }
    }

    /** @return array{ok: bool, errors: string[]} */
    public function saveAnswer(array $exam, array $attempt, int $questionId, mixed $rawAnswer, bool $markedForReview): array
    {
        if ($attempt['status'] !== 'in_progress') {
            return ['ok' => false, 'errors' => [__('This attempt is no longer active.', 'wp-cbt-pro')]];
        }

        if ($this->isExpired($attempt)) {
            $this->submitAttempt($exam, $attempt);
            return ['ok' => false, 'errors' => [__('Time has expired. Your exam has been submitted.', 'wp-cbt-pro')]];
        }

        $resolvedIds = $this->resolvedQuestionIds($exam, $attempt);
        if (!in_array($questionId, $resolvedIds, true)) {
            return ['ok' => false, 'errors' => [__('This question does not belong to your exam paper.', 'wp-cbt-pro')]];
        }

        $question = $this->questionRepository->find($questionId);
        if ($question === null || !$this->registry->has($question['type'])) {
            return ['ok' => false, 'errors' => [__('Unable to process this question.', 'wp-cbt-pro')]];
        }

        $type = $this->registry->get($question['type']);

        $validationErrors = $type->validator()->validate($question, $rawAnswer);
        if ($validationErrors !== []) {
            return ['ok' => false, 'errors' => $validationErrors];
        }

        $processed = $type->answerProcessor()->process($question, $rawAnswer);
        $this->answers->upsert((int) $attempt['id'], $questionId, $processed, $markedForReview);

        return ['ok' => true, 'errors' => []];
    }

    /** Finalizes an attempt: locks further writes, grades it, and stores the result. Idempotent. */
    public function submitAttempt(array $exam, array $attempt): array
    {
        $attemptId = (int) $attempt['id'];

        // 'paused' is submittable too (the deadline is still absolute while
        // paused — see pauseAttempt()'s docblock) — otherwise an attempt
        // that ran out of time while paused would sit forever with no
        // result row, since nothing else ever calls submitAttempt() on it.
        if (!in_array($attempt['status'], ['in_progress', 'paused'], true)) {
            return $this->results->findByAttempt($attemptId) ?? [];
        }

        $now = current_time('timestamp');
        $serverEndTs = strtotime($attempt['server_end']);
        $effectiveEnd = min($now, $serverEndTs);
        $timeUsed = max(0, $effectiveEnd - strtotime($attempt['server_start']));

        $this->storeGradingResult($exam, $attempt, $timeUsed, runFinalizeHooks: true);

        $this->attempts->update($attemptId, [
            'status' => 'submitted',
            'submitted_at' => current_time('mysql'),
        ]);

        AuditLogger::record('attempt.submitted', 'attempt', $attemptId);

        return $this->results->findByAttempt($attemptId) ?? [];
    }

    /**
     * Re-runs grading for an already-submitted attempt without re-finalizing
     * it — the entry point the execution service's completion callback uses
     * (§16, Phase 11) once a pending programming answer's Score stops being
     * null. Never touches attempt status/submitted_at; only the result row.
     */
    public function regradeAttempt(array $exam, array $attempt): array
    {
        if ($attempt['status'] !== 'submitted') {
            throw new \RuntimeException('Only a submitted attempt can be regraded.');
        }

        $existing = $this->results->findByAttempt((int) $attempt['id']);
        $timeUsed = (int) ($existing['time_used_seconds'] ?? 0);

        $this->storeGradingResult($exam, $attempt, $timeUsed, runFinalizeHooks: false);

        return $this->results->findByAttempt((int) $attempt['id']) ?? [];
    }

    private function storeGradingResult(array $exam, array $attempt, int $timeUsed, bool $runFinalizeHooks): void
    {
        $attemptId = (int) $attempt['id'];
        $existingReleasedAt = $this->results->findByAttempt($attemptId)['released_at'] ?? null;
        $resolvedIds = $this->resolvedQuestionIds($exam, $attempt);
        $storedAnswers = $this->answers->allForAttempt($attemptId);
        $questions = $this->questionRepository->findMany($resolvedIds);

        $earnedTotal = 0.0;
        $maxTotal = 0.0;
        $correct = 0;
        $incorrect = 0;
        $unanswered = 0;
        $pendingReview = 0;

        foreach ($resolvedIds as $questionId) {
            $question = $questions[$questionId] ?? null;
            if ($question === null || !$this->registry->has($question['type'])) {
                continue;
            }

            if (empty($exam['negative_marking'])) {
                $question['negative_marks'] = 0;
            }

            $answerRow = $storedAnswers[$questionId] ?? null;
            $storedAnswer = (string) ($answerRow['value'] ?? '');
            $type = $this->registry->get($question['type']);

            if ($runFinalizeHooks) {
                $type->onFinalize($question, $answerRow);
            }

            $maxTotal += (float) $question['marks'];

            if ($answerRow === null || $storedAnswer === '') {
                $unanswered++;
                continue;
            }

            $score = $type->scoringStrategy()->score($question, $answerRow);
            $earnedTotal += $score->earned;

            if ($score->isCorrect === true) {
                $correct++;
            } elseif ($score->isCorrect === false) {
                $incorrect++;
            } else {
                // isCorrect === null with a non-empty answer means grading
                // hasn't resolved yet (e.g. the execution service hasn't
                // reported back) — distinct from "unanswered".
                $pendingReview++;
            }
        }

        $clampedEarned = max(0.0, $earnedTotal);
        $percentage = $maxTotal > 0 ? round(($clampedEarned / $maxTotal) * 100, 2) : 0.0;
        $passMark = $exam['pass_mark'] ?? null;
        $status = $pendingReview > 0 ? 'provisional' : 'final';

        $this->results->upsert($attemptId, [
            'score' => $clampedEarned,
            'percentage' => $percentage,
            'grade' => $status === 'final' ? $this->letterGrade($percentage) : null,
            'pass_status' => $status === 'final' && $passMark !== null ? ($percentage >= (float) $passMark ? 'pass' : 'fail') : null,
            'correct_count' => $correct,
            'incorrect_count' => $incorrect,
            'unanswered_count' => $unanswered,
            'pending_review_count' => $pendingReview,
            'status' => $status,
            'time_used_seconds' => $timeUsed,
            'released_at' => $existingReleasedAt ?? ($exam['result_visibility'] === 'immediate' ? current_time('mysql') : null),
        ]);
    }

    public function expireOverdueAttempts(): void
    {
        foreach ($this->attempts->findExpiredInProgress() as $attempt) {
            $exam = $this->examRepository->find((int) $attempt['exam_id']);
            if ($exam === null) {
                continue;
            }

            try {
                $this->submitAttempt($exam, $attempt);
            } catch (\Throwable) {
                // One bad attempt must never block the rest of the sweep.
                continue;
            }
        }
    }

    private function letterGrade(float $percentage): string
    {
        return match (true) {
            $percentage >= 80 => 'A',
            $percentage >= 70 => 'B',
            $percentage >= 60 => 'C',
            $percentage >= 50 => 'D',
            default => 'F',
        };
    }
}
