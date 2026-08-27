<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Languages;

use WPCBTPro\Programming\Contracts\LanguageDefinition;
use WPCBTPro\Programming\Contracts\ResourceLimits;

final class JavaLanguage implements LanguageDefinition
{
    public function id(): string
    {
        return 'java17';
    }

    public function displayName(): string
    {
        return 'Java 17';
    }

    public function fileExtension(): string
    {
        return 'java';
    }

    public function compileCommand(): ?string
    {
        return 'javac {file}';
    }

    public function executeCommand(): string
    {
        return 'java -cp {dir} {class}';
    }

    public function defaultLimits(): ResourceLimits
    {
        return new ResourceLimits(timeLimitMs: 4000, memoryLimitMb: 256);
    }

    public function monacoLanguageId(): string
    {
        return 'java';
    }
}
