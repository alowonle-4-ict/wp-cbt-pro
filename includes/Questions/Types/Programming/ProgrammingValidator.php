<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Programming;

use WPCBTPro\Questions\Contracts\AnswerValidator;

/**
 * Structural only — code that fails to compile or run is a grading outcome
 * (§16.2), decided later by the execution service, never something this
 * layer rejects up front.
 */
final class ProgrammingValidator implements AnswerValidator
{
    public function validate(array $question, mixed $rawAnswer): array
    {
        if (!is_string($rawAnswer)) {
            return [__('Code must be submitted as plain text.', 'wp-cbt-pro')];
        }

        return [];
    }
}
