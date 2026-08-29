<?php

declare(strict_types=1);

namespace WPCBTPro\Import\Word;

use WPCBTPro\Questions\Registry\QuestionTypeRegistry;

/**
 * Orchestrates the pipeline from §6, Fig. 4: open the package, parse
 * structure, resolve each block's TYPE marker against a registered
 * QuestionType, and delegate mapping/validation to that type's
 * ImportHandler. Returns preview rows — nothing here touches the database.
 */
final class WordImportService
{
    /** @var array<string, string> raw TYPE marker (normalized) => registry id */
    private const TYPE_ALIASES = [
        'MCQ' => 'mcq_single',
        'SINGLE_CHOICE' => 'mcq_single',
        'YES_NO' => 'true_false',
        'TRUEFALSE' => 'true_false',
        // The bundled question-template.docx documents this marker for a DSA
        // question's Word markup even though DsaType::importHandler()
        // returns null (DSA questions can't be created via import — they're
        // built in wp-admin). Without this alias, "DSA_SIMULATION" doesn't
        // resolve to the registered 'dsa' type id at all, so the preview
        // shows a misleading "Unknown or unsupported question type" instead
        // of the accurate "DSA questions cannot be created via Word import
        // yet" — exactly the confusion a real institution would hit trying
        // the plugin's own template.
        'DSA_SIMULATION' => 'dsa',
    ];

    public function __construct(private readonly QuestionTypeRegistry $registry)
    {
    }

    /** @return array<int, array<string, mixed>> preview rows, one per parsed block */
    public function parseFile(string $filePath): array
    {
        $package = DocxPackage::open($filePath);
        $documentXml = $package->documentXml();
        $package->close();

        $parser = new DocxQuestionParser(new OmmlToMathMlConverter());
        $blocks = $parser->parse($documentXml);

        return array_map(fn (array $block) => $this->buildPreviewRow($block), $blocks);
    }

    /** @param array<string, mixed> $block */
    private function buildPreviewRow(array $block): array
    {
        $typeId = $this->resolveTypeId((string) $block['type']);
        $type = $typeId !== null && $this->registry->has($typeId) ? $this->registry->get($typeId) : null;
        $importHandler = $type?->importHandler();

        $warnings = [];
        $mapped = null;

        if ($type === null) {
            $warnings[] = sprintf(
                /* translators: %s: the raw TYPE value from the document */
                __('Unknown or unsupported question type: "%s".', 'wp-cbt-pro'),
                $block['type'] !== '' ? $block['type'] : __('(not specified)', 'wp-cbt-pro')
            );
        } elseif ($importHandler === null) {
            $warnings[] = sprintf(
                /* translators: %s: question type label */
                __('"%s" questions cannot be created via Word import yet.', 'wp-cbt-pro'),
                $type->label()
            );
        } else {
            $warnings = $importHandler->validate($block);
            $mapped = $importHandler->mapToQuestionData($block);
        }

        return [
            'block' => $block,
            'type_id' => $type?->id(),
            'type_label' => $type?->label() ?? $block['type'],
            'mapped' => $mapped,
            'warnings' => $warnings,
            'checks' => [
                'options_detected' => count($block['options']) >= 2,
                'answer_detected' => trim((string) $block['answer']) !== '',
                'equation_detected' => $block['has_equation'],
                'marks_detected' => !empty($block['marks']),
            ],
        ];
    }

    private function resolveTypeId(string $rawType): ?string
    {
        $normalized = strtoupper(trim($rawType));
        $normalized = (string) preg_replace('/[\s\-]+/', '_', $normalized);

        if ($normalized === '') {
            return null;
        }

        if (isset(self::TYPE_ALIASES[$normalized])) {
            return self::TYPE_ALIASES[$normalized];
        }

        $asId = strtolower($normalized);
        return $this->registry->has($asId) ? $asId : null;
    }
}
