<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Attempts;

use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Questions\QuestionRepository;

/**
 * AttemptRepository::findInProgress() (now findActive()) used to filter
 * strictly to status = 'in_progress', so a 'paused' attempt (reachable
 * today via the camera-disconnect "pause" policy, and by the admin
 * suspend action added alongside this fix) was invisible to it. A paused
 * candidate who reloaded was routed to start a brand-new attempt instead
 * of resuming — findActive() returned null, and the attempt-limit check
 * let them straight through if they were still under it, silently
 * orphaning the paused attempt.
 */
final class PausedAttemptResumptionTest extends \WP_UnitTestCase
{
    private AttemptService $attemptService;
    private AttemptRepository $attempts;
    private int $examId;
    private int $candidateId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = Plugin::instance()->container();
        $this->attemptService = $container->get(AttemptService::class);
        $this->attempts = $container->get(AttemptRepository::class);

        $institutionId = (int) get_option('wpcbtpro_default_institution_id');
        $userId = self::factory()->user->create();
        wp_set_current_user($userId);

        $this->candidateId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'wp_user_id' => $userId,
            'candidate_ref' => 'CBT-2026-000950',
            'first_name' => 'Paused',
            'last_name' => 'Candidate',
            'status' => 'active',
        ]);

        $questionId = (new QuestionRepository())->insert(
            [
                'institution_id' => $institutionId,
                'type' => 'mcq_single',
                'content' => 'Pick one.',
                'marks' => 1.0,
                'negative_marks' => 0.0,
                'status' => 'active',
            ],
            [
                ['label' => 'A', 'is_correct' => true, 'sort_order' => 0],
                ['label' => 'B', 'is_correct' => false, 'sort_order' => 1],
            ]
        );

        $examRepository = new ExamRepository();
        $this->examId = $examRepository->insert([
            'institution_id' => $institutionId,
            'name' => 'Pause Resumption Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
            'result_visibility' => 'immediate',
        ]);
        $examRepository->setQuestions($this->examId, [['question_id' => $questionId]]);
    }

    public function testFindActiveReturnsAPausedAttempt(): void
    {
        $attempt = $this->attemptService->startAttempt($this->examId, $this->candidateId);
        $this->attempts->update((int) $attempt['id'], ['status' => 'paused']);

        $found = $this->attempts->findActive($this->examId, $this->candidateId);

        self::assertNotNull($found, 'A paused attempt must still be found as active.');
        self::assertSame((int) $attempt['id'], (int) $found['id']);
    }

    public function testStartAttemptResumesAPausedAttemptInsteadOfCreatingASecondOne(): void
    {
        $first = $this->attemptService->startAttempt($this->examId, $this->candidateId);
        $this->attempts->update((int) $first['id'], ['status' => 'paused']);

        $second = $this->attemptService->startAttempt($this->examId, $this->candidateId);

        self::assertSame((int) $first['id'], (int) $second['id'], 'startAttempt() must resume the paused attempt, not create a new one.');
        self::assertCount(1, $this->attempts->allForCandidate($this->candidateId));
    }

    public function testCandidateSeesAPausedScreenNotTheStartScreen(): void
    {
        $attempt = $this->attemptService->startAttempt($this->examId, $this->candidateId);
        $this->attempts->update((int) $attempt['id'], ['status' => 'paused']);

        $html = do_shortcode('[wpcbtpro_exam id="' . $this->examId . '"]');

        self::assertStringContainsString('paused', $html);
        self::assertStringNotContainsString('wpcbtpro-start-btn', $html, 'A paused candidate must not be offered a fresh Start Exam button.');
    }

    public function testAPausedAttemptThatRanOutOfTimeStillGetsFinalized(): void
    {
        $attempt = $this->attemptService->startAttempt($this->examId, $this->candidateId);
        $this->attempts->update((int) $attempt['id'], [
            'status' => 'paused',
            'server_end' => gmdate('Y-m-d H:i:s', current_time('timestamp') - 60),
        ]);
        $attempt = $this->attempts->find((int) $attempt['id']);

        $exam = (new ExamRepository())->find($this->examId);
        $this->attemptService->submitAttempt($exam, $attempt);

        $finalized = $this->attempts->find((int) $attempt['id']);
        self::assertSame('submitted', $finalized['status']);
    }
}
