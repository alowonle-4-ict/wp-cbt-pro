<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Languages;

use WPCBTPro\Programming\Contracts\LanguageDefinition;
use WPCBTPro\Programming\Contracts\ResourceLimits;

final class CppLanguage implements LanguageDefinition
{
    public function id(): string
    {
        return 'cpp17';
    }

    public function displayName(): string
    {
        return 'C++ (17)';
    }

    public function fileExtension(): string
    {
        return 'cpp';
    }

    public function compileCommand(): ?string
    {
        return 'g++ -std=c++17 {file} -O2 -o {output}';
    }

    public function executeCommand(): string
    {
        return '{output}';
    }

    public function defaultLimits(): ResourceLimits
    {
        return new ResourceLimits(timeLimitMs: 2000, memoryLimitMb: 128);
    }

    public function monacoLanguageId(): string
    {
        return 'cpp';
    }
}
