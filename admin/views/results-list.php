<?php
/**
 * @var array<int, array<string, mixed>> $exams
 * @var array<string,mixed>|null $exam
 * @var int $examId
 * @var array<int, array{result: array, candidate: array}> $rows
 * @var array<string,mixed>|null $analytics
 * @var bool $canRelease
 * @var string $csvUrl
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap wpcbtpro-results">
    <h1><?php esc_html_e('Results', 'wp-cbt-pro'); ?></h1>

    <?php if (isset($_GET['released'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(sprintf(
                /* translators: %d: number of results released */
                _n('%d result released.', '%d results released.', (int) $_GET['released'], 'wp-cbt-pro'),
                (int) $_GET['released']
            )); ?></p>
        </div>
    <?php endif; ?>

    <form method="get" class="wpcbtpro-results__exam-picker">
        <input type="hidden" name="page" value="wpcbtpro-results">
        <label for="exam_id"><?php esc_html_e('Exam:', 'wp-cbt-pro'); ?></label>
        <select id="exam_id" name="exam_id" onchange="this.form.submit()">
            <?php foreach ($exams as $option): ?>
                <option value="<?php echo esc_attr((string) $option['id']); ?>" <?php selected($examId, (int) $option['id']); ?>>
                    <?php echo esc_html($option['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($exam === null): ?>
        <p><em><?php esc_html_e('No exams found.', 'wp-cbt-pro'); ?></em></p>
    <?php else: ?>

        <div class="wpcbtpro-results__actions">
            <?php if ($canRelease): ?>
                <form method="post" onsubmit="return confirm('<?php echo esc_js(__('Release all results for this exam? Candidates will be able to see their scores immediately.', 'wp-cbt-pro')); ?>');">
                    <?php wp_nonce_field('wpcbtpro_release_results_' . $examId, 'wpcbtpro_release_nonce'); ?>
                    <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $examId); ?>">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Release All Results', 'wp-cbt-pro'); ?></button>
                </form>
            <?php endif; ?>
            <a href="<?php echo esc_url($csvUrl); ?>" class="button"><?php esc_html_e('Download CSV', 'wp-cbt-pro'); ?></a>
            <button type="button" class="button" onclick="window.print();"><?php esc_html_e('Print / Save as PDF', 'wp-cbt-pro'); ?></button>
        </div>

        <?php if ($analytics !== null && $analytics['total'] > 0): ?>
            <div class="wpcbtpro-analytics">
                <div class="wpcbtpro-analytics__tile">
                    <span class="wpcbtpro-analytics__value"><?php echo esc_html((string) $analytics['total']); ?></span>
                    <span class="wpcbtpro-analytics__label"><?php esc_html_e('Candidates', 'wp-cbt-pro'); ?></span>
                </div>
                <div class="wpcbtpro-analytics__tile">
                    <span class="wpcbtpro-analytics__value"><?php echo esc_html((string) $analytics['average_percentage']); ?>%</span>
                    <span class="wpcbtpro-analytics__label"><?php esc_html_e('Average score', 'wp-cbt-pro'); ?></span>
                </div>
                <?php if ($analytics['pass_rate'] !== null): ?>
                    <div class="wpcbtpro-analytics__tile">
                        <span class="wpcbtpro-analytics__value"><?php echo esc_html((string) $analytics['pass_rate']); ?>%</span>
                        <span class="wpcbtpro-analytics__label"><?php esc_html_e('Pass rate', 'wp-cbt-pro'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="wpcbtpro-analytics__tile wpcbtpro-analytics__tile--grades">
                    <span class="wpcbtpro-analytics__label"><?php esc_html_e('Grade distribution', 'wp-cbt-pro'); ?></span>
                    <div class="wpcbtpro-analytics__grades">
                        <?php foreach ($analytics['grades'] as $grade => $count): ?>
                            <span class="wpcbtpro-pill"><?php echo esc_html($grade); ?>: <?php echo esc_html((string) $count); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($rows)): ?>
            <p><em><?php esc_html_e('No completed attempts yet for this exam.', 'wp-cbt-pro'); ?></em></p>
        <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Candidate', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Score', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Grade', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Result', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Released', 'wp-cbt-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    $result = $row['result'];
                    $candidate = $row['candidate'];
                ?>
                <tr>
                    <td>
                        <?php echo esc_html(trim($candidate['first_name'] . ' ' . $candidate['last_name'])); ?>
                        <br><small><?php echo esc_html($candidate['candidate_ref']); ?></small>
                    </td>
                    <td class="num"><?php echo esc_html($result['percentage']); ?>%</td>
                    <td><?php echo esc_html($result['grade'] ?? '—'); ?></td>
                    <td>
                        <?php if ($result['pass_status']): ?>
                            <span class="wpcbtpro-pill <?php echo $result['pass_status'] === 'pass' ? 'wpcbtpro-pill--active' : 'wpcbtpro-pill--suspended'; ?>">
                                <?php echo esc_html(ucfirst($result['pass_status'])); ?>
                            </span>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="wpcbtpro-pill <?php echo $result['status'] === 'final' ? 'wpcbtpro-pill--active' : 'wpcbtpro-pill--suspended'; ?>">
                            <?php echo $result['status'] === 'final' ? esc_html__('Final', 'wp-cbt-pro') : esc_html(sprintf(
                                /* translators: %d: number of questions still awaiting grading */
                                __('Provisional (%d pending)', 'wp-cbt-pro'),
                                (int) $result['pending_review_count']
                            )); ?>
                        </span>
                    </td>
                    <td><?php echo empty($result['released_at']) ? esc_html__('No', 'wp-cbt-pro') : esc_html__('Yes', 'wp-cbt-pro'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
