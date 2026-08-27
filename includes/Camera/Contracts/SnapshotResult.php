<?php

declare(strict_types=1);

namespace WPCBTPro\Camera\Contracts;

final class SnapshotResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $attachmentId = null,
        public readonly ?string $error = null,
    ) {
    }
}
