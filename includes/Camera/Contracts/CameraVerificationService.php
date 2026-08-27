<?php

declare(strict_types=1);

namespace WPCBTPro\Camera\Contracts;

/**
 * The single extension point for camera/identity providers (§11–§12, §23).
 * The built-in DefaultCameraVerificationService never runs facial
 * recognition; Pro/Enterprise or third-party AI-assisted providers
 * register a different implementation of this same interface — the exam
 * runtime and REST layer never know which one they're talking to.
 */
interface CameraVerificationService
{
    public function startSession(int $attemptId): CameraSession;

    /** @param array<string, mixed> $context */
    public function recordEvent(CameraSession $session, CameraEventType $type, array $context = []): CameraSession;

    public function captureSnapshot(CameraSession $session, string $base64Image): SnapshotResult;

    /** @param array<string, mixed> $candidate */
    public function verifyIdentity(CameraSession $session, array $candidate, string $base64Image): VerificationResult;
}
