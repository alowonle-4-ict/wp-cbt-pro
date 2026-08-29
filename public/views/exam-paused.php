<?php
/**
 * @var array<string,mixed> $exam
 * @var array<string,mixed> $candidate
 * @var array<string,mixed> $attempt
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<meta http-equiv="refresh" content="15">
<div class="wpcbtpro-exam wpcbtpro-exam--paused">
    <h2><?php echo esc_html($exam['name']); ?></h2>
    <div class="wpcbtpro-notice">
        <?php esc_html_e('Your exam has been paused by your invigilator. Please wait — this page will refresh automatically, or you can reload it yourself once you\'re told to continue.', 'wp-cbt-pro'); ?>
    </div>
    <p class="wpcbtpro-notice wpcbtpro-notice--error">
        <?php esc_html_e('The exam clock keeps running while paused — resume as soon as you\'re able to.', 'wp-cbt-pro'); ?>
    </p>
</div>
