<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

/**
 * Structural validation only — "is this a shape of answer this question type
 * can accept" (e.g. the option id exists). Never decides correctness; that's
 * ScoringStrategy's job, and it runs later, server-side, at grading time.
 */
interface AnswerValidator
{
    /**
     * @param array<string, mixed> $question
     * @return string[] validation error messages; empty means structurally valid
     */
    public function validate(array $question, mixed $rawAnswer): array;
}
