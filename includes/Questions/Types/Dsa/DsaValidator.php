<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Dsa;

use WPCBTPro\Questions\Contracts\AnswerValidator;

final class DsaValidator implements AnswerValidator
{
    public function validate(array $question, mixed $rawAnswer): array
    {
        if ($rawAnswer === null || $rawAnswer === '') {
            return []; // unanswered is structurally valid — scoring treats it as unanswered
        }

        if (!is_string($rawAnswer)) {
            return [__('Answer must be submitted as text.', 'wp-cbt-pro')];
        }

        $mode = $question['dsa']['mode'] ?? 'simulation';
        if ($mode === 'interactive' && json_decode($rawAnswer, true) === null && $rawAnswer !== 'null') {
            return [__('The interactive structure could not be read. Try again.', 'wp-cbt-pro')];
        }

        return [];
    }
}
