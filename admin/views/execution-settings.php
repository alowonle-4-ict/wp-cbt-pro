<?php
/**
 * @var array<string, mixed> $settings
 * @var bool $saved
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('CBT Settings', 'wp-cbt-pro'); ?></h1>

    <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'wp-cbt-pro'); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('wpcbtpro_save_execution_settings', 'wpcbtpro_execution_settings_nonce'); ?>

        <h2><?php esc_html_e('Code Execution Service', 'wp-cbt-pro'); ?></h2>
        <p class="description"><?php esc_html_e('Candidate code is never run by WordPress — it is sent here, over HTTPS, to an isolated sandbox service (§16).', 'wp-cbt-pro'); ?></p>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="execution_service_url"><?php esc_html_e('Service URL', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <input type="url" id="execution_service_url" name="execution_service_url" class="regular-text" value="<?php echo esc_attr($settings['execution_service_url'] ?? ''); ?>" placeholder="https://sandbox.example.com">
                    <p class="description"><?php esc_html_e('See execution-service/README.md for the reference implementation.', 'wp-cbt-pro'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="execution_service_api_key"><?php esc_html_e('API key', 'wp-cbt-pro'); ?></label></th>
                <td><input type="password" id="execution_service_api_key" name="execution_service_api_key" class="regular-text" value="<?php echo esc_attr($settings['execution_service_api_key'] ?? ''); ?>" autocomplete="off"></td>
            </tr>
        </table>

        <h2><?php esc_html_e('Camera & Monitoring', 'wp-cbt-pro'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="camera_disconnect_policy"><?php esc_html_e('On camera disconnect', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <select id="camera_disconnect_policy" name="camera_disconnect_policy">
                        <?php foreach (['log' => __('Log and continue', 'wp-cbt-pro'), 'pause' => __('Pause the exam', 'wp-cbt-pro'), 'terminate' => __('Terminate the attempt', 'wp-cbt-pro')] as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['camera_disconnect_policy'] ?? 'pause', $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="snapshot_retention"><?php esc_html_e('Snapshot & verification image retention', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <select id="snapshot_retention" name="snapshot_retention">
                        <?php foreach (['24_hours' => __('24 hours', 'wp-cbt-pro'), '7_days' => __('7 days', 'wp-cbt-pro'), '30_days' => __('30 days', 'wp-cbt-pro'), '90_days' => __('90 days', 'wp-cbt-pro'), 'delete_immediately' => __('Delete immediately after review', 'wp-cbt-pro')] as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['snapshot_retention'] ?? '30_days', $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Settings', 'wp-cbt-pro')); ?>
    </form>
</div>
