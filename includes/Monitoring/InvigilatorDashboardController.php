<?php

declare(strict_types=1);

namespace WPCBTPro\Monitoring;

use WPCBTPro\Attempts\AnswerRepository;
use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Attempts\CandidateExamOverrideRepository;
use WPCBTPro\Camera\CameraSessionRepository;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Security\AuditLogger;
use WPCBTPro\Security\Capabilities;

/**
 * A live read model over active attempts (§14) — plus, on the per-attempt
 * detail screen, the small set of live interventions an invigilator or
 * exam manager can make: suspend/resume an attempt in progress, or extend
 * one candidate's time/attempt allowance without touching the exam itself.
 */
final class InvigilatorDashboardController
{
    private const ALERT_EVENT_TYPES = ['CAMERA_DISCONNECTED', 'CAMERA_PERMISSION_DENIED', 'CAMERA_NOT_FOUND', 'CAMERA_ERROR'];

    public function __construct(
        private readonly AttemptRepository $attempts,
        private readonly AttemptService $attemptService,
        private readonly AnswerRepository $answers,
        private readonly ExamRepository $exams,
        private readonly CandidateRepository $candidates,
        private readonly CameraSessionRepository $cameraSessions,
        private readonly MonitoringEventRepository $events,
        private readonly InstitutionContext $institutionContext,
        private readonly CandidateExamOverrideRepository $overrides,
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'maybeProcessRequest']);
    }

    /**
     * Processed on admin_init, not inside render() — the same
     * "headers already sent" constraint every other admin controller in
     * this plugin was fixed for: by the time WordPress calls the
     * add_submenu_page() render callback, the page header has already
     * streamed, so wp_safe_redirect() from there silently fails.
     */
    public function maybeProcessRequest(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- read-only: confirms this hook applies to our own page before doing anything.
        if ((wp_unslash($_GET['page'] ?? '')) !== 'wpcbtpro-invigilator') {
            return;
        }

        // Read-only routing; the real check happens in each handler below.
        // phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        $action = isset($_POST['wpcbtpro_action']) ? sanitize_key($_POST['wpcbtpro_action']) : '';
        if ((wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST' || $action === '') {
            return;
        }
        // phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput

        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- read-only: which attempt the action applies to; each handler verifies its own nonce before mutating anything.
        $attemptId = isset($_POST['attempt_id']) ? absint($_POST['attempt_id']) : 0;
        $attempt = $attemptId > 0 ? $this->attempts->find($attemptId) : null;
        if ($attempt === null) {
            return;
        }

        $exam = $this->exams->find((int) $attempt['exam_id']);
        if ($exam === null || !$this->canManageAttempt($exam)) {
            wp_die(esc_html__('You do not have permission to act on this attempt.', 'wp-cbt-pro'));
        }

        match ($action) {
            'suspend' => $this->handleSuspend($attempt),
            'resume' => $this->handleResume($attempt),
            'extend_time' => $this->handleExtendTime($exam, $attempt),
            'grant_attempt' => $this->handleGrantAttempt($exam, $attempt),
            default => null,
        };
    }

    private function canManageAttempt(array $exam): bool
    {
        if (current_user_can(Capabilities::MANAGE_CBT)) {
            return true;
        }

        $institutionId = $this->institutionContext->currentId();
        return $institutionId === null || (int) $exam['institution_id'] === $institutionId;
    }

    /**
     * Suspend/resume are gated behind REVIEW_ATTEMPTS — a stronger bar than
     * VIEW_MONITORING (which only gets you onto this page), but one every
     * invigilator already has, since they're exactly who's watching live
     * and needs to react to something.
     */
    private function handleSuspend(array $attempt): void
    {
        if (!current_user_can(Capabilities::REVIEW_ATTEMPTS)) {
            wp_die(esc_html__('You do not have permission to suspend an attempt.', 'wp-cbt-pro'));
        }
        check_admin_referer('wpcbtpro_suspend_attempt_' . $attempt['id']);

        $this->attemptService->pauseAttempt($attempt);
        AuditLogger::record('attempt.suspended_by_admin', 'attempt', (int) $attempt['id']);

        $this->redirectToDetail((int) $attempt['id'], 'suspended');
    }

    private function handleResume(array $attempt): void
    {
        if (!current_user_can(Capabilities::REVIEW_ATTEMPTS)) {
            wp_die(esc_html__('You do not have permission to resume an attempt.', 'wp-cbt-pro'));
        }
        check_admin_referer('wpcbtpro_resume_attempt_' . $attempt['id']);

        $this->attemptService->resumeAttemptIfPaused($attempt);
        AuditLogger::record('attempt.resumed_by_admin', 'attempt', (int) $attempt['id']);

        $this->redirectToDetail((int) $attempt['id'], 'resumed');
    }

    /**
     * Extend time / grant an attempt are gated behind MANAGE_CBT_EXAMS — an
     * administrative decision about exam policy for one candidate, not a
     * live-proctoring reaction, so held to the same bar as editing the
     * exam itself rather than the (broader) invigilator capability.
     */
    private function handleExtendTime(array $exam, array $attempt): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT_EXAMS)) {
            wp_die(esc_html__('You do not have permission to change this exam.', 'wp-cbt-pro'));
        }
        check_admin_referer('wpcbtpro_extend_time_' . $attempt['id']);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- max()/absint() below are the sanitization.
        $minutes = max(1, isset($_POST['extra_minutes']) ? absint($_POST['extra_minutes']) : 0);
        $newServerEnd = gmdate('Y-m-d H:i:s', strtotime($attempt['server_end']) + ($minutes * MINUTE_IN_SECONDS));
        $this->attempts->update((int) $attempt['id'], ['server_end' => $newServerEnd]);

        // Persisted too, so a *future* attempt this candidate starts on this
        // exam (after this one is submitted) keeps the same allowance —
        // matches "and after submission" from the original request, not
        // just extending the attempt that's currently running.
        $this->overrides->addExtraMinutes((int) $exam['id'], (int) $attempt['candidate_id'], $minutes);

        AuditLogger::record('attempt.time_extended', 'attempt', (int) $attempt['id'], ['minutes' => $minutes]);

        $this->redirectToDetail((int) $attempt['id'], 'time_extended');
    }

    private function handleGrantAttempt(array $exam, array $attempt): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT_EXAMS)) {
            wp_die(esc_html__('You do not have permission to change this exam.', 'wp-cbt-pro'));
        }
        check_admin_referer('wpcbtpro_grant_attempt_' . $attempt['id']);

        $this->overrides->addExtraAttempts((int) $exam['id'], (int) $attempt['candidate_id'], 1);
        AuditLogger::record('attempt.extra_attempt_granted', 'attempt', (int) $attempt['id']);

        $this->redirectToDetail((int) $attempt['id'], 'attempt_granted');
    }

    private function redirectToDetail(int $attemptId, string $done): void
    {
        wp_safe_redirect(add_query_arg([
            'page' => 'wpcbtpro-invigilator',
            'attempt_id' => $attemptId,
            'done' => $done,
        ], admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::VIEW_MONITORING)) {
            wp_die(esc_html__('You do not have permission to view exam monitoring.', 'wp-cbt-pro'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which attempt's monitoring detail to display, not a state change.
        $attemptId = isset($_GET['attempt_id']) ? absint($_GET['attempt_id']) : 0;
        if ($attemptId > 0) {
            $this->renderDetail($attemptId);
            return;
        }

        $this->renderList();
    }

    /**
     * A live monitoring screen scales with concurrent candidates, not with
     * admin traffic — one lookup per related table instead of one per
     * attempt keeps a refresh at a fixed query count regardless of how many
     * candidates are sitting the exam right now (§15 performance).
     */
    private function renderList(): void
    {
        $institutionId = current_user_can(Capabilities::MANAGE_CBT) ? null : $this->institutionContext->currentId();

        $activeAttempts = $this->attempts->allByStatuses(['in_progress', 'paused']);
        $attemptIds = array_column($activeAttempts, 'id');

        $exams = $this->exams->findMany(array_column($activeAttempts, 'exam_id'));
        $candidates = $this->candidates->findMany(array_column($activeAttempts, 'candidate_id'));
        $answersByAttempt = $this->answers->allForAttempts($attemptIds);
        $cameraSessions = $this->cameraSessions->findManyByAttempts($attemptIds);
        $eventsByAttempt = $this->events->allForAttempts($attemptIds);

        $rows = [];
        foreach ($activeAttempts as $attempt) {
            $attemptId = (int) $attempt['id'];
            $exam = $exams[(int) $attempt['exam_id']] ?? null;
            if ($exam === null || ($institutionId !== null && (int) $exam['institution_id'] !== $institutionId)) {
                continue;
            }

            $candidate = $candidates[(int) $attempt['candidate_id']] ?? null;
            if ($candidate === null) {
                continue;
            }

            $answers = $answersByAttempt[$attemptId] ?? [];
            $total = count($this->attemptService->resolvedQuestionIds($exam, $attempt));
            $answeredCount = count(array_filter($answers, static fn (array $a): bool => trim((string) $a['value']) !== ''));

            $cameraSession = $cameraSessions[$attemptId] ?? null;
            $alertCount = count(array_filter(
                $eventsByAttempt[$attemptId] ?? [],
                static fn (array $e): bool => in_array($e['event_type'], self::ALERT_EVENT_TYPES, true)
            ));

            $lastActivity = $attempt['created_at'];
            foreach ($answers as $answer) {
                if ($answer['updated_at'] > $lastActivity) {
                    $lastActivity = $answer['updated_at'];
                }
            }

            $rows[] = [
                'attempt' => $attempt,
                'exam' => $exam,
                'candidate' => $candidate,
                'answered' => $answeredCount,
                'total' => $total,
                'camera_state' => $cameraSession['state'] ?? null,
                'alert_count' => $alertCount,
                'last_activity' => $lastActivity,
                'time_remaining_seconds' => max(0, strtotime($attempt['server_end']) - current_time('timestamp')),
            ];
        }

        include WPCBTPRO_PATH . 'admin/views/invigilator-dashboard.php';
    }

    private function renderDetail(int $attemptId): void
    {
        $attempt = $this->attempts->find($attemptId);
        if ($attempt === null) {
            wp_die(esc_html__('Attempt not found.', 'wp-cbt-pro'));
        }

        $exam = $this->exams->find((int) $attempt['exam_id']);
        $candidate = $this->candidates->find((int) $attempt['candidate_id']);

        if (!current_user_can(Capabilities::MANAGE_CBT) && $exam !== null) {
            $institutionId = $this->institutionContext->currentId();
            if ($institutionId !== null && (int) $exam['institution_id'] !== $institutionId) {
                wp_die(esc_html__('You do not have permission to view this attempt.', 'wp-cbt-pro'));
            }
        }

        $events = $this->events->allForAttempt($attemptId);
        $canReview = current_user_can(Capabilities::REVIEW_ATTEMPTS);
        $canManageExam = current_user_can(Capabilities::MANAGE_CBT_EXAMS);
        $override = $exam !== null ? $this->overrides->find((int) $exam['id'], (int) $attempt['candidate_id']) : ['extra_minutes' => 0, 'extra_attempts' => 0];

        include WPCBTPRO_PATH . 'admin/views/invigilator-attempt-detail.php';
    }
}
