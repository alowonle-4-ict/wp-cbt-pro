<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Contracts;

/**
 * One entry in the structure abstraction (§17, mirroring LanguageRegistry
 * from §16.1 and QuestionTypeRegistry from §5) — the DSA engine never
 * hard-codes a single structure. Everything reduces to one comparison:
 * compute the expected canonical state via simulate(), compute the
 * candidate's canonical state via parseStatedAnswer() or
 * parseInteractiveState(), compare the two arrays. A tree and a stack both
 * fit this shape — a tree's canonical state is just its level-order array.
 */
interface StructureDefinition
{
    /** Stable identifier stored in wp_cbt_dsa_questions.structure — never change once shipped. */
    public function id(): string;

    public function label(): string;

    /** @return string[] operation keywords this structure's admin-authored DSL accepts, e.g. ['PUSH', 'POP'] */
    public function allowedOperations(): array;

    /**
     * Runs a sequence of operations from an empty structure.
     *
     * @param array<int, array{op: string, arg: string|null}> $operations
     * @return array<int, mixed> the canonical final state — an ordered list of values (or null placeholders for a tree's missing nodes)
     */
    public function simulate(array $operations): array;

    /** @param array<int, mixed> $state */
    public function formatState(array $state): string;

    /** Simulation-mode candidate answer (free text) → the same canonical shape simulate() returns. */
    public function parseStatedAnswer(string $text): array;

    /**
     * Interactive-mode candidate submission (decoded JSON from the JS
     * widget) → the same canonical shape simulate() returns.
     *
     * @param array<string, mixed> $decoded
     * @return array<int, mixed>
     */
    public function parseInteractiveState(array $decoded): array;

    /** Whether a JS interactive widget exists for this structure (§27) — gates whether "interactive" mode is offered in the admin builder. */
    public function supportsInteractive(): bool;
}
