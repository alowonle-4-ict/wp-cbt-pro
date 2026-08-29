<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Programming;

use WPCBTPro\Programming\CodeExecutionResultRepository;
use WPCBTPro\Programming\CodeSubmissionRepository;
use WPCBTPro\Programming\Registry\LanguageRegistry;
use WPCBTPro\Questions\Contracts\AdminEditorView;
use WPCBTPro\Questions\Contracts\AnswerProcessor;
use WPCBTPro\Questions\Contracts\AnswerValidator;
use WPCBTPro\Questions\Contracts\CandidateUiView;
use WPCBTPro\Questions\Contracts\ExportHandler;
use WPCBTPro\Questions\Contracts\ImportHandler;
use WPCBTPro\Questions\Contracts\QuestionCategory;
use WPCBTPro\Questions\Contracts\QuestionRenderer;
use WPCBTPro\Questions\Contracts\QuestionType;
use WPCBTPro\Questions\Contracts\ScoringStrategy;
use WPCBTPro\Questions\Contracts\Support\RendersHtmlContent;

final class ProgrammingType implements QuestionType, QuestionRenderer
{
    use RendersHtmlContent;

    public function __construct(
        private readonly LanguageRegistry $languages,
        private readonly CodeSubmissionRepository $codeSubmissions,
        private readonly CodeExecutionResultRepository $codeExecutionResults,
    ) {
    }

    public function id(): string
    {
        return 'programming';
    }

    public function label(): string
    {
        return __('Programming', 'wp-cbt-pro');
    }

    public function category(): QuestionCategory
    {
        return QuestionCategory::Programming;
    }

    public function renderer(): QuestionRenderer
    {
        return $this;
    }

    public function validator(): AnswerValidator
    {
        return new ProgrammingValidator();
    }

    public function answerProcessor(): AnswerProcessor
    {
        return new ProgrammingAnswerProcessor();
    }

    public function scoringStrategy(): ScoringStrategy
    {
        return new ProgrammingScoringStrategy($this->codeSubmissions, $this->codeExecutionResults);
    }

    public function importHandler(): ?ImportHandler
    {
        // Word import has no way to author starter code, test cases, or
        // resource limits — programming questions are built in wp-admin.
        return null;
    }

    public function exportHandler(): ?ExportHandler
    {
        return null;
    }

    public function adminEditor(): AdminEditorView
    {
        return new ProgrammingAdminEditor($this->languages);
    }

    public function candidateUi(): CandidateUiView
    {
        return new ProgrammingCandidateUi($this->languages);
    }

    public function onFinalize(array $question, ?array $answerRow): void
    {
        if ($answerRow === null || trim((string) $answerRow['value']) === '') {
            return;
        }

        $language = $question['programming']['language'] ?? 'plaintext';
        $this->codeSubmissions->insert((int) $answerRow['id'], $language, (string) $answerRow['value']);
    }
}
