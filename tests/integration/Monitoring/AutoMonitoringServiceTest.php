<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Monitoring;

use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionRepository;
use WPCBTPro\Monitoring\AutoMonitoringService;
use WPCBTPro\Monitoring\Contracts\MonitoringViolationType;
use WPCBTPro\Monitoring\MonitoringEventRepository;
use WPCBTPro\Questions\QuestionRepository;

/**
 * "Warn 3 times, then submit" — every violation kind counts toward one
 * shared total per attempt; the 3rd submits the attempt for real, through
 * the same AttemptService::submitAttempt() every other submission path
 * uses, not a separate auto-fail code path.
 */
final class AutoMonitoringServiceTest extends \WP_UnitTestCase
{
    /** @return array{exam:array<string,mixed>, attempt:array<string,mixed>} */
    private function makeInProgressAttempt(): array
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();

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
        $examId = $examRepository->insert([
            'institution_id' => $institutionId,
            'name' => 'Auto Monitoring Test Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
            'result_visibility' => 'immediate',
            'camera_required' => 1,
            'auto_monitoring_enabled' => 1,
        ]);
        $examRepository->setQuestions($examId, [['question_id' => $questionId]]);

        $candidateId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-MONITOR-' . wp_generate_password(6, false, false),
            'first_name' => 'Monitor',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        /** @var AttemptService $attemptService */
        $attemptService = Plugin::instance()->container()->get(AttemptService::class);
        $attempt = $attemptService->startAttempt($examId, $candidateId);

        $exam = $examRepository->find($examId);

        return ['exam' => $exam, 'attempt' => $attempt];
    }

    public function testFirstAndSecondViolationsWarnWithoutSubmitting(): void
    {
        $fixture = $this->makeInProgressAttempt();

        /** @var AutoMonitoringService $service */
        $service = Plugin::instance()->container()->get(AutoMonitoringService::class);

        $first = $service->recordViolation($fixture['exam'], $fixture['attempt'], MonitoringViolationType::NoFaceDetected->value);
        self::assertSame(1, $first['strikes']);
        self::assertFalse($first['submitted']);
        self::assertStringContainsString('1', $first['message']);

        $second = $service->recordViolation($fixture['exam'], $fixture['attempt'], MonitoringViolationType::FaceMismatch->value);
        self::assertSame(2, $second['strikes']);
        self::assertFalse($second['submitted']);

        $attemptRepository = new AttemptRepository();
        $stillInProgress = $attemptRepository->find((int) $fixture['attempt']['id']);
        self::assertSame('in_progress', $stillInProgress['status']);
    }

    public function testThirdViolationSubmitsTheAttemptForReal(): void
    {
        $fixture = $this->makeInProgressAttempt();

        /** @var AutoMonitoringService $service */
        $service = Plugin::instance()->container()->get(AutoMonitoringService::class);

        $service->recordViolation($fixture['exam'], $fixture['attempt'], MonitoringViolationType::NoFaceDetected->value);
        $service->recordViolation($fixture['exam'], $fixture['attempt'], MonitoringViolationType::NoFaceDetected->value);
        $third = $service->recordViolation($fixture['exam'], $fixture['attempt'], MonitoringViolationType::FaceMismatch->value);

        self::assertSame(3, $third['strikes']);
        self::assertTrue($third['submitted']);

        $attemptRepository = new AttemptRepository();
        $submitted = $attemptRepository->find((int) $fixture['attempt']['id']);
        self::assertSame('submitted', $submitted['status'], 'The attempt should really be submitted, not just flagged.');
        self::assertNotEmpty($submitted['submitted_at']);
    }

    public function testDifferentViolationKindsShareTheSameStrikeCount(): void
    {
        $fixture = $this->makeInProgressAttempt();

        /** @var AutoMonitoringService $service */
        $service = Plugin::instance()->container()->get(AutoMonitoringService::class);

        $service->recordViolation($fixture['exam'], $fixture['attempt'], MonitoringViolationType::FaceMismatch->value);
        $service->recordViolation($fixture['exam'], $fixture['attempt'], MonitoringViolationType::NoFaceDetected->value);

        // A camera disconnect (already logged by the /camera-event pipeline
        // before evaluateStrikes() runs, so simulate that logging step here
        // the same way CameraRestController does) should count toward the
        // same total, not a separate one.
        $events = new MonitoringEventRepository();
        $events->record((int) $fixture['attempt']['id'], 'CAMERA_DISCONNECTED');

        $result = $service->evaluateStrikes($fixture['exam'], $fixture['attempt']);
        self::assertSame(3, $result['strikes']);
        self::assertTrue($result['submitted']);
    }

    public function testEvaluateStrikesDoesNotDoubleLogTheEventItEvaluates(): void
    {
        $fixture = $this->makeInProgressAttempt();
        $attemptId = (int) $fixture['attempt']['id'];

        $events = new MonitoringEventRepository();
        $events->record($attemptId, 'CAMERA_DISCONNECTED');

        /** @var AutoMonitoringService $service */
        $service = Plugin::instance()->container()->get(AutoMonitoringService::class);
        $service->evaluateStrikes($fixture['exam'], $fixture['attempt']);

        self::assertCount(1, $events->allForAttempt($attemptId), 'evaluateStrikes() must not log a duplicate event for something already logged elsewhere.');
    }
}
