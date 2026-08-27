<?php

declare(strict_types=1);

namespace WPCBTPro\Attempts;

use WPCBTPro\Candidates\CurrentCandidateResolver;
use WPCBTPro\REST\RestController;
use WPCBTPro\REST\RestServiceProvider;
use WPCBTPro\Results\ResultRepository;

/**
 * REST surface for the candidate exam runtime (§18). Cookie-authenticated
 * requests are nonce-verified by WordPress core itself before reaching any
 * callback here; ownership of the attempt being acted on is re-checked in
 * every handler via AttemptOwnershipGuard regardless, since a valid nonce
 * only proves "this is really that browser session," not "this attempt
 * belongs to that candidate."
 */
final class AttemptsRestController implements RestController
{
    public function __construct(
        private readonly AttemptService $attemptService,
        private readonly ResultRepository $resultRepository,
        private readonly CurrentCandidateResolver $candidateResolver,
        private readonly AttemptOwnershipGuard $guard,
    ) {
    }

    public function registerRoutes(): void
    {
        $ns = RestServiceProvider::NAMESPACE_V1;

        register_rest_route($ns, '/start-exam', [
            'methods' => 'POST',
            'callback' => [$this, 'startExam'],
            'permission_callback' => [$this, 'requireCandidate'],
            'args' => ['exam_id' => ['required' => true, 'type' => 'integer']],
        ]);

        register_rest_route($ns, '/answer', [
            'methods' => 'POST',
            'callback' => [$this, 'saveAnswer'],
            'permission_callback' => [$this, 'requireCandidate'],
            'args' => [
                'attempt_id' => ['required' => true, 'type' => 'integer'],
                'question_id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route($ns, '/submit-exam', [
            'methods' => 'POST',
            'callback' => [$this, 'submitExam'],
            'permission_callback' => [$this, 'requireCandidate'],
            'args' => ['attempt_id' => ['required' => true, 'type' => 'integer']],
        ]);

        register_rest_route($ns, '/result', [
            'methods' => 'GET',
            'callback' => [$this, 'getResult'],
            'permission_callback' => [$this, 'requireCandidate'],
            'args' => ['attempt_id' => ['required' => true, 'type' => 'integer']],
        ]);
    }

    public function requireCandidate(): bool
    {
        return $this->candidateResolver->resolve() !== null;
    }

    public function startExam(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $candidate = $this->candidateResolver->resolve();
        $examId = (int) $request->get_param('exam_id');

        try {
            $attempt = $this->attemptService->startAttempt($examId, (int) $candidate['id']);
        } catch (\RuntimeException $e) {
            return new \WP_Error('wpcbtpro_start_failed', $e->getMessage(), ['status' => 400]);
        }

        return new \WP_REST_Response([
            'attempt_id' => (int) $attempt['id'],
            'server_now' => current_time('timestamp'),
            'server_end' => strtotime($attempt['server_end']),
        ], 201);
    }

    public function saveAnswer(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$exam, $attempt, $error] = $this->guard->resolve((int) $request->get_param('attempt_id'));
        if ($error !== null) {
            return $error;
        }

        $questionId = (int) $request->get_param('question_id');
        $value = $request->get_param('value');
        $marked = (bool) $request->get_param('marked_for_review');

        $result = $this->attemptService->saveAnswer($exam, $attempt, $questionId, $value, $marked);

        if (!$result['ok']) {
            return new \WP_Error('wpcbtpro_answer_rejected', implode(' ', $result['errors']), ['status' => 422]);
        }

        return new \WP_REST_Response([
            'saved' => true,
            'server_now' => current_time('timestamp'),
            'server_end' => strtotime($attempt['server_end']),
        ]);
    }

    public function submitExam(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$exam, $attempt, $error] = $this->guard->resolve((int) $request->get_param('attempt_id'));
        if ($error !== null) {
            return $error;
        }

        $result = $this->attemptService->submitAttempt($exam, $attempt);

        return new \WP_REST_Response([
            'submitted' => true,
            'result_released' => !empty($result['released_at']),
        ]);
    }

    public function getResult(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [, $attempt, $error] = $this->guard->resolve((int) $request->get_param('attempt_id'));
        if ($error !== null) {
            return $error;
        }

        $result = $this->resultRepository->findByAttempt((int) $attempt['id']);
        if ($result === null || empty($result['released_at'])) {
            return new \WP_Error(
                'wpcbtpro_result_not_ready',
                __('Your result has not been released yet.', 'wp-cbt-pro'),
                ['status' => 403]
            );
        }

        return new \WP_REST_Response([
            'status' => $result['status'] ?? 'final',
            'score' => (float) $result['score'],
            'percentage' => (float) $result['percentage'],
            'grade' => $result['grade'],
            'pass_status' => $result['pass_status'],
            'correct_count' => (int) $result['correct_count'],
            'incorrect_count' => (int) $result['incorrect_count'],
            'unanswered_count' => (int) $result['unanswered_count'],
            'pending_review_count' => (int) ($result['pending_review_count'] ?? 0),
        ]);
    }
}
