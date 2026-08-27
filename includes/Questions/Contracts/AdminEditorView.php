<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

/**
 * The type-specific portion of the admin question builder form — option
 * rows for MCQ, starter code + test cases for Programming, structure +
 * operations for DSA. The shared fields (subject, marks, negative marks)
 * live outside this contract; only what varies per type belongs here.
 */
interface AdminEditorView
{
    /**
     * @param array<string, mixed>|null $question null when creating
     * @param string[] $errors field => message
     */
    public function render(?array $question, array $errors): void;

    /**
     * @param array<string, mixed> $postData raw, unslashed $_POST
     * @return array<string, mixed> sanitized type-specific fields
     */
    public function extract(array $postData): array;
}
