<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Mcq;

use WPCBTPro\Questions\Contracts\AdminEditorView;

/**
 * True/False is a single-choice question with two fixed options — the admin
 * only ever picks which one is correct, never edits the option text.
 */
final class TrueFalseAdminEditor implements AdminEditorView
{
    public function render(?array $question, array $errors): void
    {
        $correctIsTrue = true;
        foreach ($question['options'] ?? [] as $option) {
            if (!empty($option['is_correct'])) {
                $correctIsTrue = strtolower($option['label']) === 'true';
                break;
            }
        }
        ?>
        <tr>
            <th><?php esc_html_e('Correct answer', 'wp-cbt-pro'); ?></th>
            <td>
                <label>
                    <input type="radio" name="true_false_answer" value="true" <?php checked($correctIsTrue); ?>>
                    <?php esc_html_e('True', 'wp-cbt-pro'); ?>
                </label>
                &nbsp;&nbsp;
                <label>
                    <input type="radio" name="true_false_answer" value="false" <?php checked(!$correctIsTrue); ?>>
                    <?php esc_html_e('False', 'wp-cbt-pro'); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    public function extract(array $postData): array
    {
        $answerIsTrue = ($postData['true_false_answer'] ?? 'true') === 'true';

        return [
            'options' => [
                ['label' => __('True', 'wp-cbt-pro'), 'is_correct' => $answerIsTrue, 'sort_order' => 0],
                ['label' => __('False', 'wp-cbt-pro'), 'is_correct' => !$answerIsTrue, 'sort_order' => 1],
            ],
        ];
    }
}
