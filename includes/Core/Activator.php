<?php

declare(strict_types=1);

namespace WPCBTPro\Core;

use WPCBTPro\Database\Migrator;
use WPCBTPro\Institutions\InstitutionRepository;
use WPCBTPro\Security\Capabilities;

final class Activator
{
    public static function activate(): void
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            deactivate_plugins(plugin_basename(WPCBTPRO_FILE));
            wp_die(esc_html__(
                'WP CBT Pro requires PHP 8.1 or higher.',
                'wp-cbt-pro'
            ));
        }

        CronSchedules::register();

        Migrator::install();
        Capabilities::register();
        (new InstitutionRepository())->ensureDefault();

        if (false === get_option('wpcbtpro_settings')) {
            add_option('wpcbtpro_settings', [
                'camera_disconnect_policy' => 'pause',
                'snapshot_retention' => '30_days',
            ]);
        }

        if (!wp_next_scheduled('wpcbtpro_expire_attempts')) {
            // Hourly is only a backstop for abandoned tabs — every real
            // request against an attempt already enforces expiry lazily
            // (AttemptService::isExpired()), so cron precision doesn't matter here.
            wp_schedule_event(time(), 'hourly', 'wpcbtpro_expire_attempts');
        }

        if (!wp_next_scheduled('wpcbtpro_cleanup_retention')) {
            wp_schedule_event(time(), 'daily', 'wpcbtpro_cleanup_retention');
        }

        if (!wp_next_scheduled('wpcbtpro_process_code_grading')) {
            wp_schedule_event(time(), 'wpcbtpro_five_minutes', 'wpcbtpro_process_code_grading');
        }

        if (!wp_next_scheduled('wpcbtpro_release_delayed_results')) {
            wp_schedule_event(time(), 'wpcbtpro_five_minutes', 'wpcbtpro_release_delayed_results');
        }

        flush_rewrite_rules();
    }
}
