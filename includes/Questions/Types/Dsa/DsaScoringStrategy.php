<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Dsa;

use WPCBTPro\DSA\Contracts\StructureDefinition;
use WPCBTPro\DSA\Registry\StructureRegistry;
use WPCBTPro\Questions\Contracts\Score;
use WPCBTPro\Questions\Contracts\ScoringStrategy;

/**
 * Every structure reduces to the same comparison (§17, §26–§27): compute
 * the expected canonical state via simulate(), compute the candidate's
 * canonical state from whichever mode they answered in, compare
 * position-by-position for partial credit. A tree and a stack are graded
 * by the exact same code path here.
 */
final class DsaScoringStrategy implements ScoringStrategy
{
    public function __construct(private readonly StructureRegistry $structures)
    {
    }

    public function score(array $question, array $answerRow): Score
    {
        $marks = (float) ($question['marks'] ?? 0);
        $dsa = $question['dsa'] ?? null;

        if ($dsa === null || !$this->structures->has($dsa['structure'])) {
            return Score::pendingManualReview($marks);
        }

        $structure = $this->structures->get($dsa['structure']);
        $expected = $structure->simulate($dsa['operations']);

        $raw = (string) $answerRow['value'];
        $candidate = $dsa['mode'] === 'interactive'
            ? $this->parseInteractive($structure, $raw)
            : $structure->parseStatedAnswer($raw);

        if ($candidate === null) {
            return new Score(0.0, $marks, false);
        }

        $fraction = $this->partialCreditFraction($expected, $candidate);
        $isCorrect = $candidate === $expected;

        return new Score(round($fraction * $marks, 2), $marks, $isCorrect, [
            'expected' => $structure->formatState($expected),
            'submitted' => $structure->formatState($candidate),
        ]);
    }

    /** @return array<int, mixed>|null */
    private function parseInteractive(StructureDefinition $structure, string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $structure->parseInteractiveState($decoded) : null;
    }

    /** @param array<int, mixed> $expected @param array<int, mixed> $candidate */
    private function partialCreditFraction(array $expected, array $candidate): float
    {
        if ($expected === []) {
            return $candidate === [] ? 1.0 : 0.0;
        }

        $matches = 0;
        foreach ($expected as $index => $value) {
            if (($candidate[$index] ?? null) === $value) {
                $matches++;
            }
        }

        return $matches / count($expected);
    }
}
