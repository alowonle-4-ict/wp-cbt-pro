<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Programming;

use WPCBTPro\Programming\Registry\LanguageRegistry;
use WPCBTPro\Questions\Contracts\AdminEditorView;

final class ProgrammingAdminEditor implements AdminEditorView
{
    private const TEST_CASE_ROWS = 10;

    public function __construct(private readonly LanguageRegistry $languages)
    {
    }

    public function render(?array $question, array $errors): void
    {
        $programming = $question['programming'] ?? [];
        $testCases = $programming['test_cases'] ?? [];
        ?>
        <tr>
            <th><label for="language"><?php esc_html_e('Language', 'wp-cbt-pro'); ?></label></th>
            <td>
                <select id="language" name="language">
                    <?php foreach ($this->languages->all() as $language): ?>
                        <option value="<?php echo esc_attr($language->id()); ?>" <?php selected($programming['language'] ?? '', $language->id()); ?>>
                            <?php echo esc_html($language->displayName()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="entry_point"><?php esc_html_e('Entry point', 'wp-cbt-pro'); ?></label></th>
            <td>
                <input type="text" id="entry_point" name="entry_point" class="regular-text" value="<?php echo esc_attr($programming['entry_point'] ?? ''); ?>" placeholder="main / solve / Solution.run">
            </td>
        </tr>
        <tr>
            <th><label for="starter_code"><?php esc_html_e('Starter code', 'wp-cbt-pro'); ?></label></th>
            <td><textarea id="starter_code" name="starter_code" rows="10" class="large-text code"><?php echo esc_textarea($programming['starter_code'] ?? ''); ?></textarea></td>
        </tr>
        <tr>
            <th><label for="time_limit_ms"><?php esc_html_e('Time limit (ms)', 'wp-cbt-pro'); ?></label></th>
            <td><input type="number" min="100" id="time_limit_ms" name="time_limit_ms" value="<?php echo esc_attr((string) ($programming['time_limit_ms'] ?? 2000)); ?>"></td>
        </tr>
        <tr>
            <th><label for="memory_limit_mb"><?php esc_html_e('Memory limit (MB)', 'wp-cbt-pro'); ?></label></th>
            <td><input type="number" min="16" id="memory_limit_mb" name="memory_limit_mb" value="<?php echo esc_attr((string) ($programming['memory_limit_mb'] ?? 128)); ?>"></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Test cases', 'wp-cbt-pro'); ?></th>
            <td>
                <?php if (!empty($errors['test_cases'])): ?>
                    <p class="wpcbtpro-field-error"><?php echo esc_html($errors['test_cases']); ?></p>
                <?php endif; ?>
                <table class="wpcbtpro-test-case-editor">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Input', 'wp-cbt-pro'); ?></th>
                            <th><?php esc_html_e('Expected output', 'wp-cbt-pro'); ?></th>
                            <th><?php esc_html_e('Weight', 'wp-cbt-pro'); ?></th>
                            <th><?php esc_html_e('Hidden', 'wp-cbt-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < self::TEST_CASE_ROWS; $i++):
                            $tc = $testCases[$i] ?? ['input' => '', 'expected_output' => '', 'weight' => 1, 'is_hidden' => $i >= 2];
                        ?>
                            <tr>
                                <td><textarea name="test_input[]" rows="2"><?php echo esc_textarea($tc['input'] ?? ''); ?></textarea></td>
                                <td><textarea name="test_output[]" rows="2"><?php echo esc_textarea($tc['expected_output'] ?? ''); ?></textarea></td>
                                <td><input type="number" step="0.1" min="0" name="test_weight[]" value="<?php echo esc_attr((string) ($tc['weight'] ?? 1)); ?>" class="small-text"></td>
                                <td><input type="checkbox" name="test_hidden[<?php echo (int) $i; ?>]" value="1" <?php checked(!empty($tc['is_hidden'])); ?>></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                <p class="description"><?php esc_html_e('Leave both Input and Expected output blank to omit a row. Hidden test cases are never shown to candidates (§18).', 'wp-cbt-pro'); ?></p>
            </td>
        </tr>
        <?php
    }

    public function extract(array $postData): array
    {
        $inputs = $postData['test_input'] ?? [];
        $outputs = $postData['test_output'] ?? [];
        $weights = $postData['test_weight'] ?? [];
        $hiddenFlags = $postData['test_hidden'] ?? [];

        $testCases = [];
        foreach ($inputs as $index => $input) {
            $expectedOutput = $outputs[$index] ?? '';
            if (trim($input) === '' && trim($expectedOutput) === '') {
                continue;
            }

            $testCases[] = [
                'input' => sanitize_textarea_field($input),
                'expected_output' => sanitize_textarea_field($expectedOutput),
                'weight' => (float) ($weights[$index] ?? 1),
                'is_hidden' => !empty($hiddenFlags[$index]),
            ];
        }

        return [
            'programming' => [
                'language' => sanitize_key($postData['language'] ?? 'python3'),
                'entry_point' => sanitize_text_field($postData['entry_point'] ?? ''),
                'starter_code' => wp_unslash($postData['starter_code'] ?? ''),
                'time_limit_ms' => max(100, (int) ($postData['time_limit_ms'] ?? 2000)),
                'memory_limit_mb' => max(16, (int) ($postData['memory_limit_mb'] ?? 128)),
                'test_cases' => $testCases,
            ],
        ];
    }
}
