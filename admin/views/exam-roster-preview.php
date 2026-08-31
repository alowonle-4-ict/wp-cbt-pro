<?php
/**
 * @var array<string, mixed> $exam
 * @var string $session
 * @var array<int, array<string, mixed>> $rows
 */
if (!defined('ABSPATH')) {
    exit;
}

$examId = (int) $exam['id'];
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php echo esc_html(sprintf(
        /* translators: %s: exam name */
        __('Roster Import Preview: %s', 'wp-cbt-pro'),
        $exam['name']
    )); ?></h1>
    <p><?php esc_html_e('Review each detected candidate below. Rows with errors are unchecked and cannot be imported — fix them in the spreadsheet and re-upload. Only checked rows are added to this exam\'s roster.', 'wp-cbt-pro'); ?></p>

    <form method="post">
        <?php wp_nonce_field('wpcbtpro_exam_roster_confirm_' . $session, 'wpcbtpro_exam_roster_confirm_nonce'); ?>
        <input type="hidden" name="session" value="<?php echo esc_attr($session); ?>">
        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $examId); ?>">

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="check-column"></td>
                    <th><?php esc_html_e('Row', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Name', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Email', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Reg. Number', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Match', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Notes', 'wp-cbt-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row): $input = $row['input']; ?>
                    <tr>
                        <th class="check-column">
                            <input
                                type="checkbox"
                                name="rows[]"
                                value="<?php echo esc_attr((string) $index); ?>"
                                <?php checked($row['errors'] === []); ?>
                                <?php disabled($row['errors'] !== []); ?>
                            >
                        </th>
                        <td><?php echo esc_html((string) $row['row_number']); ?></td>
                        <td><?php echo esc_html(trim($input['first_name'] . ' ' . $input['last_name'])); ?></td>
                        <td><?php echo esc_html($input['email']); ?></td>
                        <td><?php echo esc_html($input['registration_number']); ?></td>
                        <td><?php echo $row['existing_candidate_id'] !== null ? esc_html__('Existing candidate', 'wp-cbt-pro') : esc_html__('New candidate', 'wp-cbt-pro'); ?></td>
                        <td>
                            <?php foreach ($row['errors'] as $error): ?>
                                <div class="wpcbtpro-import-row__warnings"><span>&#9888;</span> <?php echo esc_html($error); ?></div>
                            <?php endforeach; ?>
                            <?php foreach ($row['warnings'] as $warning): ?>
                                <div class="wpcbtpro-import-row__warnings"><span>&#9888;</span> <?php echo esc_html($warning); ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button(__('Add Selected to Roster', 'wp-cbt-pro')); ?>
    </form>
</div>
