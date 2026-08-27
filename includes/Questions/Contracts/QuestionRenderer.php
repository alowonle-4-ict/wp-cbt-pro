<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

/**
 * Renders the read-only question prompt — shared by admin preview, the
 * candidate exam screen, and printed/exported papers. Never renders the
 * answer input itself; that's CandidateUiView.
 */
interface QuestionRenderer
{
    /**
     * @param array<string, mixed> $question full question row, with 'options' merged in where relevant
     * @return string safe HTML
     */
    public function renderPrompt(array $question): string;
}
