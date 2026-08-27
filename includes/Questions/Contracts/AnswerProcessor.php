<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

/**
 * Normalizes a raw candidate submission into the canonical string stored in
 * wp_cbt_answers.value — e.g. sorting a multi-select answer so scoring can
 * do a plain equality check instead of re-deriving order every time.
 */
interface AnswerProcessor
{
    /**
     * @param array<string, mixed> $question
     */
    public function process(array $question, mixed $rawAnswer): string;
}
