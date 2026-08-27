<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts\Support;

/**
 * Every question type stores its prompt in the same wp_cbt_questions.content
 * column; most types render it identically. A type only needs its own
 * QuestionRenderer when the prompt means something beyond sanitized HTML
 * (e.g. a DSA operation sequence).
 */
trait RendersHtmlContent
{
    public function renderPrompt(array $question): string
    {
        return wp_kses_post($question['content'] ?? '');
    }
}
