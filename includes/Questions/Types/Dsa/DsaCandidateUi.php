<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Dsa;

use WPCBTPro\DSA\Registry\StructureRegistry;
use WPCBTPro\Questions\Contracts\CandidateUiView;

final class DsaCandidateUi implements CandidateUiView
{
    public function __construct(private readonly StructureRegistry $structures)
    {
    }

    public function render(array $question, ?string $currentAnswer): void
    {
        $dsa = $question['dsa'] ?? null;
        if ($dsa === null || !$this->structures->has($dsa['structure'])) {
            echo '<p class="wpcbtpro-notice wpcbtpro-notice--error">' . esc_html__('This question is not configured correctly.', 'wp-cbt-pro') . '</p>';
            return;
        }

        $structure = $this->structures->get($dsa['structure']);
        ?>
        <div class="wpcbtpro-dsa-operations">
            <h4><?php esc_html_e('Starting from an empty structure, apply:', 'wp-cbt-pro'); ?></h4>
            <ol>
                <?php foreach ($dsa['operations'] as $operation): ?>
                    <li><code><?php echo esc_html($operation['op'] . '(' . ($operation['arg'] ?? '') . ')'); ?></code></li>
                <?php endforeach; ?>
            </ol>
        </div>

        <?php if ($dsa['mode'] === 'interactive'): ?>
            <div
                class="wpcbtpro-dsa-widget"
                data-wpcbtpro-dsa-widget
                data-widget-type="<?php echo esc_attr($dsa['structure'] === 'bst' ? 'tree' : 'sequence'); ?>"
                data-operations="<?php echo esc_attr(wp_json_encode($structure->allowedOperations())); ?>"
            >
                <div class="wpcbtpro-dsa-widget__canvas" data-wpcbtpro-dsa-canvas></div>
                <div class="wpcbtpro-dsa-widget__controls" data-wpcbtpro-dsa-controls></div>
                <textarea name="wpcbtpro_answer" class="wpcbtpro-hidden" data-wpcbtpro-dsa-source><?php echo esc_textarea($currentAnswer ?? ''); ?></textarea>
            </div>
        <?php else: ?>
            <p>
                <label for="wpcbtpro-dsa-answer-<?php echo esc_attr((string) $question['id']); ?>">
                    <?php esc_html_e('Final state (comma-separated, front-to-back):', 'wp-cbt-pro'); ?>
                </label>
            </p>
            <input
                type="text"
                id="wpcbtpro-dsa-answer-<?php echo esc_attr((string) $question['id']); ?>"
                name="wpcbtpro_answer"
                class="wpcbtpro-dsa-text-answer"
                value="<?php echo esc_attr($currentAnswer ?? ''); ?>"
                placeholder="e.g. 10, 30, 40"
            >
        <?php endif; ?>
        <?php
    }
}
