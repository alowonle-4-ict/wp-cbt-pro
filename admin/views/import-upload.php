<?php
/** @var string $templateUrl */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('Import Questions', 'wp-cbt-pro'); ?></h1>

    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success banner, not a state change; both values are cast to int below. ?>
    <?php if (isset($_GET['imported'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(sprintf(
                /* translators: 1: number imported, 2: number selected */
                __('Imported %1$d of %2$d selected questions into the question bank.', 'wp-cbt-pro'),
                absint($_GET['imported']),
                isset($_GET['total']) ? absint($_GET['total']) : 0
            )); ?></p>
        </div>
    <?php endif; ?>

    <p><?php esc_html_e('Upload a .docx file built from the question template below, or a plain .txt file (MCQ only). Nothing is added to the question bank until you review and confirm it on the next screen.', 'wp-cbt-pro'); ?></p>

    <p>
        <a href="<?php echo esc_url($templateUrl); ?>" class="button">
            <?php esc_html_e('Download Question Template (.docx)', 'wp-cbt-pro'); ?>
        </a>
    </p>

    <details class="wpcbtpro-txt-format-help">
        <summary><?php esc_html_e('Plain .txt format', 'wp-cbt-pro'); ?></summary>
        <p><?php esc_html_e('Separate each question from the next with a blank line. Only MCQ is supported in this format.', 'wp-cbt-pro'); ?></p>
        <pre>What is your name?
A. Ade
B. Kunle
C. Dada
D. Abdul
ANSWER: B

What is your name?
A. Ade
B. Kunle
C. Dada
D. Abdul
ANSWER: A</pre>
        <p><?php esc_html_e('SUBJECT:, TOPIC:, MARKS:, and NEGATIVE: lines are recognized too, if you want them — none are required.', 'wp-cbt-pro'); ?></p>
    </details>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('wpcbtpro_word_import_upload', 'wpcbtpro_import_upload_nonce'); ?>
        <p><input type="file" name="wpcbtpro_docx" accept=".docx,.txt" required></p>
        <?php submit_button(__('Upload & Preview', 'wp-cbt-pro')); ?>
    </form>
</div>
