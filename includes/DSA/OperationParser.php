<?php

declare(strict_types=1);

namespace WPCBTPro\DSA;

use WPCBTPro\DSA\Contracts\StructureDefinition;

/**
 * Converts the admin-authored, one-instruction-per-line DSL (§6's own
 * example: PUSH(10), POP(), ...) into structured operations, and back
 * again for re-editing. Validated against the target structure's
 * allowedOperations() so a typo or a mismatched keyword is caught at save
 * time, not silently ignored during grading.
 */
final class OperationParser
{
    /**
     * @return array<int, array{op: string, arg: string|null}>
     * @throws \InvalidArgumentException on a malformed line or an operation the structure doesn't accept
     */
    public function parse(string $text, StructureDefinition $structure): array
    {
        $operations = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (!preg_match('/^([A-Za-z_]+)\s*\(\s*([^)]*)\s*\)$/', $line, $m)) {
                throw new \InvalidArgumentException(sprintf(
                    /* translators: %s: the unparseable line */
                    __('Could not parse operation: "%s". Use a format like PUSH(10) or POP().', 'wp-cbt-pro'),
                    $line
                ));
            }

            $op = strtoupper($m[1]);
            $arg = trim($m[2]);

            if (!in_array($op, $structure->allowedOperations(), true)) {
                throw new \InvalidArgumentException(sprintf(
                    /* translators: 1: operation keyword, 2: structure label */
                    __('"%1$s" is not a valid operation for %2$s.', 'wp-cbt-pro'),
                    $op,
                    $structure->label()
                ));
            }

            $operations[] = ['op' => $op, 'arg' => $arg === '' ? null : $arg];
        }

        return $operations;
    }

    /** @param array<int, array{op: string, arg: string|null}> $operations */
    public function format(array $operations): string
    {
        return implode("\n", array_map(
            static fn (array $o): string => $o['op'] . '(' . ($o['arg'] ?? '') . ')',
            $operations
        ));
    }
}
