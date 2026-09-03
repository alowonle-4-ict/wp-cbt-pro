<?php

declare(strict_types=1);

namespace WPCBTPro\Monitoring\Contracts;

/**
 * The violation kinds AutoMonitoringService's REST endpoint accepts from the
 * browser's own face-api.js check. CAMERA_DISCONNECTED (from
 * CameraEventType) counts toward the same strike total but isn't listed
 * here — it arrives through the existing /camera-event route, not this one.
 */
enum MonitoringViolationType: string
{
    case FaceMismatch = 'FACE_MISMATCH';
    case NoFaceDetected = 'NO_FACE_DETECTED';
}
