<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Contracts;

/**
 * One entry in the language abstraction from §16.1 — the plugin never
 * hard-codes a single language or execution backend. §11 (Phase 11) is the
 * only thing that ever actually invokes compileCommand()/executeCommand();
 * everything before that (admin builder, candidate editor) only needs id(),
 * displayName(), and fileExtension() to configure the UI.
 */
interface LanguageDefinition
{
    /** Stable identifier stored in wp_cbt_programming_questions.language — never change once shipped. */
    public function id(): string;

    public function displayName(): string;

    public function fileExtension(): string;

    /** Null for interpreted languages that skip a separate compile step. */
    public function compileCommand(): ?string;

    public function executeCommand(): string;

    public function defaultLimits(): ResourceLimits;

    /** The Monaco editor language mode id (e.g. 'python', 'cpp', 'java'). */
    public function monacoLanguageId(): string;
}
