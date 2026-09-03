<?php

declare(strict_types=1);

namespace WPCBTPro\Camera;

use WPCBTPro\Attempts\AttemptOwnershipGuard;
use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Camera\Contracts\CameraEventType;
use WPCBTPro\Camera\Contracts\CameraVerificationService;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Candidates\CurrentCandidateResolver;
use WPCBTPro\Monitoring\AutoMonitoringService;
use WPCBTPro\Monitoring\Contracts\MonitoringViolationType;
use WPCBTPro\REST\RestController;
use WPCBTPro\REST\RestServiceProvider;

final class CameraRestController implements RestController
{
    public function __construct(
        private readonly CameraVerificationService $cameraService,
        private readonly AttemptService $attemptService,
        private readonly AttemptOwnershipGuard $guard,
        private readonly CurrentCandidateResolver $candidateResolver,
        private readonly CandidateRepository $candidateRepository,
        private readonly AutoMonitoringService $autoMonitoring,
    ) {
    }

    public function registerRoutes(): void
    {
        $ns = RestServiceProvider::NAMESPACE_V1;

        register_rest_route($ns, '/camera-event', [
            'methods' => 'POST',
            'callback' => [$this, 'handleEvent'],
            'permission_callback' => [$this, 'requireCandidate'],
            'args' => ['attempt_id' => ['required' => true, 'type' => 'integer']],
        ]);

        register_rest_route($ns, '/snapshot', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSnapshot'],
            'permission_callback' => [$this, 'requireCandidate'],
            'args' => ['attempt_id' => ['required' => true, 'type' => 'integer']],
        ]);

        register_rest_route($ns, '/verification', [
            'methods' => 'POST',
            'callback' => [$this, 'handleVerification'],
            'permission_callback' => [$this, 'requireCandidate'],
            'args' => ['attempt_id' => ['required' => true, 'type' => 'integer']],
        ]);

        register_rest_route($ns, '/monitoring-violation', [
            'methods' => 'POST',
            'callback' => [$this, 'handleMonitoringViolation'],
            'permission_callback' => [$this, 'requireCandidate'],
            'args' => ['attempt_id' => ['required' => true, 'type' => 'integer']],
        ]);
    }

    public function requireCandidate(): bool
    {
        return $this->candidateResolver->resolve() !== null;
    }

    public function handleEvent(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$exam, $attempt, $error] = $this->guard->resolve((int) $request->get_param('attempt_id'));
        if ($error !== null) {
            return $error;
        }

        $type = CameraEventType::tryFrom((string) $request->get_param('event_type'));
        if ($type === null) {
            return new \WP_Error('wpcbtpro_invalid_event', __('Unknown camera event type.', 'wp-cbt-pro'), ['status' => 400]);
        }

        $context = (array) $request->get_param('context');
        $session = $this->cameraService->startSession((int) $attempt['id']);
        $session = $this->cameraService->recordEvent($session, $type, $context);

        $monitoring = $this->applyDisconnectPolicy($type, $exam, $attempt);

        return new \WP_REST_Response(array_merge(
            ['recorded' => true, 'session_state' => $session->state->value],
            $monitoring !== null ? ['monitoring' => $monitoring] : []
        ));
    }

    public function handleSnapshot(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [, $attempt, $error] = $this->guard->resolve((int) $request->get_param('attempt_id'));
        if ($error !== null) {
            return $error;
        }

        $image = (string) $request->get_param('image');
        $session = $this->cameraService->startSession((int) $attempt['id']);
        $result = $this->cameraService->captureSnapshot($session, $image);

        if (!$result->success) {
            return new \WP_Error('wpcbtpro_snapshot_failed', (string) $result->error, ['status' => 422]);
        }

        return new \WP_REST_Response(['captured' => true]);
    }

    public function handleVerification(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [, $attempt, $error] = $this->guard->resolve((int) $request->get_param('attempt_id'));
        if ($error !== null) {
            return $error;
        }

        $candidate = $this->candidateRepository->find((int) $attempt['candidate_id']);
        if ($candidate === null) {
            return new \WP_Error('wpcbtpro_not_found', __('Candidate not found.', 'wp-cbt-pro'), ['status' => 404]);
        }

        $image = (string) $request->get_param('image');
        $session = $this->cameraService->startSession((int) $attempt['id']);
        $result = $this->cameraService->verifyIdentity($session, $candidate, $image);

        if ($result->message !== null && $result->capturedAttachmentId === null) {
            return new \WP_Error('wpcbtpro_verification_failed', $result->message, ['status' => 422]);
        }

        return new \WP_REST_Response(['status' => $result->status->value]);
    }

    /**
     * The candidate's own browser reports a face mismatch or "no face
     * visible" here after comparing a live frame to their reference photo
     * client-side — this endpoint only logs it and evaluates the shared
     * strike count (AutoMonitoringService), it never trusts the browser's
     * verdict about anything beyond "a check ran and this is what it saw."
     */
    public function handleMonitoringViolation(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$exam, $attempt, $error] = $this->guard->resolve((int) $request->get_param('attempt_id'));
        if ($error !== null) {
            return $error;
        }

        if (empty($exam['auto_monitoring_enabled'])) {
            return new \WP_Error('wpcbtpro_monitoring_disabled', __('Automated monitoring is not enabled for this exam.', 'wp-cbt-pro'), ['status' => 400]);
        }

        if ($attempt['status'] !== 'in_progress') {
            return new \WP_REST_Response(['strikes' => 0, 'submitted' => false, 'message' => '']);
        }

        $violationType = MonitoringViolationType::tryFrom((string) $request->get_param('violation_type'));
        if ($violationType === null) {
            return new \WP_Error('wpcbtpro_invalid_violation', __('Unknown violation type.', 'wp-cbt-pro'), ['status' => 400]);
        }

        $result = $this->autoMonitoring->recordViolation($exam, $attempt, $violationType->value);

        return new \WP_REST_Response($result);
    }

    /** @return array{strikes:int, submitted:bool, message:string}|null */
    private function applyDisconnectPolicy(CameraEventType $type, array $exam, array $attempt): ?array
    {
        if ($attempt['status'] !== 'in_progress' && $attempt['status'] !== 'paused') {
            return null;
        }

        if ($type === CameraEventType::Reconnected) {
            $this->attemptService->resumeAttemptIfPaused($attempt);
            return null;
        }

        if ($type !== CameraEventType::Disconnected || $attempt['status'] !== 'in_progress') {
            return null;
        }

        if (!empty($exam['auto_monitoring_enabled'])) {
            // Already logged by recordEvent() just above in handleEvent() —
            // this only counts what's there and decides, it doesn't log again.
            return $this->autoMonitoring->evaluateStrikes($exam, $attempt);
        }

        $policy = get_option('wpcbtpro_settings')['camera_disconnect_policy'] ?? 'log';

        if ($policy === 'terminate') {
            $this->attemptService->submitAttempt($exam, $attempt);
        } elseif ($policy === 'pause') {
            $this->attemptService->pauseAttempt($attempt);
        }
        // 'log' (default): the event is already recorded above; no state change.

        return null;
    }
}
