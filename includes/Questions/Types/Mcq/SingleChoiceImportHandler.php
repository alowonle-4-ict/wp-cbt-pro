<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Mcq;

use WPCBTPro\Questions\Contracts\ImportHandler;

final class SingleChoiceImportHandler implements ImportHandler
{
    public function mapToQuestionData(array $parsedBlock): array
    {
        $answerLetter = strtoupper(trim((string) ($parsedBlock['answer'] ?? '')));

        $options = [];
        foreach ($parsedBlock['options'] ?? [] as $index => $option) {
            $options[] = [
                'label' => trim($option['html']),
                'is_correct' => strtoupper($option['letter']) === $answerLetter,
                'sort_order' => $index,
            ];
        }

        return [
            'type' => 'mcq_single',
            'subject' => $parsedBlock['subject'] ?? '',
            'topic' => $parsedBlock['topic'] ?? '',
            'content' => $parsedBlock['body_html'] ?? '',
            'marks' => !empty($parsedBlock['marks']) ? (float) $parsedBlock['marks'] : 1.0,
            'negative_marks' => (float) ($parsedBlock['negative'] ?? 0.0),
            'options' => $options,
        ];
    }

    public function validate(array $parsedBlock): array
    {
        $warnings = [];
        $options = $parsedBlock['options'] ?? [];

        if (count($options) < 2) {
            $warnings[] = __('Fewer than two options were detected.', 'wp-cbt-pro');
        }

        $answerLetter = strtoupper(trim((string) ($parsedBlock['answer'] ?? '')));
        $optionLetters = array_map(static fn (array $o): string => strtoupper($o['letter']), $options);

        if ($answerLetter === '') {
            $warnings[] = __('No ANSWER line was found.', 'wp-cbt-pro');
        } elseif (!in_array($answerLetter, $optionLetters, true)) {
            $warnings[] = __('The ANSWER letter does not match any option.', 'wp-cbt-pro');
        }

        if (empty($parsedBlock['marks'])) {
            $warnings[] = __('No MARKS value was found; defaulting to 1.', 'wp-cbt-pro');
        }

        return array_merge($warnings, $parsedBlock['equation_warnings'] ?? []);
    }
}
