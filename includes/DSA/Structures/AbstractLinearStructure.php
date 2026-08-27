<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Structures;

use WPCBTPro\DSA\Contracts\StructureDefinition;

/**
 * Stack, Queue, Circular Queue, Deque, Priority Queue, and Linked List all
 * reduce to the same canonical shape — an ordered list of values — and
 * differ only in which operations mutate that list and how (§17). This
 * base class owns the shared text/JSON parsing so each concrete structure
 * only has to implement applyOperation().
 */
abstract class AbstractLinearStructure implements StructureDefinition
{
    /** @param array<int, mixed> $state @param array{op: string, arg: string|null} $operation @return array<int, mixed> */
    abstract protected function applyOperation(array $state, array $operation): array;

    public function simulate(array $operations): array
    {
        $state = [];
        foreach ($operations as $operation) {
            $state = $this->applyOperation($state, $operation);
        }

        return $state;
    }

    public function formatState(array $state): string
    {
        return implode(', ', array_map(static fn ($v): string => (string) $v, $state));
    }

    public function parseStatedAnswer(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $v): string => trim($v), explode(',', $text)),
            static fn (string $v): bool => $v !== ''
        ));
    }

    /** @param array<string, mixed> $decoded */
    public function parseInteractiveState(array $decoded): array
    {
        $values = $decoded['values'] ?? [];
        return is_array($values) ? array_values(array_map(static fn ($v): string => (string) $v, $values)) : [];
    }

    public function supportsInteractive(): bool
    {
        return true;
    }
}
