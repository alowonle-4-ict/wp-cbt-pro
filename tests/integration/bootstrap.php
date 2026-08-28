<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

putenv('WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php');

if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__, 2) . '/vendor/yoast/phpunit-polyfills');
}

$wpTestsDir = getenv('WP_PHPUNIT__DIR');

require_once $wpTestsDir . '/includes/functions.php';

/**
 * Loads the plugin the same way a real WordPress install would (as if it
 * were an mu-plugin, i.e. before `plugins_loaded`), so Plugin::boot() runs
 * through the ordinary `plugins_loaded` action during wp-settings.php.
 */
function _wpcbtpro_load_plugin(): void
{
    require dirname(__DIR__, 2) . '/wp-cbt-pro.php';
}
tests_add_filter('muplugins_loaded', '_wpcbtpro_load_plugin');

require $wpTestsDir . '/includes/bootstrap.php';

/**
 * Loading the file above only registers hooks — it does not fire
 * register_activation_hook (that only happens through activate_plugin()).
 * Run the real activation routine once so the plugin's own tables,
 * capabilities, and default institution exist for every integration test.
 */
\WPCBTPro\Core\Activator::activate();
