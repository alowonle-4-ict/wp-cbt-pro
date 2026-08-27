<?php

declare(strict_types=1);

namespace WPCBTPro\Programming;

use WPCBTPro\Attempts\AnswerRepository;
use WPCBTPro\Attempts\AttemptOwnershipGuard;
use WPCBTPro\REST\RestController;
use WPCBTPro\REST\RestServiceProvider;

/**
 * The read-only half of §18's programming endpoints — lets the candidate's
 * result page ask "has my code been graded yet" without a full page
 * reload. Never returns hidden test cases, stdout/stderr, or grading
 * internals — only a status a candidate is allowed to see.
 */
final class CodeSubmissionRestController implements RestController
{
    public function __construct(
        private readonly AttemptOwnershipGuard $guard,
        private readonly AnswerRepository $answers,
        private readonly CodeSubmissionRepository $submissions,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(RestServiceProvider::NAMESPACE_V1, '/code-submission-status', [
            'methods' => 'GET',
            'callback' => [$this, 'getStatus'],
            'permission_callback' => static fn (): bool => is_user_logged_in(),
            'args' => [
                'attempt_id' => ['required' => true, 'type' => 'integer'],
                'question_id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }

    public function getStatus(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [, $attempt, $error] = $this->guard->resolve((int) $request->get_param('attempt_id'));
        if ($error !== null) {
            return $error;
        }

        $questionId = (int) $request->get_param('question_id');
        $answer = $this->answers->find((int) $attempt['id'], $questionId);
        if ($answer === null) {
            return new \WP_Error('wpcbtpro_not_found', __('No submission found for this question.', 'wp-cbt-pro'), ['status' => 404]);
        }

        $submission = $this->submissions->findByAnswer((int) $answer['id']);
        if ($submission === null) {
            return new \WP_REST_Response(['status' => 'not_submitted']);
        }

        return new \WP_REST_Response([
            'status' => $submission['status'],
            'has_compile_error' => !empty($submission['compile_error']) && $submission['status'] === 'completed',
        ]);
    }
}
