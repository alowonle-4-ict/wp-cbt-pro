<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Mcq;

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

class SingleChoiceType implements QuestionType, QuestionRenderer
{
    use RendersHtmlContent;

    public function id(): string
    {
        return 'mcq_single';
    }

    public function label(): string
    {
        return __('Single Choice (MCQ)', 'wp-cbt-pro');
    }

    public function category(): QuestionCategory
    {
        return QuestionCategory::Objective;
    }

    public function renderer(): QuestionRenderer
    {
        return $this;
    }

    public function validator(): AnswerValidator
    {
        return new SingleChoiceValidator();
    }

    public function answerProcessor(): AnswerProcessor
    {
        return new SingleChoiceAnswerProcessor();
    }

    public function scoringStrategy(): ScoringStrategy
    {
        return new SingleChoiceScoringStrategy();
    }

    public function importHandler(): ?ImportHandler
    {
        return new SingleChoiceImportHandler();
    }

    public function exportHandler(): ?ExportHandler
    {
        return null;
    }

    public function adminEditor(): AdminEditorView
    {
        return new SingleChoiceAdminEditor();
    }

    public function candidateUi(): CandidateUiView
    {
        return new SingleChoiceCandidateUi();
    }

    public function onFinalize(array $question, ?array $answerRow): void
    {
        // Nothing type-specific to persist beyond the answer itself.
    }
}
