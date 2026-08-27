<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Mcq;

use WPCBTPro\Questions\Contracts\AdminEditorView;

final class SingleChoiceAdminEditor implements AdminEditorView
{
    private int $rowCount = 6;

    public function render(?array $question, array $errors): void
    {
        $options = $question['options'] ?? [];
        $correctIndex = null;
        foreach ($options as $index => $option) {
            if (!empty($option['is_correct'])) {
                $correctIndex = $index;
                break;
            }
        }
        ?>
        <tr>
            <th><?php esc_html_e('Options', 'wp-cbt-pro'); ?></th>
            <td>
                <?php if (!empty($errors['options'])): ?>
                    <p class="wpcbtpro-field-error"><?php echo esc_html($errors['options']); ?></p>
                <?php endif; ?>
                <?php for ($i = 0; $i < $this->rowCount; $i++): ?>
                    <p class="wpcbtpro-option-row">
                        <input type="radio" name="options_correct" value="<?php echo esc_attr((string) $i); ?>"
                            <?php checked($correctIndex, $i); ?>
                            aria-label="<?php esc_attr_e('Correct answer', 'wp-cbt-pro'); ?>">
                        <input type="text" name="options_label[]" class="regular-text"
                            value="<?php echo esc_attr($options[$i]['label'] ?? ''); ?>"
                            placeholder="<?php echo esc_attr(sprintf(__('Option %d', 'wp-cbt-pro'), $i + 1)); ?>">
                    </p>
                <?php endfor; ?>
                <p class="description"><?php esc_html_e('Leave a row blank to omit it. Mark the radio button next to the correct option.', 'wp-cbt-pro'); ?></p>
            </td>
        </tr>
        <?php
    }

    public function extract(array $postData): array
    {
        $labels = $postData['options_label'] ?? [];
        $correctIndex = isset($postData['options_correct']) ? (int) $postData['options_correct'] : null;

        $options = [];
        foreach ($labels as $index => $label) {
            $label = trim(sanitize_text_field($label));
            if ($label === '') {
                continue;
            }
            $options[] = [
                'label' => $label,
                'is_correct' => $correctIndex === (int) $index,
                'sort_order' => count($options),
            ];
        }

        return ['options' => $options];
    }
}
