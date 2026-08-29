<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Core;

use WPCBTPro\Camera\VerificationAdminController;
use WPCBTPro\Candidates\CandidatesAdminController;
use WPCBTPro\DSA\DsaQuestionsAdminController;
use WPCBTPro\Exams\ExamsAdminController;
use WPCBTPro\Import\Word\WordImportAdminController;
use WPCBTPro\Programming\ProgrammingQuestionsAdminController;
use WPCBTPro\Questions\McqQuestionsAdminController;
use WPCBTPro\Results\ResultsAdminController;

/**
 * Guards against the bug this test was written after: every one of these
 * controllers processed its form POST (and issued wp_safe_redirect() on
 * success) from directly inside the callback WordPress registers via
 * add_submenu_page() — but WordPress always streams the admin page's HTML
 * header before calling that callback, so the redirect's header() call
 * silently failed ("headers already sent") and the admin was left looking
 * at a blank page after every single save. No unit or REST-level
 * integration test ever caught it, because none of them go through
 * WordPress's actual wp-admin/admin.php dispatch — only a real browser
 * request does. Fixed by moving all POST/delete handling to an admin_init
 * hook, which fires before any output starts.
 *
 * This test can't reproduce the "headers already sent" condition itself
 * (PHPUnit's CLI SAPI has no such constraint), so it guards the wiring
 * instead: if a future change removes a controller's admin_init
 * registration without also removing its wp_safe_redirect() call, the bug
 * comes back silently — this fails loudly.
 */
final class AdminRedirectWiringTest extends \WP_UnitTestCase
{
    /** @return array<string, array{0: class-string}> */
    public static function controllerProvider(): array
    {
        return [
            'candidates' => [CandidatesAdminController::class],
            'exams' => [ExamsAdminController::class],
            'dsa questions' => [DsaQuestionsAdminController::class],
            'programming questions' => [ProgrammingQuestionsAdminController::class],
            'mcq/true-false questions' => [McqQuestionsAdminController::class],
            'verification review' => [VerificationAdminController::class],
            'results (release)' => [ResultsAdminController::class],
            'word import' => [WordImportAdminController::class],
        ];
    }

    /**
     * @dataProvider controllerProvider
     * @param class-string $controllerClass
     */
    public function testControllerRegistersAdminInitBeforeAnyOutput(string $controllerClass): void
    {
        self::assertTrue(
            method_exists($controllerClass, 'register') && method_exists($controllerClass, 'maybeProcessRequest'),
            "{$controllerClass} must expose register()/maybeProcessRequest() so its POST handling runs on admin_init, not inside the add_submenu_page() render callback."
        );

        $controller = \WPCBTPro\Core\Plugin::instance()->container()->get($controllerClass);
        $controller->register();

        self::assertNotFalse(
            has_action('admin_init', [$controller, 'maybeProcessRequest']),
            "{$controllerClass}::register() did not hook maybeProcessRequest() to admin_init."
        );
    }
}
