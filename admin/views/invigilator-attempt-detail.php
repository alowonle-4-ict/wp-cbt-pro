<?php
/**
 * @var array<string,mixed> $attempt
 * @var array<string,mixed>|null $exam
 * @var array<string,mixed>|null $candidate
 * @var array<int, array<string,mixed>> $events
 */
if (!defined('ABSPATH')) {
    exit;
}

$backUrl = add_query_arg(['page' => 'wpcbtpro-invigilator'], admin_url('admin.php'));
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('Monitoring Log', 'wp-cbt-pro'); ?></h1>
    <p><a href="<?php echo esc_url($backUrl); ?>">&larr; <?php esc_html_e('Back to dashboard', 'wp-cbt-pro'); ?></a></p>

    <?php if ($candidate !== null && $exam !== null): ?>
        <p>
            <strong><?php echo esc_html(trim($candidate['first_name'] . ' ' . $candidate['last_name'])); ?></strong>
            (<?php echo esc_html($candidate['candidate_ref']); ?>)
            &middot; <?php echo esc_html($exam['name']); ?>
            &middot; <?php echo esc_html(ucfirst($attempt['status'])); ?>
        </p>
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
