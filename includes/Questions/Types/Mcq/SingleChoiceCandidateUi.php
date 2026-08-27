<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Mcq;

use WPCBTPro\Questions\Contracts\CandidateUiView;

final class SingleChoiceCandidateUi implements CandidateUiView
{
    public function render(array $question, ?string $currentAnswer): void
    {
        // A fixed field name works because the exam runtime shows one
        // question per page/request — the accompanying hidden question_id
        // field is what ties this value back to the right question.
        ?>
        <fieldset class="wpcbtpro-mcq-options">
            <legend class="screen-reader-text"><?php esc_html_e('Choose one answer', 'wp-cbt-pro'); ?></legend>
            <?php foreach ($question['options'] ?? [] as $option): ?>
                <label class="wpcbtpro-mcq-option">
                    <input
                        type="radio"
                        name="wpcbtpro_answer"
                        value="<?php echo esc_attr((string) $option['id']); ?>"
                        <?php checked((string) $option['id'], (string) $currentAnswer); ?>
                    >
                    <span><?php echo wp_kses_post($option['label']); ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php
    }
}
