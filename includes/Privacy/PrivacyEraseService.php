<?php

declare(strict_types=1);

namespace WPCBTPro\Privacy;

use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Camera\VerificationRepository;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Monitoring\MonitoringEventRepository;
use WPCBTPro\Security\AuditLogger;

/**
 * WordPress's "Erase Personal Data" tool, wired up for this plugin's tables.
 * Erasure here means anonymize-and-redact, not delete-the-row: an exam
 * result is an institution's academic record, not just the candidate's
 * personal data, so it is kept (with identity scrubbed) the same way
 * RetentionCleanupService keeps a redacted monitoring event instead of
 * deleting it outright (§20). Only genuinely identifying material — name,
 * contact details, photos, proctoring snapshots — is actually removed.
 */
final class PrivacyEraseService
{
    public function __construct(
        private readonly CandidateRepository $candidates,
        private readonly AttemptRepository $attempts,
        private readonly VerificationRepository $verifications,
        private readonly MonitoringEventRepository $events,
    ) {
    }

    /** @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool} */
    public function erase(string $emailAddress): array
    {
        $candidate = $this->candidates->findByEmail($emailAddress);
        if ($candidate === null) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        $candidateId = (int) $candidate['id'];
        // Reaching here means a real candidate record was found and is about
        // to have its name/email/phone wiped below — that's real personal
        // data being removed regardless of whether a photo, verification
        // snapshot, or monitoring payload also exists to clean up, so
        // items_removed must already be true, not stay conditionally false
        // on those extras (a candidate who never used the camera would
        // otherwise incorrectly report "nothing was removed").
        $removedSomething = true;

        if (!empty($candidate['photo_attachment_id'])) {
            wp_delete_attachment((int) $candidate['photo_attachment_id'], true);
        }

        $this->candidates->update($candidateId, [
            'first_name' => __('Redacted', 'wp-cbt-pro'),
            'last_name' => __('Candidate', 'wp-cbt-pro'),
            'email' => null,
            'phone' => null,
            'photo_attachment_id' => null,
        ]);

        foreach ($this->attempts->allForCandidate($candidateId) as $attempt) {
            $attemptId = (int) $attempt['id'];

            $verification = $this->verifications->findLatestByAttempt($attemptId);
            if ($verification !== null && !empty($verification['captured_image_attachment_id'])) {
                wp_delete_attachment((int) $verification['captured_image_attachment_id'], true);
                $this->verifications->clearCapturedImage((int) $verification['id']);
            }

            foreach ($this->events->allForAttempt($attemptId) as $event) {
                if ($event['payload'] !== null) {
                    $this->events->redactPayload((int) $event['id']);
                }
            }
        }

        AuditLogger::record('candidate.privacy_erased', 'candidate', $candidateId);

        return [
            'items_removed' => $removedSomething,
            // Attempts/answers/results themselves are kept, identity-scrubbed,
            // as the institution's academic record — this is not an omission.
            'items_retained' => true,
            'messages' => [
                __('Exam attempts and results were retained as anonymized academic records rather than deleted.', 'wp-cbt-pro'),
            ],
            'done' => true,
        ];
    }
}
