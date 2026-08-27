<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Mcq;

use WPCBTPro\Questions\Contracts\AnswerProcessor;

final class SingleChoiceAnswerProcessor implements AnswerProcessor
{
    public function process(array $question, mixed $rawAnswer): string
    {
        $optionId = is_array($rawAnswer) ? ($rawAnswer[0] ?? '') : (string) ($rawAnswer ?? '');

        return sanitize_text_field($optionId);
    }
}
