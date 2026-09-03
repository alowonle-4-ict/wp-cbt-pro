<?php

declare(strict_types=1);

namespace WPCBTPro\Monitoring;

use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Camera\Contracts\CameraEventType;
use WPCBTPro\Monitoring\Contracts\MonitoringViolationType;
use WPCBTPro\Security\AuditLogger;

/**
 * "Warn 3 times, then submit" — every violation kind (a live frame that
 * doesn't match the candidate's reference photo, no face visible at all —
 * camera covered, blocked, or the candidate stepped away — or the camera
 * disconnecting) counts toward one shared total per attempt, checked here
 * against MonitoringEventRepository's own audit log rather than a separate
 * counter, so the strike count and the record an invigilator reviews are
 * always the same numbers. Reaching the limit submits the attempt through
 * the same AttemptService::submitAttempt() every other submission path
 * uses — there's no separate "auto-fail" code path to drift out of sync
 * with the real one.
 */
final class AutoMonitoringService
{
    private const MAX_STRIKES = 3;

    /** @var string[] event_type values that count toward the shared strike total */
    private const COUNTABLE_EVENT_TYPES = [
        MonitoringViolationType::FaceMismatch->value,
        MonitoringViolationType::NoFaceDetected->value,
        CameraEventType::Disconnected->value,
    ];

    public function __construct(
        private readonly MonitoringEventRepository $events,
        private readonly AttemptService $attemptService,
    ) {
    }

    /**
     * Logs a new violation (face mismatch / no face detected, reported by
     * the browser's own check) and evaluates strikes against it.
     *
     * @param array<string, mixed> $exam
     * @param array<string, mixed> $attempt
     * @return array{strikes:int, submitted:bool, message:string}
     */
    public function recordViolation(array $exam, array $attempt, string $violationType): array
    {
        $this->events->record((int) $attempt['id'], $violationType);

        return $this->evaluateStrikes($exam, $attempt);
    }

    /**
     * For a violation already logged elsewhere — CAMERA_DISCONNECTED is
     * recorded by the existing /camera-event pipeline before this ever
     * runs — counts and decides without logging it a second time.
     *
     * @param array<string, mixed> $exam
     * @param array<string, mixed> $attempt
     * @return array{strikes:int, submitted:bool, message:string}
     */
    public function evaluateStrikes(array $exam, array $attempt): array
    {
        $attemptId = (int) $attempt['id'];
        $strikes = $this->events->countByTypes($attemptId, self::COUNTABLE_EVENT_TYPES);
        $latestType = $this->events->latestTypeForAttempt($attemptId, self::COUNTABLE_EVENT_TYPES);

        if ($strikes >= self::MAX_STRIKES) {
            $this->attemptService->submitAttempt($exam, $attempt);
            AuditLogger::record('attempt.auto_submitted_monitoring', 'attempt', $attemptId, ['strikes' => $strikes]);

            return [
                'strikes' => $strikes,
                'submitted' => true,
                'message' => __('Your exam was submitted automatically after repeated monitoring violations.', 'wp-cbt-pro'),
            ];
        }

        return [
            'strikes' => $strikes,
            'submitted' => false,
            'message' => sprintf(
                /* translators: 1: current warning number, 2: total warnings allowed before the exam auto-submits, 3: what triggered this warning */
                __('Warning %1$d of %2$d: %3$s. Further violations will submit your exam automatically.', 'wp-cbt-pro'),
                $strikes,
                self::MAX_STRIKES,
                $this->describeViolation($latestType)
            ),
        ];
    }

    private function describeViolation(?string $type): string
    {
        return match ($type) {
            MonitoringViolationType::FaceMismatch->value => __('the face in view did not match your registered photo', 'wp-cbt-pro'),
            MonitoringViolationType::NoFaceDetected->value => __('no face was detected — make sure your camera is uncovered and stay in view', 'wp-cbt-pro'),
            CameraEventType::Disconnected->value => __('your camera connection was lost', 'wp-cbt-pro'),
            default => __('a monitoring issue was detected', 'wp-cbt-pro'),
        };
    }
}
