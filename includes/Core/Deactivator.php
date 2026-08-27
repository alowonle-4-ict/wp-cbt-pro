<?php

declare(strict_types=1);

namespace WPCBTPro\Core;

final class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('wpcbtpro_cleanup_retention');
        wp_clear_scheduled_hook('wpcbtpro_expire_attempts');
        wp_clear_scheduled_hook('wpcbtpro_process_code_grading');
        wp_clear_scheduled_hook('wpcbtpro_release_delayed_results');
        flush_rewrite_rules();
    }
}
