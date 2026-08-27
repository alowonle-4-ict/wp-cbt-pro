<?php

declare(strict_types=1);

namespace WPCBTPro\Camera\Contracts;

final class CameraSession
{
    private function __construct(
        public readonly int $id,
        public readonly int $attemptId,
        public readonly CameraSessionState $state,
        public readonly ?string $connectedAt,
        public readonly ?string $disconnectedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['attempt_id'],
            CameraSessionState::from($row['state']),
            $row['connected_at'] ?? null,
            $row['disconnected_at'] ?? null,
        );
    }
}
