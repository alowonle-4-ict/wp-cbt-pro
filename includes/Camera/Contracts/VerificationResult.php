<?php

declare(strict_types=1);

namespace WPCBTPro\Camera\Contracts;

final class VerificationResult
{
    public function __construct(
        public readonly VerificationStatus $status,
        public readonly ?int $capturedAttachmentId = null,
        public readonly ?string $message = null,
    ) {
    }
}
