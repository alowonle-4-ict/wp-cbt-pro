<?php

declare(strict_types=1);

namespace WPCBTPro\Security;

final class AuditLogger
{
    public static function record(string $action, string $objectType, ?int $objectId = null, array $context = []): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'cbt_audit_logs', [
            'actor_id' => get_current_user_id() ?: null,
            'action' => $action,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'context' => $context === [] ? null : wp_json_encode($context),
            'created_at' => current_time('mysql'),
        ]);
    }
}
