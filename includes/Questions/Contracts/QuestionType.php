<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

/**
 * The one contract every question type satisfies (§5). The exam runtime,
 * admin builder, and Word importer only ever talk to this interface via
 * QuestionTypeRegistry — never to a concrete type — so adding a new type
 * later is one new class plus a registration call, not a change to any of
 * those three call sites.
 */
interface QuestionType
{
    /** Stable identifier stored in wp_cbt_questions.type — never change once shipped. */
    public function id(): string;

    public function label(): string;

    public function category(): QuestionCategory;

    public function renderer(): QuestionRenderer;

    public function validator(): AnswerValidator;

    public function answerProcessor(): AnswerProcessor;

    public function scoringStrategy(): ScoringStrategy;

    public function importHandler(): ?ImportHandler;

    public function exportHandler(): ?ExportHandler;

    public function adminEditor(): AdminEditorView;

    public function candidateUi(): CandidateUiView;

    /**
     * Called once per question when an attempt is finalized (§34, §35) —
     * a type's chance to persist its own final-snapshot data (e.g. a code
     * submission row), regardless of whether it can be auto-scored yet.
     * Most types have nothing to do here.
     *
     * @param array<string, mixed> $question
     * @param array<string, mixed>|null $answerRow the stored wp_cbt_answers row, or null if unanswered
     */
    public function onFinalize(array $question, ?array $answerRow): void;
}
