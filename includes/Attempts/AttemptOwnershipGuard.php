<?php

declare(strict_types=1);

namespace WPCBTPro\Attempts;

use WPCBTPro\Candidates\CurrentCandidateResolver;
use WPCBTPro\Exams\ExamRepository;

/**
 * A valid REST nonce only proves "this is really that browser session," not
 * "this attempt belongs to that candidate" — every attempt-scoped REST
 * controller (answers, camera, verification) re-checks ownership through
 * this one gate rather than re-implementing the check per endpoint.
 */
final class AttemptOwnershipGuard
{
    public function __construct(
        private readonly AttemptRepository $attempts,
        private readonly ExamRepository $exams,
        private readonly CurrentCandidateResolver $candidateResolver,
    ) {
    }

    /** @return array{0: array<string,mixed>|null, 1: array<string,mixed>|null, 2: \WP_Error|null} [exam, attempt, error] */
    public function resolve(int $attemptId): array
    {
        $candidate = $this->candidateResolver->resolve();
        $attempt = $this->attempts->find($attemptId);

        if ($attempt === null || $candidate === null || (int) $attempt['candidate_id'] !== (int) $candidate['id']) {
            return [null, null, new \WP_Error(
                'wpcbtpro_forbidden',
                __('This attempt does not belong to you.', 'wp-cbt-pro'),
                ['status' => 403]
            )];
        }

        $exam = $this->exams->find((int) $attempt['exam_id']);
        if ($exam === null) {
            return [null, null, new \WP_Error('wpcbtpro_not_found', __('Exam not found.', 'wp-cbt-pro'), ['status' => 404])];
        }

        return [$exam, $attempt, null];
    }
}
