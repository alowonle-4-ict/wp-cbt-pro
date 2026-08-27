<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Contracts;

/**
 * Everything an execution backend needs to grade one submission — nothing
 * more. There is no institution data, candidate identity, or exam context
 * in here; the sandbox only ever sees a language, source code, and test
 * cases (§16, §18).
 */
final class ExecutionJob
{
    /** @param array<int, array{id:int, input:?string, expected_output:?string}> $testCases */
    public function __construct(
        public readonly int $submissionId,
        public readonly string $language,
        public readonly string $source,
        public readonly ?string $entryPoint,
        public readonly int $timeLimitMs,
        public readonly int $memoryLimitMb,
        public readonly array $testCases,
    ) {
    }
}
