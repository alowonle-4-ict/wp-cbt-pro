<?php
/**
 * Plugin Name:       WP CBT Pro
 * Plugin URI:        https://example.com/wp-cbt-pro
 * Description:       Modular computer-based examination platform — candidate management, proctoring, secure code execution, and an interactive DSA engine.
 * Version:           0.5.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Adigun Nurudeen
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-cbt-pro
 * Domain Path:       /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('WPCBTPRO_VERSION', '0.5.0');
define('WPCBTPRO_DB_VERSION', '1.3.0');
define('WPCBTPRO_FILE', __FILE__);
define('WPCBTPRO_PATH', plugin_dir_path(__FILE__));
define('WPCBTPRO_URL', plugin_dir_url(__FILE__));
define('WPCBTPRO_TEXT_DOMAIN', 'wp-cbt-pro');

if (is_readable(WPCBTPRO_PATH . 'vendor/autoload.php')) {
    require_once WPCBTPRO_PATH . 'vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'WPCBTPro\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $path = WPCBTPRO_PATH . 'includes/' . str_replace('\\', '/', $relative) . '.php';
        if (is_readable($path)) {
            require $path;
        }
    });
}

register_activation_hook(__FILE__, [\WPCBTPro\Core\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\WPCBTPro\Core\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    \WPCBTPro\Core\Plugin::instance()->boot();
});
