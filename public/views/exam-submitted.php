<?php
/**
 * @var array<string,mixed> $exam
 * @var array<string,mixed> $attempt
 * @var array<string,mixed>|null $result
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wpcbtpro-exam wpcbtpro-exam--submitted">
    <h2><?php echo esc_html($exam['name']); ?></h2>
    <p class="wpcbtpro-notice wpcbtpro-notice--success"><?php esc_html_e('Your exam has been submitted.', 'wp-cbt-pro'); ?></p>

    <?php if ($result !== null && !empty($result['released_at'])): ?>
        <?php if (($result['status'] ?? 'final') === 'provisional'): ?>
            <div class="wpcbtpro-notice">
                <?php echo esc_html(sprintf(
                    /* translators: %d: number of questions still awaiting grading */
                    _n(
                        '%d question still needs to be graded before your final result is ready.',
                        '%d questions still need to be graded before your final result is ready.',
                        (int) $result['pending_review_count'],
                        'wp-cbt-pro'
                    ),
                    (int) $result['pending_review_count']
                )); ?>
            </div>
        <?php endif; ?>
        <div class="wpcbtpro-result">
            <div class="wpcbtpro-result__score"><?php echo esc_html((string) $result['percentage']); ?>%<?php if (($result['status'] ?? 'final') === 'provisional'): ?><span class="wpcbtpro-result__provisional"> (<?php esc_html_e('provisional', 'wp-cbt-pro'); ?>)</span><?php endif; ?></div>
            <table class="wpcbtpro-result__table">
                <tr><th><?php esc_html_e('Score', 'wp-cbt-pro'); ?></th><td><?php echo esc_html($result['score']); ?></td></tr>
                <?php if (!empty($result['grade'])): ?>
                    <tr><th><?php esc_html_e('Grade', 'wp-cbt-pro'); ?></th><td><?php echo esc_html((string) $result['grade']); ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($result['pass_status'])): ?>
                    <tr><th><?php esc_html_e('Result', 'wp-cbt-pro'); ?></th><td><?php echo esc_html(ucfirst($result['pass_status'])); ?></td></tr>
                <?php endif; ?>
                <tr><th><?php esc_html_e('Correct', 'wp-cbt-pro'); ?></th><td><?php echo esc_html((string) $result['correct_count']); ?></td></tr>
                <tr><th><?php esc_html_e('Incorrect', 'wp-cbt-pro'); ?></th><td><?php echo esc_html((string) $result['incorrect_count']); ?></td></tr>
                <tr><th><?php esc_html_e('Unanswered', 'wp-cbt-pro'); ?></th><td><?php echo esc_html((string) $result['unanswered_count']); ?></td></tr>
                <?php if (!empty($result['pending_review_count'])): ?>
                    <tr><th><?php esc_html_e('Pending grading', 'wp-cbt-pro'); ?></th><td><?php echo esc_html((string) $result['pending_review_count']); ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    <?php else: ?>
        <p class="wpcbtpro-notice"><?php esc_html_e('Your result will be released by your institution.', 'wp-cbt-pro'); ?></p>
    <?php endif; ?>

    <p class="wpcbtpro-submitted__logout">
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="wpcbtpro-btn"><?php esc_html_e('Log out', 'wp-cbt-pro'); ?></a>
    </p>
</div>
