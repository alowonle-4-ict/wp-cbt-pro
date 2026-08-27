<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

/**
 * Maps one parsed block from the Word template (§6) onto this type's stored
 * fields. Optional — a type without one simply can't be created via Word
 * import, and the importer flags that block as an unsupported type (§6.1)
 * rather than guessing.
 */
interface ImportHandler
{
    /**
     * @param array<string, mixed> $parsedBlock structural fields the docx parser extracted (TYPE, MARKS, QUESTION, options, ANSWER, ...)
     * @return array<string, mixed> shaped for insertion into wp_cbt_questions (+ type-specific tables)
     */
    public function mapToQuestionData(array $parsedBlock): array;

    /**
     * @param array<string, mixed> $parsedBlock
     * @return string[] warnings surfaced in the Import Preview (§6.1) — never throws
     */
    public function validate(array $parsedBlock): array;
}
