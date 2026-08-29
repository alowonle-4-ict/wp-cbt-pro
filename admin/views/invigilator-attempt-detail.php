<?php
/**
 * @var array<string,mixed> $attempt
 * @var array<string,mixed>|null $exam
 * @var array<string,mixed>|null $candidate
 * @var array<int, array<string,mixed>> $events
 * @var bool $canReview
 * @var bool $canManageExam
 * @var array{extra_minutes:int, extra_attempts:int} $override
 */
if (!defined('ABSPATH')) {
    exit;
}

$backUrl = add_query_arg(['page' => 'wpcbtpro-invigilator'], admin_url('admin.php'));
$actionUrl = add_query_arg(['page' => 'wpcbtpro-invigilator', 'attempt_id' => $attempt['id']], admin_url('admin.php'));

$doneMessages = [
    'suspended' => __('Attempt suspended.', 'wp-cbt-pro'),
    'resumed' => __('Attempt resumed.', 'wp-cbt-pro'),
    'time_extended' => __('Extra time added.', 'wp-cbt-pro'),
    'attempt_granted' => __('An extra attempt was granted for this exam.', 'wp-cbt-pro'),
];
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which success notice to show, not a state change.
$done = isset($_GET['done']) ? sanitize_key($_GET['done']) : '';
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('Monitoring Log', 'wp-cbt-pro'); ?></h1>
    <p><a href="<?php echo esc_url($backUrl); ?>">&larr; <?php esc_html_e('Back to dashboard', 'wp-cbt-pro'); ?></a></p>

    <?php if ($done !== '' && isset($doneMessages[$done])): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($doneMessages[$done]); ?></p></div>
    <?php endif; ?>

    <?php if ($candidate !== null && $exam !== null): ?>
        <p>
            <strong><?php echo esc_html(trim($candidate['first_name'] . ' ' . $candidate['last_name'])); ?></strong>
            (<?php echo esc_html($candidate['candidate_ref']); ?>)
            &middot; <?php echo esc_html($exam['name']); ?>
            &middot; <?php echo esc_html(ucfirst($attempt['status'])); ?>
        </p>

        <?php if (in_array($attempt['status'], ['in_progress', 'paused'], true) && ($canReview || $canManageExam)): ?>
        <div class="wpcbtpro-attempt-actions">
            <h2><?php esc_html_e('Actions', 'wp-cbt-pro'); ?></h2>

            <?php if ($canReview): ?>
                <?php if ($attempt['status'] === 'in_progress'): ?>
                    <form method="post" action="<?php echo esc_url($actionUrl); ?>" style="display:inline-block">
                        <?php wp_nonce_field('wpcbtpro_suspend_attempt_' . $attempt['id']); ?>
                        <input type="hidden" name="wpcbtpro_action" value="suspend">
                        <input type="hidden" name="attempt_id" value="<?php echo esc_attr((string) $attempt['id']); ?>">
                        <button type="submit" class="button"><?php esc_html_e('Suspend attempt', 'wp-cbt-pro'); ?></button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?php echo esc_url($actionUrl); ?>" style="display:inline-block">
                        <?php wp_nonce_field('wpcbtpro_resume_attempt_' . $attempt['id']); ?>
                        <input type="hidden" name="wpcbtpro_action" value="resume">
                        <input type="hidden" name="attempt_id" value="<?php echo esc_attr((string) $attempt['id']); ?>">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Resume attempt', 'wp-cbt-pro'); ?></button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($canManageExam): ?>
                <form method="post" action="<?php echo esc_url($actionUrl); ?>" style="display:inline-block; margin-left: 1em;">
                    <?php wp_nonce_field('wpcbtpro_extend_time_' . $attempt['id']); ?>
                    <input type="hidden" name="wpcbtpro_action" value="extend_time">
                    <input type="hidden" name="attempt_id" value="<?php echo esc_attr((string) $attempt['id']); ?>">
                    <label>
                        <?php esc_html_e('Add minutes:', 'wp-cbt-pro'); ?>
                        <input type="number" min="1" step="1" name="extra_minutes" value="15" class="small-text">
                    </label>
                    <button type="submit" class="button"><?php esc_html_e('Extend time', 'wp-cbt-pro'); ?></button>
                </form>

                <form method="post" action="<?php echo esc_url($actionUrl); ?>" style="display:inline-block; margin-left: 1em;">
                    <?php wp_nonce_field('wpcbtpro_grant_attempt_' . $attempt['id']); ?>
                    <input type="hidden" name="wpcbtpro_action" value="grant_attempt">
                    <input type="hidden" name="attempt_id" value="<?php echo esc_attr((string) $attempt['id']); ?>">
                    <button type="submit" class="button"><?php esc_html_e('Grant an extra attempt', 'wp-cbt-pro'); ?></button>
                </form>

                <?php if ($override['extra_minutes'] > 0 || $override['extra_attempts'] > 0): ?>
                    <p class="description">
                        <?php echo esc_html(sprintf(
                            /* translators: 1: extra minutes granted so far, 2: extra attempts granted so far */
                            __('This candidate already has +%1$d minute(s) and +%2$d attempt(s) granted for this exam.', 'wp-cbt-pro'),
                            $override['extra_minutes'],
                            $override['extra_attempts']
                        )); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($events)): ?>
        <p><em><?php esc_html_e('No monitoring events have been recorded for this attempt.', 'wp-cbt-pro'); ?></em></p>
    <?php else: ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Time', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Event', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Details', 'wp-cbt-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_reverse($events) as $event): ?>
            <tr>
                <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $event['created_at'])); ?></td>
                <td><code><?php echo esc_html($event['event_type']); ?></code></td>
                <td><?php echo esc_html($event['payload'] ? wp_json_encode(json_decode($event['payload'], true)) : ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
