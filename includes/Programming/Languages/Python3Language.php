<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Languages;

use WPCBTPro\Programming\Contracts\LanguageDefinition;
use WPCBTPro\Programming\Contracts\ResourceLimits;

final class Python3Language implements LanguageDefinition
{
    public function id(): string
    {
        return 'python3';
    }

    public function displayName(): string
    {
        return 'Python 3';
    }

    public function fileExtension(): string
    {
        return 'py';
    }

    public function compileCommand(): ?string
    {
        return null;
    }

    public function executeCommand(): string
    {
        return 'python3 {file}';
    }

    public function defaultLimits(): ResourceLimits
    {
        return new ResourceLimits(timeLimitMs: 2000, memoryLimitMb: 128);
    }

    public function monacoLanguageId(): string
    {
        return 'python';
    }
}
