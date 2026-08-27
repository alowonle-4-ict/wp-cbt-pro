<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Contracts;

final class ResourceLimits
{
    public function __construct(
        public readonly int $timeLimitMs,
        public readonly int $memoryLimitMb,
        public readonly int $maxProcesses = 1,
        public readonly int $maxOutputBytes = 65536,
    ) {
    }
}
