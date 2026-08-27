<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Programming;

use WPCBTPro\Programming\Registry\LanguageRegistry;
use WPCBTPro\Questions\Contracts\CandidateUiView;

/**
 * Monaco is a text editor here, nothing more (§19). There is no execute
 * button, no keyboard shortcut, and no code path anywhere in this class
 * that runs candidate code in the browser — code only ever leaves as the
 * value of the hidden textarea, through the exact same autosave/submit
 * pipeline every other question type uses.
 */
final class ProgrammingCandidateUi implements CandidateUiView
{
    public function __construct(private readonly LanguageRegistry $languages)
    {
    }

    public function render(array $question, ?string $currentAnswer): void
    {
        $programming = $question['programming'] ?? [];
        $language = $programming['language'] ?? 'plaintext';
        $monacoLanguage = $this->languages->has($language) ? $this->languages->get($language)->monacoLanguageId() : 'plaintext';
        $starterCode = $programming['starter_code'] ?? '';
        $value = $currentAnswer !== null && $currentAnswer !== '' ? $currentAnswer : $starterCode;
        $editorId = 'wpcbtpro-monaco-' . (int) $question['id'];
        $visibleTestCases = array_values(array_filter($programming['test_cases'] ?? [], static fn (array $tc): bool => empty($tc['is_hidden'])));
        ?>
        <div class="wpcbtpro-code-question" data-wpcbtpro-code-editor data-monaco-language="<?php echo esc_attr($monacoLanguage); ?>">
            <div id="<?php echo esc_attr($editorId); ?>" class="wpcbtpro-monaco-container" data-wpcbtpro-monaco-target></div>
            <textarea
                name="wpcbtpro_answer"
                class="wpcbtpro-hidden"
                data-wpcbtpro-code-source
            ><?php echo esc_textarea($value); ?></textarea>
            <p class="wpcbtpro-code-note"><?php esc_html_e('Your code is saved automatically as you type. It will be compiled and run only after you submit the exam.', 'wp-cbt-pro'); ?></p>
        </div>

        <?php if (!empty($visibleTestCases)): ?>
            <div class="wpcbtpro-test-cases">
                <h4><?php esc_html_e('Example test cases', 'wp-cbt-pro'); ?></h4>
                <table class="wpcbtpro-test-cases__table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Input', 'wp-cbt-pro'); ?></th>
                            <th><?php esc_html_e('Expected output', 'wp-cbt-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visibleTestCases as $testCase): ?>
                            <tr>
                                <td><pre><?php echo esc_html((string) $testCase['input']); ?></pre></td>
                                <td><pre><?php echo esc_html((string) $testCase['expected_output']); ?></pre></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php
    }
}
