<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Contracts;

final class ExecutionReport
{
    /** @param array<int, TestCaseResult> $testCaseResults */
    public function __construct(
        public readonly bool $compiled,
        public readonly ?string $compileError,
        public readonly array $testCaseResults,
    ) {
    }
}
