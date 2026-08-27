<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = get_option('wpcbtpro_settings', []);
$removeData = !empty($settings['remove_data_on_uninstall']);

require_once __DIR__ . '/includes/Security/Capabilities.php';
\WPCBTPro\Security\Capabilities::deregister();

delete_option('wpcbtpro_settings');
delete_option('wpcbtpro_db_version');

if (!$removeData) {
    return;
}

global $wpdb;

$tables = [
    'audit_logs', 'dsa_states', 'dsa_questions', 'code_execution_results',
    'code_submissions', 'programming_test_cases', 'programming_questions',
    'verification_records', 'camera_sessions', 'monitoring_events', 'results',
    'answers', 'attempts', 'exam_questions', 'question_options', 'questions',
    'exams', 'candidates', 'institutions',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cbt_{$table}");
}
