<?php
/**
 * @var string $session
 * @var array<int, array<string, mixed>> $rows
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('Import Preview', 'wp-cbt-pro'); ?></h1>
    <p><?php esc_html_e('Review each detected candidate below. Rows with errors are unchecked and cannot be imported — fix them in the spreadsheet and re-upload. Only checked rows are imported.', 'wp-cbt-pro'); ?></p>

    <form method="post">
        <?php wp_nonce_field('wpcbtpro_candidate_import_confirm_' . $session, 'wpcbtpro_candidate_import_confirm_nonce'); ?>
        <input type="hidden" name="session" value="<?php echo esc_attr($session); ?>">

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="check-column"></td>
                    <th><?php esc_html_e('Row', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Name', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Email', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Phone', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Department', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Class', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Reg. Number', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Password', 'wp-cbt-pro'); ?></th>
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
                        <td><?php echo esc_html($input['phone']); ?></td>
                        <td><?php echo esc_html($input['department']); ?></td>
                        <td><?php echo esc_html($input['class']); ?></td>
                        <td><?php echo esc_html($input['registration_number']); ?></td>
                        <td><?php echo $row['password'] !== '' ? esc_html__('Yes', 'wp-cbt-pro') : esc_html__('— (no account)', 'wp-cbt-pro'); ?></td>
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

        <?php submit_button(__('Import Selected Candidates', 'wp-cbt-pro')); ?>
    </form>
</div>
