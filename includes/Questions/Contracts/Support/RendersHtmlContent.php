<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts\Support;

use WPCBTPro\Import\Word\OmmlToMathMlConverter;

/**
 * Every question type stores its prompt in the same wp_cbt_questions.content
 * column; most types render it identically. A type only needs its own
 * QuestionRenderer when the prompt means something beyond sanitized HTML
 * (e.g. a DSA operation sequence).
 */
trait RendersHtmlContent
{
    /** @var array<string, array<string, bool>>|null */
    private static ?array $allowedHtml = null;

    public function renderPrompt(array $question): string
    {
        return wp_kses((string) ($question['content'] ?? ''), self::allowedPromptHtml());
    }

    /**
     * wp_kses_post()'s default allowlist doesn't include MathML, so a plain
     * wp_kses_post() call here would silently strip every equation the Word
     * importer converted from OMML — matches the allowlist Import Preview
     * already renders with (WordImportAdminController::renderPreview()).
     *
     * @return array<string, array<string, bool>>
     */
    private static function allowedPromptHtml(): array
    {
        return self::$allowedHtml ??= array_merge(wp_kses_allowed_html('post'), OmmlToMathMlConverter::allowedKsesTags());
    }
}
