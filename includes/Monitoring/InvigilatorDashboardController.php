<?php

declare(strict_types=1);

namespace WPCBTPro\Monitoring;

use WPCBTPro\Attempts\AnswerRepository;
use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Camera\CameraSessionRepository;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Security\Capabilities;

/**
 * A live read model over active attempts (§14) — this never writes
 * anything. Flagging or acting on what it shows is a separate, explicit
 * step (verification review, manual attempt review), never automatic.
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
    ) {
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

        include WPCBTPRO_PATH . 'admin/views/invigilator-attempt-detail.php';
    }
}
