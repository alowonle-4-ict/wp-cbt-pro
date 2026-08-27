<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Languages;

use WPCBTPro\Programming\Contracts\LanguageDefinition;
use WPCBTPro\Programming\Contracts\ResourceLimits;

final class JavaScriptLanguage implements LanguageDefinition
{
    public function id(): string
    {
        return 'javascript';
    }

    public function displayName(): string
    {
        return 'JavaScript (Node.js)';
    }

    public function fileExtension(): string
    {
        return 'js';
    }

    public function compileCommand(): ?string
    {
        return null;
    }

    public function executeCommand(): string
    {
        return 'node {file}';
    }

    public function defaultLimits(): ResourceLimits
    {
        return new ResourceLimits(timeLimitMs: 2000, memoryLimitMb: 128);
    }

    public function monacoLanguageId(): string
    {
        return 'javascript';
    }
}
