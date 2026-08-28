<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Database;

use WPCBTPro\Database\Schema;

final class SchemaInstallationTest extends \WP_UnitTestCase
{
    public function testActivationCreatesEveryPluginTable(): void
    {
        global $wpdb;

        foreach (array_keys(Schema::definitions($wpdb->prefix, $wpdb->get_charset_collate())) as $table) {
            $fullName = $wpdb->prefix . 'cbt_' . $table;
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $fullName));

            self::assertSame($fullName, $found, "Expected table {$fullName} to exist after activation.");
        }
    }
}
