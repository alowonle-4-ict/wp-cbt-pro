<?php

declare(strict_types=1);

namespace WPCBTPro\Camera\Contracts;

/** The state machine from §11 Fig. 5 — stored verbatim in wp_cbt_camera_sessions.state. */
enum CameraSessionState: string
{
    case NotStarted = 'not_started';
    case Requesting = 'requesting';
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Blocked = 'blocked';
    case Paused = 'paused';
    case Terminated = 'terminated';
}
