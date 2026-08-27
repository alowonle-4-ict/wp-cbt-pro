<?php

declare(strict_types=1);

namespace WPCBTPro\Camera\Providers;

use WPCBTPro\Camera\Base64ImageUploader;
use WPCBTPro\Camera\CameraSessionRepository;
use WPCBTPro\Camera\Contracts\CameraEventType;
use WPCBTPro\Camera\Contracts\CameraSession;
use WPCBTPro\Camera\Contracts\CameraSessionState;
use WPCBTPro\Camera\Contracts\CameraVerificationService;
use WPCBTPro\Camera\Contracts\SnapshotResult;
use WPCBTPro\Camera\Contracts\VerificationResult;
use WPCBTPro\Camera\Contracts\VerificationStatus;
use WPCBTPro\Camera\VerificationRepository;
use WPCBTPro\Monitoring\MonitoringEventRepository;

final class DefaultCameraVerificationService implements CameraVerificationService
{
    public function __construct(
        private readonly CameraSessionRepository $sessions,
        private readonly VerificationRepository $verifications,
        private readonly MonitoringEventRepository $events,
        private readonly Base64ImageUploader $imageUploader,
    ) {
    }

    public function startSession(int $attemptId): CameraSession
    {
        $existing = $this->sessions->findByAttempt($attemptId);
        if ($existing === null) {
            $this->sessions->create($attemptId, CameraSessionState::NotStarted->value);
            $existing = $this->sessions->findByAttempt($attemptId);
        }

        return CameraSession::fromRow($existing);
    }

    public function recordEvent(CameraSession $session, CameraEventType $type, array $context = []): CameraSession
    {
        $this->events->record($session->attemptId, $type->value, $context);

        $newState = match ($type) {
            CameraEventType::Connected, CameraEventType::Reconnected => CameraSessionState::Connected,
            CameraEventType::Disconnected => CameraSessionState::Disconnected,
            CameraEventType::PermissionDenied, CameraEventType::NotFound, CameraEventType::Error => CameraSessionState::Blocked,
            CameraEventType::SnapshotCaptured => null,
        };

        if ($newState !== null) {
            $this->sessions->updateState(
                $session->id,
                $newState->value,
                $newState === CameraSessionState::Connected ? current_time('mysql') : null,
                $newState === CameraSessionState::Disconnected ? current_time('mysql') : null
            );
        }

        return CameraSession::fromRow($this->sessions->findByAttempt($session->attemptId));
    }

    public function captureSnapshot(CameraSession $session, string $base64Image): SnapshotResult
    {
        $attachmentId = $this->imageUploader->upload($base64Image, 'wpcbtpro-snapshot-attempt-' . $session->attemptId);

        if (is_wp_error($attachmentId)) {
            return new SnapshotResult(false, null, $attachmentId->get_error_message());
        }

        $this->events->record($session->attemptId, CameraEventType::SnapshotCaptured->value, [
            'attachment_id' => $attachmentId,
        ]);

        return new SnapshotResult(true, $attachmentId);
    }

    public function verifyIdentity(CameraSession $session, array $candidate, string $base64Image): VerificationResult
    {
        $attachmentId = $this->imageUploader->upload($base64Image, 'wpcbtpro-verify-attempt-' . $session->attemptId);

        if (is_wp_error($attachmentId)) {
            return new VerificationResult(VerificationStatus::Failed, null, $attachmentId->get_error_message());
        }

        // No automated facial comparison ships in core (§12) — every
        // capture is routed to human review. An AI-assisted provider
        // registers its own CameraVerificationService for this method
        // instead of this default ever guessing VERIFIED or FAILED.
        $status = VerificationStatus::ReviewRequired;
        $this->verifications->insert($session->attemptId, $status->value, $attachmentId);

        return new VerificationResult($status, $attachmentId);
    }
}
