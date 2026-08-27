<?php

declare(strict_types=1);

namespace WPCBTPro\Camera\Contracts;

/** Logged events from §11. The browser and PHP share these exact string values — no translation table in between. */
enum CameraEventType: string
{
    case Connected = 'CAMERA_CONNECTED';
    case Disconnected = 'CAMERA_DISCONNECTED';
    case PermissionDenied = 'CAMERA_PERMISSION_DENIED';
    case NotFound = 'CAMERA_NOT_FOUND';
    case Error = 'CAMERA_ERROR';
    case Reconnected = 'CAMERA_RECONNECTED';
    case SnapshotCaptured = 'CAMERA_SNAPSHOT';
}
