<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Dsa;

use WPCBTPro\DSA\OperationParser;
use WPCBTPro\DSA\Registry\StructureRegistry;
use WPCBTPro\Questions\Contracts\AdminEditorView;

final class DsaAdminEditor implements AdminEditorView
{
    public function __construct(
        private readonly StructureRegistry $structures,
        private readonly OperationParser $operationParser,
    ) {
    }

    public function render(?array $question, array $errors): void
    {
        $dsa = $question['dsa'] ?? [];
        $selectedStructure = $dsa['structure'] ?? array_key_first($this->structures->all());
        $operationsText = !empty($dsa['operations']) ? $this->operationParser->format($dsa['operations']) : '';
        ?>
        <tr>
            <th><label for="structure"><?php esc_html_e('Structure', 'wp-cbt-pro'); ?></label></th>
            <td>
                <select id="structure" name="structure">
                    <?php foreach ($this->structures->all() as $structure): ?>
                        <option value="<?php echo esc_attr($structure->id()); ?>" <?php selected($selectedStructure, $structure->id()); ?>>
                            <?php echo esc_html($structure->label()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="mode"><?php esc_html_e('Mode', 'wp-cbt-pro'); ?></label></th>
            <td>
                <select id="mode" name="mode">
                    <option value="simulation" <?php selected($dsa['mode'] ?? 'simulation', 'simulation'); ?>><?php esc_html_e('Simulation (candidate types the final state)', 'wp-cbt-pro'); ?></option>
                    <option value="interactive" <?php selected($dsa['mode'] ?? '', 'interactive'); ?>><?php esc_html_e('Interactive (candidate builds the structure)', 'wp-cbt-pro'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="operations"><?php esc_html_e('Operations', 'wp-cbt-pro'); ?></label></th>
            <td>
                <?php if (!empty($errors['operations'])): ?>
                    <p class="wpcbtpro-field-error"><?php echo esc_html($errors['operations']); ?></p>
                <?php endif; ?>
                <textarea id="operations" name="operations" rows="8" class="large-text code" placeholder="PUSH(10)&#10;PUSH(20)&#10;POP()"><?php echo esc_textarea($operationsText); ?></textarea>
                <p class="description">
                    <?php esc_html_e('One instruction per line, starting from an empty structure. Allowed operations depend on the selected structure — e.g. PUSH(n) / POP() for a stack.', 'wp-cbt-pro'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * @throws \InvalidArgumentException if the operations text doesn't parse against the chosen structure
     */
    public function extract(array $postData): array
    {
        $structureId = sanitize_key($postData['structure'] ?? '');
        $structure = $this->structures->get($structureId);
        $mode = in_array($postData['mode'] ?? '', ['simulation', 'interactive'], true) ? $postData['mode'] : 'simulation';

        if ($mode === 'interactive' && !$structure->supportsInteractive()) {
            $mode = 'simulation';
        }

        $operations = $this->operationParser->parse((string) ($postData['operations'] ?? ''), $structure);

        return [
            'dsa' => [
                'structure' => $structureId,
                'mode' => $mode,
                'operations' => $operations,
            ],
        ];
    }
}
