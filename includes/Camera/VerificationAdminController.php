<?php

declare(strict_types=1);

namespace WPCBTPro\Camera;

use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Camera\Contracts\VerificationStatus;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Security\AuditLogger;
use WPCBTPro\Security\Capabilities;

/**
 * The human side of §12: every REVIEW_REQUIRED record created by the
 * default provider (§11 Phase 8) ends up here rather than being resolved
 * automatically. Reviewing never happens without an explicit reviewer id
 * attached to the record.
 */
final class VerificationAdminController
{
    public function __construct(
        private readonly VerificationRepository $verifications,
        private readonly AttemptRepository $attempts,
        private readonly ExamRepository $exams,
        private readonly CandidateRepository $candidates,
        private readonly InstitutionContext $institutionContext,
    ) {
    }

    public function register(): void
    {
        // Processed on admin_init — before WordPress starts streaming the admin
        // page's HTML — because wp_safe_redirect() from inside the
        // add_submenu_page() render callback itself is always too late: WP has
        // already sent the page header by the time that callback runs, so the
        // redirect silently fails ("headers already sent") and the reviewer is
        // left looking at a blank page. render() only ever displays.
        add_action('admin_init', [$this, 'maybeProcessRequest']);
    }

    public function maybeProcessRequest(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only: confirms this hook applies to our own page before doing anything.
        if (($_GET['page'] ?? '') !== 'wpcbtpro-verification') {
            return;
        }

        if (!current_user_can(Capabilities::REVIEW_ATTEMPTS)) {
            return; // render() will wp_die() with the proper message for a real page view.
        }

        // handleReview() runs check_admin_referer() as its first statement; this only decides whether to dispatch there.
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_review_nonce'])) {
            $this->handleReview();
        }
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::REVIEW_ATTEMPTS)) {
            wp_die(esc_html__('You do not have permission to review identity verifications.', 'wp-cbt-pro'));
        }

        $rows = $this->buildQueue();

        include WPCBTPRO_PATH . 'admin/views/verification-review.php';
    }

    /** @return array<int, array{record: array, attempt: array, exam: array, candidate: array}> */
    private function buildQueue(): array
    {
        $institutionId = current_user_can(Capabilities::MANAGE_CBT) ? null : $this->institutionContext->currentId();

        $rows = [];
        foreach ($this->verifications->allByStatus(VerificationStatus::ReviewRequired->value) as $record) {
            $attempt = $this->attempts->find((int) $record['attempt_id']);
            if ($attempt === null) {
                continue;
            }
            $exam = $this->exams->find((int) $attempt['exam_id']);
            if ($exam === null) {
                continue;
            }
            if ($institutionId !== null && (int) $exam['institution_id'] !== $institutionId) {
                continue;
            }
            $candidate = $this->candidates->find((int) $attempt['candidate_id']);
            if ($candidate === null) {
                continue;
            }

            $rows[] = ['record' => $record, 'attempt' => $attempt, 'exam' => $exam, 'candidate' => $candidate];
        }

        return $rows;
    }

    private function handleReview(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- record_id is only used to build the nonce action string; check_admin_referer() below rejects any tampering.
        $recordId = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;
        check_admin_referer('wpcbtpro_review_verification_' . $recordId, 'wpcbtpro_review_nonce');

        $decision = sanitize_key($_POST['decision'] ?? '');
        $status = match ($decision) {
            'verify' => VerificationStatus::Verified,
            'fail' => VerificationStatus::Failed,
            default => null,
        };

        if ($status === null) {
            wp_die(esc_html__('Invalid review decision.', 'wp-cbt-pro'));
        }

        $record = $this->verifications->find($recordId);
        if ($record === null) {
            wp_die(esc_html__('Verification record not found.', 'wp-cbt-pro'));
        }

        $this->verifications->updateStatus($recordId, $status->value, get_current_user_id());
        AuditLogger::record('verification.reviewed', 'verification_record', $recordId, ['decision' => $status->value]);

        $settings = get_option('wpcbtpro_settings', []);
        if (($settings['snapshot_retention'] ?? '') === 'delete_immediately' && !empty($record['captured_image_attachment_id'])) {
            wp_delete_attachment((int) $record['captured_image_attachment_id'], true);
            $this->verifications->clearCapturedImage($recordId);
        }

        wp_safe_redirect(add_query_arg(['page' => 'wpcbtpro-verification', 'reviewed' => 1], admin_url('admin.php')));
        exit;
    }
}
