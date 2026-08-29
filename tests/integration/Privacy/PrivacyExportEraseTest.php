<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Privacy;

use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Institutions\InstitutionRepository;

/**
 * Exercises the exact callback path WordPress core's own Privacy Tools
 * admin screens invoke (the 'wp_privacy_personal_data_exporters'/'erasers'
 * filters), not just the service classes directly — this is what actually
 * proves the plugin is wired into WP's GDPR tooling, not only that the
 * underlying logic is correct.
 */
final class PrivacyExportEraseTest extends \WP_UnitTestCase
{
    public function testExportAndEraseForACandidateWithNoPhotoOrCameraActivity(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $candidateRepo = new CandidateRepository();
        $email = 'privacy-test-' . wp_generate_password(8, false) . '@example.org';

        $candidateId = $candidateRepo->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-PRIV-' . wp_generate_password(6, false, false),
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => $email,
            'phone' => '555-0100',
            'status' => 'active',
        ]);

        $exporters = apply_filters('wp_privacy_personal_data_exporters', []);
        self::assertArrayHasKey('wp-cbt-pro', $exporters, 'The plugin must register a WP privacy exporter.');

        $exportResult = ($exporters['wp-cbt-pro']['callback'])($email, 1);
        self::assertTrue($exportResult['done']);
        self::assertNotEmpty($exportResult['data']);
        self::assertSame('wpcbtpro-candidate-profile', $exportResult['data'][0]['group_id']);

        $erasers = apply_filters('wp_privacy_personal_data_erasers', []);
        self::assertArrayHasKey('wp-cbt-pro', $erasers, 'The plugin must register a WP privacy eraser.');

        $eraseResult = ($erasers['wp-cbt-pro']['callback'])($email, 1);

        // The bug this guards: a candidate with no photo, no verification
        // snapshot, and no monitoring event payload to clean up still had
        // their name/email/phone genuinely wiped below — items_removed must
        // reflect that, not silently report "nothing was removed" just
        // because there were no extra attachments/payloads on top of it.
        self::assertTrue(
            $eraseResult['items_removed'],
            'items_removed must be true: name/email/phone were redacted even though there was no photo, verification image, or monitoring payload to also remove.'
        );
        self::assertTrue($eraseResult['items_retained']);

        $afterErase = $candidateRepo->find($candidateId);
        self::assertSame('Redacted', $afterErase['first_name']);
        self::assertSame('Candidate', $afterErase['last_name']);
        self::assertNull($afterErase['email']);
        self::assertNull($afterErase['phone']);

        // A second export against the now-nulled email must find nothing —
        // proving the erasure is actually complete, not merely reported as such.
        $exportAfterErase = ($exporters['wp-cbt-pro']['callback'])($email, 1);
        self::assertSame([], $exportAfterErase['data']);
    }

    public function testExportIncludesAttemptsResultsAndVerification(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $candidateRepo = new CandidateRepository();
        $email = 'privacy-full-' . wp_generate_password(8, false) . '@example.org';

        $candidateId = $candidateRepo->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-PRIV-' . wp_generate_password(6, false, false),
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => $email,
            'status' => 'active',
        ]);

        $questionRepo = new \WPCBTPro\Questions\QuestionRepository();
        $questionId = $questionRepo->insert(
            ['institution_id' => $institutionId, 'type' => 'mcq_single', 'content' => 'Fixture', 'marks' => 1.0, 'negative_marks' => 0.0, 'status' => 'active'],
            [['label' => 'A', 'is_correct' => true, 'sort_order' => 0], ['label' => 'B', 'is_correct' => false, 'sort_order' => 1]]
        );
        $examRepo = new \WPCBTPro\Exams\ExamRepository();
        $examId = $examRepo->insert([
            'institution_id' => $institutionId,
            'name' => 'Privacy Export Fixture Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 5,
            'status' => 'active',
            'result_visibility' => 'immediate',
        ]);
        $examRepo->setQuestions($examId, [['question_id' => $questionId]]);

        /** @var \WPCBTPro\Attempts\AttemptService $attemptService */
        $attemptService = Plugin::instance()->container()->get(\WPCBTPro\Attempts\AttemptService::class);
        $attempt = $attemptService->startAttempt($examId, $candidateId);
        $exam = $examRepo->find($examId);
        $attemptService->submitAttempt($exam, $attempt);

        $exporters = apply_filters('wp_privacy_personal_data_exporters', []);
        $exportResult = ($exporters['wp-cbt-pro']['callback'])($email, 1);

        $groupIds = array_column($exportResult['data'], 'group_id');
        self::assertContains('wpcbtpro-candidate-profile', $groupIds);
        self::assertContains('wpcbtpro-exam-attempts', $groupIds);
        self::assertContains('wpcbtpro-exam-results', $groupIds);

        // Attempts/results themselves must survive an erase — they're the
        // institution's academic record, only identity is scrubbed.
        $erasers = apply_filters('wp_privacy_personal_data_erasers', []);
        ($erasers['wp-cbt-pro']['callback'])($email, 1);

        $attemptRepo = new AttemptRepository();
        self::assertCount(1, $attemptRepo->allForCandidate($candidateId), 'Attempts must be retained (identity-scrubbed), not deleted, on erase.');
    }
}
