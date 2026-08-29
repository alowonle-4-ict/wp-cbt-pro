<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Monitoring;

use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Attempts\CandidateExamOverrideRepository;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Monitoring\InvigilatorDashboardController;

/**
 * Calls maybeProcessRequest() directly rather than firing the full
 * admin_init action, for the same reason CandidateLoginControllerTest
 * calls maybeProcessLogin() directly: admin_init carries unrelated core
 * and other-plugin hooks that would make this flaky for reasons that have
 * nothing to do with this controller. The redirect is still exercised —
 * caught via the wp_redirect filter rather than letting it exit().
 */
final class InvigilatorAttemptActionsTest extends \WP_UnitTestCase
{
    private InvigilatorDashboardController $controller;
    private AttemptRepository $attempts;
    private AttemptService $attemptService;
    private int $examId;
    private int $candidateId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = Plugin::instance()->container();
        $this->controller = $container->get(InvigilatorDashboardController::class);
        $this->attempts = $container->get(AttemptRepository::class);
        $this->attemptService = $container->get(AttemptService::class);

        // Administrators hold every WPCBTPro capability (Capabilities::register()) —
        // simplest way to exercise both the REVIEW_ATTEMPTS-gated and
        // MANAGE_CBT_EXAMS-gated actions in the same test.
        $adminId = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($adminId);

        $institutionId = (int) get_option('wpcbtpro_default_institution_id');
        $candidateUserId = self::factory()->user->create();
        $this->candidateId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'wp_user_id' => $candidateUserId,
            'candidate_ref' => 'CBT-2026-000970',
            'first_name' => 'Live',
            'last_name' => 'Candidate',
            'status' => 'active',
        ]);

        $this->examId = (new ExamRepository())->insert([
            'institution_id' => $institutionId,
            'name' => 'Invigilator Action Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
        ]);
    }

    private function dispatchAction(string $action, int $attemptId, string $nonceAction, array $extra = []): string
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET['page'] = 'wpcbtpro-invigilator';
        $_POST = array_merge([
            'wpcbtpro_action' => $action,
            'attempt_id' => (string) $attemptId,
            '_wpnonce' => wp_create_nonce($nonceAction),
        ], $extra);
        // check_admin_referer() reads $_REQUEST, not $_POST directly — PHP only
        // merges those automatically at the start of a real request, not mid-test.
        $_REQUEST = array_merge($_GET, $_POST);

        $caughtLocation = '';
        $filter = function (string $location) use (&$caughtLocation): string {
            $caughtLocation = $location;
            throw new \Exception('redirect-intercepted');
        };
        add_filter('wp_redirect', $filter);

        try {
            $this->controller->maybeProcessRequest();
        } catch (\Exception $e) {
            self::assertSame('redirect-intercepted', $e->getMessage());
        } finally {
            remove_filter('wp_redirect', $filter);
            unset($_GET['page'], $_SERVER['REQUEST_METHOD']);
            $_POST = [];
            $_REQUEST = [];
        }

        return $caughtLocation;
    }

    public function testSuspendPausesAnInProgressAttempt(): void
    {
        $attempt = $this->attemptService->startAttempt($this->examId, $this->candidateId);

        $location = $this->dispatchAction('suspend', (int) $attempt['id'], 'wpcbtpro_suspend_attempt_' . $attempt['id']);

        self::assertStringContainsString('done=suspended', $location);
        self::assertSame('paused', $this->attempts->find((int) $attempt['id'])['status']);
    }

    public function testResumeReactivatesAPausedAttempt(): void
    {
        $attempt = $this->attemptService->startAttempt($this->examId, $this->candidateId);
        $this->attempts->update((int) $attempt['id'], ['status' => 'paused']);

        $location = $this->dispatchAction('resume', (int) $attempt['id'], 'wpcbtpro_resume_attempt_' . $attempt['id']);

        self::assertStringContainsString('done=resumed', $location);
        self::assertSame('in_progress', $this->attempts->find((int) $attempt['id'])['status']);
    }

    public function testExtendTimePushesBackTheDeadlineAndPersistsAnOverride(): void
    {
        $attempt = $this->attemptService->startAttempt($this->examId, $this->candidateId);
        $originalEnd = strtotime($attempt['server_end']);

        $this->dispatchAction('extend_time', (int) $attempt['id'], 'wpcbtpro_extend_time_' . $attempt['id'], ['extra_minutes' => '20']);

        $updated = $this->attempts->find((int) $attempt['id']);
        self::assertSame($originalEnd + 20 * MINUTE_IN_SECONDS, strtotime($updated['server_end']));

        $override = Plugin::instance()->container()->get(CandidateExamOverrideRepository::class)->find($this->examId, $this->candidateId);
        self::assertSame(20, $override['extra_minutes']);
    }

    public function testGrantAttemptLetsTheCandidateStartASecondAttemptAfterUsingTheFirst(): void
    {
        $exam = (new ExamRepository())->find($this->examId);
        $first = $this->attemptService->startAttempt($this->examId, $this->candidateId);
        $this->attemptService->submitAttempt($exam, $first);

        $this->dispatchAction('grant_attempt', (int) $first['id'], 'wpcbtpro_grant_attempt_' . $first['id']);

        $second = $this->attemptService->startAttempt($this->examId, $this->candidateId);
        self::assertNotSame((int) $first['id'], (int) $second['id']);
    }
}
