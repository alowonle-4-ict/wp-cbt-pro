<?php
/** @var array<int, string>|false $failures */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('Import Candidates', 'wp-cbt-pro'); ?></h1>

    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success banner, not a state change; both values are cast to int below. ?>
    <?php if (isset($_GET['imported'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(sprintf(
                /* translators: 1: number imported, 2: number selected */
                __('Imported %1$d of %2$d selected candidates.', 'wp-cbt-pro'),
                absint($_GET['imported']),
                isset($_GET['total']) ? absint($_GET['total']) : 0
            )); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($failures)): ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Some rows could not be imported:', 'wp-cbt-pro'); ?></p>
            <ul class="wpcbtpro-import-row__warnings">
                <?php foreach ($failures as $failure): ?>
                    <li><?php echo esc_html($failure); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p><?php esc_html_e('Upload a spreadsheet of candidates. Nothing is added until you review and confirm it on the next screen.', 'wp-cbt-pro'); ?></p>

    <details class="wpcbtpro-txt-format-help">
        <summary><?php esc_html_e('Spreadsheet format', 'wp-cbt-pro'); ?></summary>
        <p><?php esc_html_e('The first row must be a header. These columns are recognized (case-insensitive); only First Name and Last Name are required:', 'wp-cbt-pro'); ?></p>
        <ul>
            <li><?php esc_html_e('First Name, Last Name (required)', 'wp-cbt-pro'); ?></li>
            <li><?php esc_html_e('Email, Phone, Department, Class, Registration Number (optional)', 'wp-cbt-pro'); ?></li>
            <li><?php esc_html_e('Password (optional) — when given, a WordPress account is created for the candidate so they can sign in.', 'wp-cbt-pro'); ?></li>
        </ul>
    </details>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('wpcbtpro_candidate_import_upload', 'wpcbtpro_candidate_import_upload_nonce'); ?>
        <p><input type="file" name="wpcbtpro_candidates" accept=".xlsx,.xls,.csv" required></p>
        <?php submit_button(__('Upload & Preview', 'wp-cbt-pro')); ?>
    </form>
</div>
