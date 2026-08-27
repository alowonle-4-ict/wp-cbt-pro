<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Languages;

use WPCBTPro\Programming\Contracts\LanguageDefinition;
use WPCBTPro\Programming\Contracts\ResourceLimits;

final class CLanguage implements LanguageDefinition
{
    public function id(): string
    {
        return 'c';
    }

    public function displayName(): string
    {
        return 'C';
    }

    public function fileExtension(): string
    {
        return 'c';
    }

    public function compileCommand(): ?string
    {
        return 'gcc {file} -O2 -o {output}';
    }

    public function executeCommand(): string
    {
        return '{output}';
    }

    public function defaultLimits(): ResourceLimits
    {
        return new ResourceLimits(timeLimitMs: 2000, memoryLimitMb: 64);
    }

    public function monacoLanguageId(): string
    {
        return 'c';
    }
}
