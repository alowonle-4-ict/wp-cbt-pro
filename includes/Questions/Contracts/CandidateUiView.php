<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

/**
 * The candidate-facing answer input — radio buttons, a text area, Monaco,
 * or a DSA canvas. Distinct from QuestionRenderer, which only ever shows
 * the read-only prompt.
 *
 * The exam runtime shows one question per request, so every implementation
 * names its primary input(s) "wpcbtpro_answer" (or "wpcbtpro_answer[]" for
 * a multi-value type) — the surrounding form's hidden question_id field is
 * what ties the posted value back to the right question.
 */
interface CandidateUiView
{
    /**
     * @param array<string, mixed> $question
     * @param string|null $currentAnswer the candidate's last autosaved value, if any
     */
    public function render(array $question, ?string $currentAnswer): void;
}
