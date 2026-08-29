<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Dsa;

use WPCBTPro\DSA\DsaStateRepository;
use WPCBTPro\DSA\OperationParser;
use WPCBTPro\DSA\Registry\StructureRegistry;
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

final class DsaType implements QuestionType, QuestionRenderer
{
    use RendersHtmlContent;

    public function __construct(
        private readonly StructureRegistry $structures,
        private readonly OperationParser $operationParser,
        private readonly DsaStateRepository $states,
    ) {
    }

    public function id(): string
    {
        return 'dsa';
    }

    public function label(): string
    {
        return __('Data Structures & Algorithms', 'wp-cbt-pro');
    }

    public function category(): QuestionCategory
    {
        return QuestionCategory::Dsa;
    }

    public function renderer(): QuestionRenderer
    {
        return $this;
    }

    public function validator(): AnswerValidator
    {
        return new DsaValidator();
    }

    public function answerProcessor(): AnswerProcessor
    {
        return new DsaAnswerProcessor();
    }

    public function scoringStrategy(): ScoringStrategy
    {
        return new DsaScoringStrategy($this->structures);
    }

    public function importHandler(): ?ImportHandler
    {
        // Word import can capture DATA_STRUCTURE metadata (§6.2) but not a
        // safely-validated operation sequence — DSA questions are built in
        // wp-admin, same reasoning as Programming (§15).
        return null;
    }

    public function exportHandler(): ?ExportHandler
    {
        return null;
    }

    public function adminEditor(): AdminEditorView
    {
        return new DsaAdminEditor($this->structures, $this->operationParser);
    }

    public function candidateUi(): CandidateUiView
    {
        return new DsaCandidateUi($this->structures);
    }

    public function onFinalize(array $question, ?array $answerRow): void
    {
        if ($answerRow === null || trim((string) $answerRow['value']) === '') {
            return;
        }

        $dsa = $question['dsa'] ?? null;
        $isValid = false;

        if ($dsa !== null && $this->structures->has($dsa['structure'])) {
            $structure = $this->structures->get($dsa['structure']);
            $expected = $structure->simulate($dsa['operations']);
            $candidate = $dsa['mode'] === 'interactive'
                ? (is_array($decoded = json_decode((string) $answerRow['value'], true)) ? $structure->parseInteractiveState($decoded) : null)
                : $structure->parseStatedAnswer((string) $answerRow['value']);
            $isValid = $candidate === $expected;
        }

        $this->states->upsert((int) $answerRow['id'], (string) $answerRow['value'], $isValid);
    }
}
