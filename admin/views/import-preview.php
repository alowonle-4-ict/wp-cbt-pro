<?php
/**
 * @var string $session
 * @var array<int, array<string, mixed>> $rows
 * @var array<string, array<string, bool>> $mathmlAllowedHtml
 * @var array<int, string> $mathmlAllowedProtocols
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('Import Preview', 'wp-cbt-pro'); ?></h1>
    <p><?php esc_html_e('Review each detected question below. Rows with warnings are unchecked by default — fix them in the document and re-upload, or leave them unchecked to skip. Only checked rows enter the question bank.', 'wp-cbt-pro'); ?></p>

    <form method="post">
        <?php wp_nonce_field('wpcbtpro_word_import_confirm_' . $session, 'wpcbtpro_import_confirm_nonce'); ?>
        <input type="hidden" name="session" value="<?php echo esc_attr($session); ?>">

        <?php foreach ($rows as $index => $row): $block = $row['block']; ?>
            <div class="wpcbtpro-import-row<?php echo $row['mapped'] === null ? ' wpcbtpro-import-row--unsupported' : ''; ?>">
                <label class="wpcbtpro-import-row__header">
                    <input
                        type="checkbox"
                        name="rows[]"
                        value="<?php echo esc_attr((string) $index); ?>"
                        <?php checked($row['mapped'] !== null && empty($row['warnings'])); ?>
                        <?php disabled($row['mapped'] === null); ?>
                    >
                    <strong><?php echo esc_html(sprintf(
                        /* translators: %d: question number from the document */
                        __('Question %d', 'wp-cbt-pro'),
                        $block['index']
                    )); ?></strong>
                    <span class="wpcbtpro-import-row__type"><?php echo esc_html($row['type_label'] !== '' ? $row['type_label'] : __('(no type specified)', 'wp-cbt-pro')); ?></span>
                </label>

                <div class="wpcbtpro-import-row__body">
                    <?php echo wp_kses($block['body_html'], $mathmlAllowedHtml, $mathmlAllowedProtocols); ?>
                    <?php if (!empty($block['options'])): ?>
                        <ul class="wpcbtpro-import-row__options">
                            <?php foreach ($block['options'] as $option): ?>
                                <li>
                                    <?php echo esc_html($option['letter']); ?>.
                                    <?php echo wp_kses($option['html'], $mathmlAllowedHtml, $mathmlAllowedProtocols); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <ul class="wpcbtpro-import-row__checks">
                    <li class="<?php echo $row['checks']['options_detected'] ? 'is-ok' : 'is-warn'; ?>">
                        <?php echo $row['checks']['options_detected'] ? '&#10003;' : '&#9888;'; ?>
                        <?php esc_html_e('Options detected', 'wp-cbt-pro'); ?>
                    </li>
                    <li class="<?php echo $row['checks']['answer_detected'] ? 'is-ok' : 'is-warn'; ?>">
                        <?php echo $row['checks']['answer_detected'] ? '&#10003;' : '&#9888;'; ?>
                        <?php esc_html_e('Correct answer detected', 'wp-cbt-pro'); ?>
                    </li>
                    <?php if ($row['checks']['equation_detected']): ?>
                        <li class="is-ok">&#10003; <?php esc_html_e('Equation detected', 'wp-cbt-pro'); ?></li>
                    <?php endif; ?>
                    <li class="<?php echo $row['checks']['marks_detected'] ? 'is-ok' : 'is-warn'; ?>">
                        <?php echo $row['checks']['marks_detected'] ? '&#10003;' : '&#9888;'; ?>
                        <?php esc_html_e('Marks detected', 'wp-cbt-pro'); ?>
                    </li>
                </ul>

                <?php if (!empty($row['warnings'])): ?>
                    <ul class="wpcbtpro-import-row__warnings">
                        <?php foreach ($row['warnings'] as $warning): ?>
                            <li>&#9888; <?php echo esc_html($warning); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php submit_button(__('Import Selected Questions', 'wp-cbt-pro')); ?>
    </form>
</div>
