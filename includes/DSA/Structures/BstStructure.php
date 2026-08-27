<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Structures;

use WPCBTPro\DSA\Contracts\StructureDefinition;

/**
 * The one structure whose canonical shape isn't already a flat list —
 * simulate() builds a real binary search tree via standard numeric-compare
 * insertion, then reduces it to a level-order array with null placeholders
 * for missing children (§27's own worked example is exactly this: insert
 * 50, 30, 70, 20, 40, 60, 80 and compare the resulting shape). Once
 * reduced to that array, comparison and partial credit work identically to
 * every linear structure — a tree is just a list with a specific slotting
 * rule.
 */
final class BstStructure implements StructureDefinition
{
    public function id(): string
    {
        return 'bst';
    }

    public function label(): string
    {
        return __('Binary Search Tree', 'wp-cbt-pro');
    }

    public function allowedOperations(): array
    {
        return ['INSERT'];
    }

    public function simulate(array $operations): array
    {
        $root = null;
        foreach ($operations as $operation) {
            if ($operation['op'] === 'INSERT') {
                $root = $this->insert($root, $operation['arg']);
            }
        }

        return $this->toLevelOrderArray($root);
    }

    public function formatState(array $state): string
    {
        return implode(', ', array_map(static fn ($v): string => $v === null ? 'null' : (string) $v, $state));
    }

    public function parseStatedAnswer(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        return array_map(
            static function (string $v): ?string {
                $v = trim($v);
                return in_array(strtolower($v), ['null', '_', '-', ''], true) ? null : $v;
            },
            explode(',', $text)
        );
    }

    /** @param array<string, mixed> $decoded {"value":50,"left":{...}|null,"right":{...}|null} or [] for an empty tree */
    public function parseInteractiveState(array $decoded): array
    {
        return $this->toLevelOrderArray($decoded === [] ? null : $decoded);
    }

    public function supportsInteractive(): bool
    {
        return true;
    }

    /** @return array{value: string, left: mixed, right: mixed}|null */
    private function insert(?array $node, string $value): array
    {
        if ($node === null) {
            return ['value' => $value, 'left' => null, 'right' => null];
        }

        if ((float) $value < (float) $node['value']) {
            $node['left'] = $this->insert($node['left'], $value);
        } elseif ((float) $value > (float) $node['value']) {
            $node['right'] = $this->insert($node['right'], $value);
        }
        // Equal values are ignored — standard BST insert doesn't store duplicates.

        return $node;
    }

    /** @param array{value: mixed, left: mixed, right: mixed}|null $root @return array<int, mixed> */
    private function toLevelOrderArray(?array $root): array
    {
        if ($root === null) {
            return [];
        }

        $result = [];
        $queue = [$root];

        while ($queue !== []) {
            $hasAnyNonNull = false;
            $nextQueue = [];

            foreach ($queue as $node) {
                if ($node === null) {
                    $result[] = null;
                    $nextQueue[] = null;
                    $nextQueue[] = null;
                    continue;
                }
                $result[] = (string) $node['value'];
                $hasAnyNonNull = true;
                $nextQueue[] = $node['left'] ?? null;
                $nextQueue[] = $node['right'] ?? null;
            }

            if (!$hasAnyNonNull) {
                break;
            }
            $queue = $nextQueue;
        }

        while ($result !== [] && end($result) === null) {
            array_pop($result);
        }

        return $result;
    }
}
