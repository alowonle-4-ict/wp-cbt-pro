<?php

declare(strict_types=1);

namespace WPCBTPro\Core;

final class CronSchedules
{
    public static function register(): void
    {
        add_filter('cron_schedules', static function (array $schedules): array {
            $schedules['wpcbtpro_five_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display' => __('Every 5 minutes (WP CBT Pro)', 'wp-cbt-pro'),
            ];
            return $schedules;
        });
    }
}
