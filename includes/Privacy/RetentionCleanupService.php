<?php

declare(strict_types=1);

namespace WPCBTPro\Privacy;

use WPCBTPro\Camera\VerificationRepository;
use WPCBTPro\Monitoring\MonitoringEventRepository;

/**
 * The automatic side of §20 — camera snapshots and identity-verification
 * captures are deleted after the configured retention window regardless of
 * whether anyone remembered to review them. The monitoring event itself is
 * kept (redacted, not removed) so the fact a snapshot was taken stays part
 * of the audit trail even after the image is gone.
 */
final class RetentionCleanupService
{
    public function __construct(
        private readonly MonitoringEventRepository $events,
        private readonly VerificationRepository $verifications,
    ) {
    }

    public function run(): void
    {
        $settings = get_option('wpcbtpro_settings', []);
        $retention = $settings['snapshot_retention'] ?? '30_days';

        $windowSeconds = $this->windowSeconds($retention, $settings);
        if ($windowSeconds === null) {
            return; // 'delete_immediately' is handled at capture/review time, not on a timer
        }

        $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - $windowSeconds);

        foreach ($this->events->findSnapshotsOlderThan($cutoff) as $event) {
            $payload = json_decode((string) $event['payload'], true);
            $attachmentId = (int) ($payload['attachment_id'] ?? 0);
            if ($attachmentId > 0) {
                wp_delete_attachment($attachmentId, true);
            }
            $this->events->redactPayload((int) $event['id']);
        }

        foreach ($this->verifications->allReviewedOlderThan($cutoff) as $record) {
            wp_delete_attachment((int) $record['captured_image_attachment_id'], true);
            $this->verifications->clearCapturedImage((int) $record['id']);
        }
    }

    /** @param array<string, mixed> $settings */
    private function windowSeconds(string $retention, array $settings): ?int
    {
        return match ($retention) {
            '24_hours' => DAY_IN_SECONDS,
            '7_days' => 7 * DAY_IN_SECONDS,
            '30_days' => 30 * DAY_IN_SECONDS,
            '90_days' => 90 * DAY_IN_SECONDS,
            'custom' => max(1, (int) ($settings['snapshot_retention_days'] ?? 30)) * DAY_IN_SECONDS,
            default => null, // 'delete_immediately' or unrecognized
        };
    }
}
