<?php

declare(strict_types=1);

namespace WPCBTPro\Programming;

use WPCBTPro\Security\Capabilities;

/**
 * Where to send code for grading is a site-wide infrastructure setting
 * (§16), not something scoped per institution — only a full manage_cbt
 * administrator configures it.
 */
final class ExecutionSettingsController
{
    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT)) {
            wp_die(esc_html__('You do not have permission to manage execution settings.', 'wp-cbt-pro'));
        }

        $saved = false;
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- check_admin_referer() below verifies before anything is read.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_execution_settings_nonce'])) {
            check_admin_referer('wpcbtpro_save_execution_settings', 'wpcbtpro_execution_settings_nonce');

            $settings = get_option('wpcbtpro_settings', []);
            $settings['execution_service_url'] = esc_url_raw(wp_unslash($_POST['execution_service_url'] ?? ''));
            $settings['execution_service_api_key'] = sanitize_text_field(wp_unslash($_POST['execution_service_api_key'] ?? ''));
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated against an explicit allowlist below, which is stronger than a generic sanitizer.
            $postedPolicy = sanitize_key(wp_unslash($_POST['camera_disconnect_policy'] ?? ''));
            $settings['camera_disconnect_policy'] = in_array($postedPolicy, ['log', 'pause', 'terminate'], true)
                ? $postedPolicy
                : ($settings['camera_disconnect_policy'] ?? 'pause');
            $settings['snapshot_retention'] = sanitize_key($_POST['snapshot_retention'] ?? ($settings['snapshot_retention'] ?? '30_days'));

            update_option('wpcbtpro_settings', $settings);
            $saved = true;
        }

        $settings = get_option('wpcbtpro_settings', []);

        include WPCBTPRO_PATH . 'admin/views/execution-settings.php';
    }
}
