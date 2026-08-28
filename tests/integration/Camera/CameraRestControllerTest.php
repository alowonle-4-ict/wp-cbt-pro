<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Camera;

use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Tests\Integration\RestTestCase;

/**
 * Exercises the proctoring REST surface end to end — session/state tracking,
 * the disconnect policy, snapshot upload, and identity verification — none
 * of which is covered by the pure-logic unit suite.
 */
final class CameraRestControllerTest extends RestTestCase
{
    /** A 1x1 transparent PNG, the smallest valid image Base64ImageUploader will accept. */
    private const TINY_PNG_DATA_URI = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private int $attemptId;

    protected function setUp(): void
    {
        parent::setUp();

        $institutionId = (int) get_option('wpcbtpro_default_institution_id');
        $userId = self::factory()->user->create();
        wp_set_current_user($userId);

        (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'wp_user_id' => $userId,
            'candidate_ref' => 'CBT-2026-000950',
            'first_name' => 'Camera',
            'last_name' => 'Candidate',
            'status' => 'active',
        ]);

        $questionRepository = new QuestionRepository();
        $questionId = $questionRepository->insert(
            [
                'institution_id' => $institutionId,
                'type' => 'mcq_single',
                'content' => 'Camera test question?',
                'marks' => 1.0,
                'negative_marks' => 0.0,
                'status' => 'active',
            ],
            [['label' => 'A', 'is_correct' => true, 'sort_order' => 0]]
        );

        $examRepository = new ExamRepository();
        $examId = $examRepository->insert([
            'institution_id' => $institutionId,
            'name' => 'Camera Integration Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
            'camera_required' => 1,
        ]);
        $examRepository->setQuestions($examId, [['question_id' => $questionId]]);

        $start = $this->dispatch('POST', '/wp-cbt-pro/v1/start-exam', ['exam_id' => $examId]);
        $this->attemptId = (int) $start->get_data()['attempt_id'];
    }

    public function testCameraConnectedEventRecordsSessionState(): void
    {
        $response = $this->dispatch('POST', '/wp-cbt-pro/v1/camera-event', [
            'attempt_id' => $this->attemptId,
            'event_type' => 'CAMERA_CONNECTED',
        ]);

        self::assertSame(200, $response->get_status());
        self::assertTrue($response->get_data()['recorded']);
        self::assertSame('connected', $response->get_data()['session_state']);
    }

    public function testDisconnectEventPausesAttemptWhenPolicyIsPause(): void
    {
        update_option('wpcbtpro_settings', ['camera_disconnect_policy' => 'pause']);

        $response = $this->dispatch('POST', '/wp-cbt-pro/v1/camera-event', [
            'attempt_id' => $this->attemptId,
            'event_type' => 'CAMERA_DISCONNECTED',
        ]);

        self::assertSame(200, $response->get_status());

        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}cbt_attempts WHERE id = %d",
            $this->attemptId
        ));
        self::assertSame('paused', $status);
    }

    public function testSnapshotUploadsAndRecordsAttachment(): void
    {
        $response = $this->dispatch('POST', '/wp-cbt-pro/v1/snapshot', [
            'attempt_id' => $this->attemptId,
            'image' => self::TINY_PNG_DATA_URI,
        ]);

        self::assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        self::assertTrue($response->get_data()['captured']);
    }

    public function testSnapshotRejectsMalformedImage(): void
    {
        $response = $this->dispatch('POST', '/wp-cbt-pro/v1/snapshot', [
            'attempt_id' => $this->attemptId,
            'image' => 'not-a-data-uri',
        ]);

        self::assertSame(422, $response->get_status());
    }

    public function testVerificationAlwaysRoutesToHumanReview(): void
    {
        $response = $this->dispatch('POST', '/wp-cbt-pro/v1/verification', [
            'attempt_id' => $this->attemptId,
            'image' => self::TINY_PNG_DATA_URI,
        ]);

        self::assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        self::assertSame('review_required', $response->get_data()['status']);
    }

    public function testCameraEventRejectsAttemptBelongingToAnotherCandidate(): void
    {
        $otherUserId = self::factory()->user->create();
        wp_set_current_user($otherUserId);
        (new CandidateRepository())->insert([
            'institution_id' => (int) get_option('wpcbtpro_default_institution_id'),
            'wp_user_id' => $otherUserId,
            'candidate_ref' => 'CBT-2026-000951',
            'first_name' => 'Other',
            'last_name' => 'Candidate',
            'status' => 'active',
        ]);

        $response = $this->dispatch('POST', '/wp-cbt-pro/v1/camera-event', [
            'attempt_id' => $this->attemptId,
            'event_type' => 'CAMERA_CONNECTED',
        ]);

        self::assertSame(403, $response->get_status());
    }
}
