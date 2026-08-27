<?php

declare(strict_types=1);

namespace WPCBTPro\Privacy;

use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Camera\VerificationRepository;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Results\ResultRepository;

/**
 * WordPress's own "Export Personal Data" tool (Tools > Export Personal Data)
 * has no idea this plugin's tables exist unless something registers an
 * exporter for them — without this, an access request an institution
 * receives would silently miss every candidate/attempt/proctoring record
 * this plugin holds (§20).
 */
final class PrivacyExportService
{
    public function __construct(
        private readonly CandidateRepository $candidates,
        private readonly AttemptRepository $attempts,
        private readonly ExamRepository $exams,
        private readonly ResultRepository $results,
        private readonly VerificationRepository $verifications,
    ) {
    }

    /** @return array{data: array<int, array{group_id:string, group_label:string, item_id:string, data:array}>, done: bool} */
    public function export(string $emailAddress): array
    {
        $candidate = $this->candidates->findByEmail($emailAddress);
        if ($candidate === null) {
            return ['data' => [], 'done' => true];
        }

        $exportItems = [];
        $exportItems[] = $this->profileItem($candidate);

        foreach ($this->attempts->allForCandidate((int) $candidate['id']) as $attempt) {
            $exportItems[] = $this->attemptItem($attempt);

            $result = $this->results->findByAttempt((int) $attempt['id']);
            if ($result !== null) {
                $exportItems[] = $this->resultItem($attempt, $result);
            }

            $verification = $this->verifications->findLatestByAttempt((int) $attempt['id']);
            if ($verification !== null) {
                $exportItems[] = $this->verificationItem($attempt, $verification);
            }
        }

        return ['data' => $exportItems, 'done' => true];
    }

    private function profileItem(array $candidate): array
    {
        return [
            'group_id' => 'wpcbtpro-candidate-profile',
            'group_label' => __('CBT Candidate Profile', 'wp-cbt-pro'),
            'item_id' => 'candidate-' . $candidate['id'],
            'data' => [
                ['name' => __('Candidate Reference', 'wp-cbt-pro'), 'value' => $candidate['candidate_ref']],
                ['name' => __('First Name', 'wp-cbt-pro'), 'value' => $candidate['first_name']],
                ['name' => __('Last Name', 'wp-cbt-pro'), 'value' => $candidate['last_name']],
                ['name' => __('Email', 'wp-cbt-pro'), 'value' => (string) ($candidate['email'] ?? '')],
                ['name' => __('Phone', 'wp-cbt-pro'), 'value' => (string) ($candidate['phone'] ?? '')],
                ['name' => __('Department', 'wp-cbt-pro'), 'value' => (string) ($candidate['department'] ?? '')],
                ['name' => __('Registered', 'wp-cbt-pro'), 'value' => $candidate['created_at']],
            ],
        ];
    }

    private function attemptItem(array $attempt): array
    {
        $exam = $this->exams->find((int) $attempt['exam_id']);

        return [
            'group_id' => 'wpcbtpro-exam-attempts',
            'group_label' => __('CBT Exam Attempts', 'wp-cbt-pro'),
            'item_id' => 'attempt-' . $attempt['id'],
            'data' => [
                ['name' => __('Exam', 'wp-cbt-pro'), 'value' => $exam['name'] ?? __('(deleted exam)', 'wp-cbt-pro')],
                ['name' => __('Started', 'wp-cbt-pro'), 'value' => $attempt['server_start']],
                ['name' => __('Submitted', 'wp-cbt-pro'), 'value' => (string) $attempt['submitted_at']],
                ['name' => __('Status', 'wp-cbt-pro'), 'value' => $attempt['status']],
            ],
        ];
    }

    private function resultItem(array $attempt, array $result): array
    {
        return [
            'group_id' => 'wpcbtpro-exam-results',
            'group_label' => __('CBT Exam Results', 'wp-cbt-pro'),
            'item_id' => 'result-' . $result['id'],
            'data' => [
                ['name' => __('Attempt', 'wp-cbt-pro'), 'value' => 'attempt-' . $attempt['id']],
                ['name' => __('Score', 'wp-cbt-pro'), 'value' => $result['score']],
                ['name' => __('Percentage', 'wp-cbt-pro'), 'value' => $result['percentage']],
                ['name' => __('Grade', 'wp-cbt-pro'), 'value' => (string) $result['grade']],
                ['name' => __('Pass/Fail', 'wp-cbt-pro'), 'value' => (string) $result['pass_status']],
                ['name' => __('Released to candidate', 'wp-cbt-pro'), 'value' => empty($result['released_at']) ? __('No', 'wp-cbt-pro') : __('Yes', 'wp-cbt-pro')],
            ],
        ];
    }

    private function verificationItem(array $attempt, array $verification): array
    {
        return [
            'group_id' => 'wpcbtpro-identity-verification',
            'group_label' => __('CBT Identity Verification', 'wp-cbt-pro'),
            'item_id' => 'verification-' . $verification['id'],
            'data' => [
                ['name' => __('Attempt', 'wp-cbt-pro'), 'value' => 'attempt-' . $attempt['id']],
                ['name' => __('Status', 'wp-cbt-pro'), 'value' => $verification['status']],
                ['name' => __('Photo captured on file', 'wp-cbt-pro'), 'value' => empty($verification['captured_image_attachment_id']) ? __('No', 'wp-cbt-pro') : __('Yes', 'wp-cbt-pro')],
                ['name' => __('Recorded', 'wp-cbt-pro'), 'value' => $verification['created_at']],
            ],
        ];
    }
}
