<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Mcq;

use WPCBTPro\Questions\Contracts\AnswerValidator;

final class SingleChoiceValidator implements AnswerValidator
{
    public function validate(array $question, mixed $rawAnswer): array
    {
        $optionId = is_array($rawAnswer) ? ($rawAnswer[0] ?? null) : $rawAnswer;

        if ($optionId === null || $optionId === '') {
            return [];
        }

        $validIds = array_map(static fn (array $opt): string => (string) $opt['id'], $question['options'] ?? []);
        if ($validIds !== [] && !in_array((string) $optionId, $validIds, true)) {
            return [__('Selected option does not belong to this question.', 'wp-cbt-pro')];
        }

        return [];
    }
}
