<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Attempts;

use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Attempts\CandidateExamOverrideRepository;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Exams\ExamRepository;

final class CandidateExamOverrideTest extends \WP_UnitTestCase
{
    private AttemptService $attemptService;
    private CandidateExamOverrideRepository $overrides;
    private int $examId;
    private int $candidateId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = Plugin::instance()->container();
        $this->attemptService = $container->get(AttemptService::class);
        $this->overrides = $container->get(CandidateExamOverrideRepository::class);

        $institutionId = (int) get_option('wpcbtpro_default_institution_id');
        $userId = self::factory()->user->create();

        $this->candidateId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'wp_user_id' => $userId,
            'candidate_ref' => 'CBT-2026-000960',
            'first_name' => 'Override',
            'last_name' => 'Candidate',
            'status' => 'active',
        ]);

        $this->examId = (new ExamRepository())->insert([
            'institution_id' => $institutionId,
            'name' => 'Override Test Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
        ]);
    }

    public function testGrantingAnExtraAttemptLetsACandidatePastTheNormalLimit(): void
    {
        $first = $this->attemptService->startAttempt($this->examId, $this->candidateId);
        $this->attemptService->submitAttempt((new ExamRepository())->find($this->examId), $first);

        $this->expectException(\RuntimeException::class);
        $this->attemptService->startAttempt($this->examId, $this->candidateId);
    }

    public function testAfterGrantingAnExtraAttemptASecondAttemptIsAllowed(): void
    {
        $exam = (new ExamRepository())->find($this->examId);
        $first = $this->attemptService->startAttempt($this->examId, $this->candidateId);
        $this->attemptService->submitAttempt($exam, $first);

        $this->overrides->addExtraAttempts($this->examId, $this->candidateId, 1);

        $second = $this->attemptService->startAttempt($this->examId, $this->candidateId);

        self::assertNotSame((int) $first['id'], (int) $second['id']);
    }

    public function testGrantingExtraMinutesLengthensANewAttemptsDeadline(): void
    {
        $this->overrides->addExtraMinutes($this->examId, $this->candidateId, 15);

        $attempt = $this->attemptService->startAttempt($this->examId, $this->candidateId);

        $minutesGranted = (strtotime($attempt['server_end']) - strtotime($attempt['server_start'])) / 60;
        self::assertSame(45, (int) round($minutesGranted));
    }

    public function testOverridesAreCumulativeNotAReset(): void
    {
        $this->overrides->addExtraAttempts($this->examId, $this->candidateId, 1);
        $this->overrides->addExtraAttempts($this->examId, $this->candidateId, 2);

        self::assertSame(3, $this->overrides->find($this->examId, $this->candidateId)['extra_attempts']);
    }
}
