<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Contracts;

final class TestCaseResult
{
    public const VERDICT_PASSED = 'passed';
    public const VERDICT_WRONG_ANSWER = 'wrong_answer';
    public const VERDICT_RUNTIME_ERROR = 'runtime_error';
    public const VERDICT_TIME_LIMIT_EXCEEDED = 'time_limit_exceeded';
    public const VERDICT_MEMORY_LIMIT_EXCEEDED = 'memory_limit_exceeded';

    public function __construct(
        public readonly int $testCaseId,
        public readonly bool $passed,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly ?int $exitCode,
        public readonly int $runtimeMs,
        public readonly ?int $memoryKb,
        public readonly string $verdict,
    ) {
    }
}
