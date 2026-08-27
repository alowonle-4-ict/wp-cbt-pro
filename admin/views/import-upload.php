<?php
/** @var string $templateUrl */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('Import Questions from Word', 'wp-cbt-pro'); ?></h1>

    <?php if (isset($_GET['imported'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(sprintf(
                /* translators: 1: number imported, 2: number selected */
                __('Imported %1$d of %2$d selected questions into the question bank.', 'wp-cbt-pro'),
                (int) $_GET['imported'],
                (int) ($_GET['total'] ?? 0)
            )); ?></p>
        </div>
    <?php endif; ?>

    <p><?php esc_html_e('Upload a .docx file built from the question template below. Nothing is added to the question bank until you review and confirm it on the next screen.', 'wp-cbt-pro'); ?></p>

    <p>
        <a href="<?php echo esc_url($templateUrl); ?>" class="button">
            <?php esc_html_e('Download Question Template (.docx)', 'wp-cbt-pro'); ?>
        </a>
    </p>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('wpcbtpro_word_import_upload', 'wpcbtpro_import_upload_nonce'); ?>
        <p><input type="file" name="wpcbtpro_docx" accept=".docx" required></p>
        <?php submit_button(__('Upload & Preview', 'wp-cbt-pro')); ?>
    </form>
</div>
