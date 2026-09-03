<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Camera;

use WPCBTPro\Camera\Contracts\CameraVerificationService;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionRepository;
use WPCBTPro\Questions\QuestionRepository;

/**
 * A candidate's identity-verification capture only ever fills in a
 * *missing* profile photo — never overwrites an existing one. That existing
 * photo is the fixed reference AutoMonitoringService compares every later
 * live frame against; a capture silently replacing it would let whoever is
 * in front of the camera at verification time become their own "correct"
 * answer.
 */
final class VerificationProfilePhotoTest extends \WP_UnitTestCase
{
    // A genuine 1x1 PNG, so Base64ImageUploader's real upload path runs end to end.
    private const SAMPLE_IMAGE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private function makeInProgressAttempt(?int $photoAttachmentId): array
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();

        $questionId = (new QuestionRepository())->insert(
            ['institution_id' => $institutionId, 'type' => 'mcq_single', 'content' => 'Pick one.', 'marks' => 1.0, 'negative_marks' => 0.0, 'status' => 'active'],
            [['label' => 'A', 'is_correct' => true, 'sort_order' => 0], ['label' => 'B', 'is_correct' => false, 'sort_order' => 1]]
        );

        $examRepository = new ExamRepository();
        $examId = $examRepository->insert([
            'institution_id' => $institutionId,
            'name' => 'Verification Photo Test Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
            'result_visibility' => 'immediate',
            'identity_verification' => 1,
        ]);
        $examRepository->setQuestions($examId, [['question_id' => $questionId]]);

        $candidateRepository = new CandidateRepository();
        $candidateId = $candidateRepository->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-VERIFYPHOTO-' . wp_generate_password(6, false, false),
            'first_name' => 'Verify',
            'last_name' => 'PhotoTest',
            'photo_attachment_id' => $photoAttachmentId,
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ]);

        $attemptService = Plugin::instance()->container()->get(\WPCBTPro\Attempts\AttemptService::class);
        $attempt = $attemptService->startAttempt($examId, $candidateId);

        return ['candidate' => $candidateRepository->find($candidateId), 'attempt' => $attempt];
    }

    public function testVerificationFillsInAMissingProfilePhoto(): void
    {
        $fixture = $this->makeInProgressAttempt(null);

        /** @var CameraVerificationService $service */
        $service = Plugin::instance()->container()->get(CameraVerificationService::class);
        $session = $service->startSession((int) $fixture['attempt']['id']);
        $result = $service->verifyIdentity($session, $fixture['candidate'], self::SAMPLE_IMAGE);

        self::assertNotNull($result->capturedAttachmentId);

        $candidate = (new CandidateRepository())->find((int) $fixture['candidate']['id']);
        self::assertSame($result->capturedAttachmentId, (int) $candidate['photo_attachment_id']);
    }

    public function testVerificationNeverOverwritesAnExistingProfilePhoto(): void
    {
        $existingPhotoId = self::factory()->attachment->create_upload_object(
            WPCBTPRO_PATH . 'tests/fixtures/question-with-image.docx'
        );
        // The fixture above isn't an image; just need a real, distinct
        // attachment id already on the candidate record before verification.
        $fixture = $this->makeInProgressAttempt($existingPhotoId);

        /** @var CameraVerificationService $service */
        $service = Plugin::instance()->container()->get(CameraVerificationService::class);
        $session = $service->startSession((int) $fixture['attempt']['id']);
        $result = $service->verifyIdentity($session, $fixture['candidate'], self::SAMPLE_IMAGE);

        self::assertNotNull($result->capturedAttachmentId);
        self::assertNotSame($existingPhotoId, $result->capturedAttachmentId, 'The verification capture should be a distinct new attachment.');

        $candidate = (new CandidateRepository())->find((int) $fixture['candidate']['id']);
        self::assertSame($existingPhotoId, (int) $candidate['photo_attachment_id'], 'The pre-existing profile photo must be untouched.');
    }
}
