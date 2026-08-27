<?php
/** @var array<int, array<string, mixed>> $rows */
if (!defined('ABSPATH')) {
    exit;
}

$cameraLabels = [
    'not_started' => __('Not started', 'wp-cbt-pro'),
    'requesting' => __('Requesting…', 'wp-cbt-pro'),
    'connected' => __('OK', 'wp-cbt-pro'),
    'disconnected' => __('Disconnected', 'wp-cbt-pro'),
    'blocked' => __('Blocked', 'wp-cbt-pro'),
    'paused' => __('Paused', 'wp-cbt-pro'),
    'terminated' => __('Terminated', 'wp-cbt-pro'),
];
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('Invigilator Dashboard', 'wp-cbt-pro'); ?></h1>
    <p class="description"><?php esc_html_e('This page refreshes automatically every 30 seconds.', 'wp-cbt-pro'); ?></p>

    <?php if (empty($rows)): ?>
        <p><em><?php esc_html_e('No candidates are currently sitting an exam.', 'wp-cbt-pro'); ?></em></p>
    <?php else: ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Candidate', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Exam', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Time remaining', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Progress', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Camera', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Last activity', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Status', 'wp-cbt-pro'); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row):
                $candidate = $row['candidate'];
                $photoId = (int) ($candidate['photo_attachment_id'] ?? 0);
                $minutes = (int) floor($row['time_remaining_seconds'] / 60);
                $seconds = $row['time_remaining_seconds'] % 60;
                $cameraState = $row['camera_state'];
                $detailUrl = add_query_arg(['page' => 'wpcbtpro-invigilator', 'attempt_id' => $row['attempt']['id']], admin_url('admin.php'));
            ?>
            <tr>
                <td>
                    <div class="wpcbtpro-candidate-inline">
                        <?php if ($photoId): ?>
                            <?php echo wp_get_attachment_image($photoId, [32, 32], false, ['class' => 'wpcbtpro-thumb']); ?>
                        <?php else: ?>
                            <span class="wpcbtpro-thumb wpcbtpro-thumb--placeholder"></span>
                        <?php endif; ?>
                        <span>
                            <?php echo esc_html(trim($candidate['first_name'] . ' ' . $candidate['last_name'])); ?><br>
                            <small><?php echo esc_html($candidate['candidate_ref']); ?></small>
                        </span>
                    </div>
                </td>
                <td><?php echo esc_html($row['exam']['name']); ?></td>
                <td class="num"><?php echo esc_html(sprintf('%02d:%02d', $minutes, $seconds)); ?></td>
                <td><?php echo esc_html(sprintf('%d / %d', $row['answered'], $row['total'])); ?></td>
                <td>
                    <?php if ($row['alert_count'] > 0): ?>
                        <span class="wpcbtpro-pill wpcbtpro-pill--suspended">&#9888; <?php echo esc_html((string) $row['alert_count']); ?></span>
                    <?php elseif ($cameraState === 'connected'): ?>
                        <span class="wpcbtpro-pill wpcbtpro-pill--active">&#9679; <?php esc_html_e('OK', 'wp-cbt-pro'); ?></span>
                    <?php else: ?>
                        <span class="wpcbtpro-pill"><?php echo esc_html($cameraLabels[$cameraState] ?? __('N/A', 'wp-cbt-pro')); ?></span>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html(human_time_diff(strtotime($row['last_activity'])) . ' ' . __('ago', 'wp-cbt-pro')); ?></td>
                <td>
                    <span class="wpcbtpro-pill <?php echo $row['attempt']['status'] === 'paused' ? 'wpcbtpro-pill--suspended' : 'wpcbtpro-pill--active'; ?>">
                        <?php echo esc_html(ucfirst($row['attempt']['status'])); ?>
                    </span>
                </td>
                <td><a href="<?php echo esc_url($detailUrl); ?>"><?php esc_html_e('View log', 'wp-cbt-pro'); ?></a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<script>
    setTimeout(function () { window.location.reload(); }, 30000);
</script>
