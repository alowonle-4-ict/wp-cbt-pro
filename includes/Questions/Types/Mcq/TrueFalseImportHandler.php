<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Mcq;

use WPCBTPro\Questions\Contracts\ImportHandler;

final class TrueFalseImportHandler implements ImportHandler
{
    private const TRUE_WORDS = ['TRUE', 'YES', 'T', 'Y'];
    private const RECOGNIZED_WORDS = ['TRUE', 'FALSE', 'YES', 'NO', 'T', 'F', 'Y', 'N'];

    public function mapToQuestionData(array $parsedBlock): array
    {
        $answer = strtoupper(trim((string) ($parsedBlock['answer'] ?? '')));
        $isTrue = in_array($answer, self::TRUE_WORDS, true);

        return [
            'type' => 'true_false',
            'subject' => $parsedBlock['subject'] ?? '',
            'topic' => $parsedBlock['topic'] ?? '',
            'content' => $parsedBlock['body_html'] ?? '',
            'marks' => !empty($parsedBlock['marks']) ? (float) $parsedBlock['marks'] : 1.0,
            'negative_marks' => (float) ($parsedBlock['negative'] ?? 0.0),
            'options' => [
                ['label' => __('True', 'wp-cbt-pro'), 'is_correct' => $isTrue, 'sort_order' => 0],
                ['label' => __('False', 'wp-cbt-pro'), 'is_correct' => !$isTrue, 'sort_order' => 1],
            ],
        ];
    }

    public function validate(array $parsedBlock): array
    {
        $warnings = [];
        $answer = strtoupper(trim((string) ($parsedBlock['answer'] ?? '')));

        if (!in_array($answer, self::RECOGNIZED_WORDS, true)) {
            $warnings[] = __('ANSWER should be TRUE or FALSE for this question type.', 'wp-cbt-pro');
        }

        if (empty($parsedBlock['marks'])) {
            $warnings[] = __('No MARKS value was found; defaulting to 1.', 'wp-cbt-pro');
        }

        return array_merge($warnings, $parsedBlock['equation_warnings'] ?? []);
    }
}
