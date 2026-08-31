<?php
/** @var array<int, string>|false $failures */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('Assign Candidates to Exams', 'wp-cbt-pro'); ?></h1>

    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success banner, not a state change; both values are cast to int below. ?>
    <?php if (isset($_GET['assigned'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(sprintf(
                /* translators: 1: number assigned, 2: number selected */
                __('Assigned %1$d of %2$d selected candidates to their exams.', 'wp-cbt-pro'),
                absint($_GET['assigned']),
                isset($_GET['total']) ? absint($_GET['total']) : 0
            )); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($failures)): ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Some rows could not be assigned:', 'wp-cbt-pro'); ?></p>
            <ul class="wpcbtpro-import-row__warnings">
                <?php foreach ($failures as $failure): ?>
                    <li><?php echo esc_html($failure); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <p><?php esc_html_e('Upload one spreadsheet of candidates for any number of exams at once — each row names its own exam, so there\'s no need to open each exam\'s roster separately. Nothing is added until you review and confirm it on the next screen.', 'wp-cbt-pro'); ?></p>

    <p><strong><?php esc_html_e('Assigning a candidate to an exam this way turns on that exam\'s "Restrict to roster" setting automatically, if it wasn\'t already on — only candidates assigned this way (or added to its roster directly) will then be able to start it.', 'wp-cbt-pro'); ?></strong></p>

    <details class="wpcbtpro-txt-format-help">
        <summary><?php esc_html_e('Spreadsheet format', 'wp-cbt-pro'); ?></summary>
        <p><?php esc_html_e('The first row must be a header. These columns are recognized (case-insensitive):', 'wp-cbt-pro'); ?></p>
        <ul>
            <li><?php esc_html_e('First Name, Last Name (required)', 'wp-cbt-pro'); ?></li>
            <li><?php esc_html_e('Exam (required) — must exactly match an existing exam\'s name, e.g. "Mathematics Mid-Term 2026".', 'wp-cbt-pro'); ?></li>
            <li><?php esc_html_e('Email, Phone, Department, Class, Registration Number (optional)', 'wp-cbt-pro'); ?></li>
            <li><?php esc_html_e('Password (optional) — when given, a WordPress account is created for a newly created candidate so they can sign in.', 'wp-cbt-pro'); ?></li>
        </ul>
        <p><?php esc_html_e('A row matching an existing candidate (by registration number or email) is added to that exam\'s roster without creating a duplicate; otherwise a new candidate is created first.', 'wp-cbt-pro'); ?></p>
    </details>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('wpcbtpro_exam_assignment_upload', 'wpcbtpro_exam_assignment_upload_nonce'); ?>
        <p><input type="file" name="wpcbtpro_assignment" accept=".xlsx,.xls,.csv" required></p>
        <?php submit_button(__('Upload & Preview', 'wp-cbt-pro')); ?>
    </form>
</div>
