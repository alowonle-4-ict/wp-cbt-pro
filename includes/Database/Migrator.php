<?php

declare(strict_types=1);

namespace WPCBTPro\Database;

final class Migrator
{
    private const DB_VERSION_OPTION = 'wpcbtpro_db_version';

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        foreach (Schema::definitions($wpdb->prefix, $charsetCollate) as $sql) {
            dbDelta($sql);
        }

        update_option(self::DB_VERSION_OPTION, WPCBTPRO_DB_VERSION);
    }

    public static function maybeUpgrade(): void
    {
        if (get_option(self::DB_VERSION_OPTION) !== WPCBTPRO_DB_VERSION) {
            self::install();
        }
    }

    public static function currentVersion(): string
    {
        return (string) get_option(self::DB_VERSION_OPTION, '');
    }
}
