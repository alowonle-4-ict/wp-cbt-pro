<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Mcq;

use WPCBTPro\Questions\Contracts\Score;
use WPCBTPro\Questions\Contracts\ScoringStrategy;

final class SingleChoiceScoringStrategy implements ScoringStrategy
{
    public function score(array $question, array $answerRow): Score
    {
        $marks = (float) ($question['marks'] ?? 0);
        $storedAnswer = (string) $answerRow['value'];

        $correctOptionId = null;
        foreach ($question['options'] ?? [] as $option) {
            if (!empty($option['is_correct'])) {
                $correctOptionId = (string) $option['id'];
                break;
            }
        }

        $isCorrect = $correctOptionId !== null && $storedAnswer === $correctOptionId;
        $negative = (float) ($question['negative_marks'] ?? 0);

        return new Score($isCorrect ? $marks : -$negative, $marks, $isCorrect);
    }
}
