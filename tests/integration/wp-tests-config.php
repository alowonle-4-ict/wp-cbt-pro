<?php
/**
 * WordPress test-suite config, shared by local runs and CI. Every value has
 * a WPCBTPRO_TEST_* environment variable override so CI can point this at
 * its own MySQL service and WordPress core checkout without editing this
 * file — see .github/workflows/ci.yml. The defaults below match a fresh
 * local setup: `wpcbtpro_test` database/user created per README, WordPress
 * core downloaded to .wordpress-core/wordpress (gitignored).
 */

define('DB_NAME', getenv('WPCBTPRO_TEST_DB_NAME') ?: 'wpcbtpro_test');
define('DB_USER', getenv('WPCBTPRO_TEST_DB_USER') ?: 'wpcbtpro_test');
define('DB_PASSWORD', getenv('WPCBTPRO_TEST_DB_PASSWORD') ?: 'wpcbtpro_test_pw');
define('DB_HOST', getenv('WPCBTPRO_TEST_DB_HOST') ?: '127.0.0.1:3307');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'WP CBT Pro Test Suite');

define('WP_PHP_BINARY', 'php');

define('WPLANG', '');

define('ABSPATH', getenv('WPCBTPRO_TEST_WP_CORE_DIR') ?: (dirname(__DIR__, 2) . '/.wordpress-core/wordpress/'));
