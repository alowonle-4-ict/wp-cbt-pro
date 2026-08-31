<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Candidates;

use WPCBTPro\Candidates\CandidateLoginController;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionRepository;

/**
 * Exercises the real shortcode + the controller's login handler together,
 * not just wp_signon() in isolation — login is deliberately processed on
 * template_redirect rather than inside the shortcode callback, because by
 * the time a shortcode runs the page has already started streaming HTML
 * (the same header-timing constraint the wp-admin controllers have, just
 * on the frontend). testHookedToTemplateRedirectNotTheShortcode() would
 * catch a regression back to doing it in the wrong hook; the other tests
 * call maybeProcessLogin() directly (the exact method/instance that hook
 * invokes) rather than firing the full template_redirect action, since
 * that action also carries unrelated WP core hooks (e.g. canonical
 * redirects) that would make the test flaky for reasons that have nothing
 * to do with this controller.
 */
final class CandidateLoginControllerTest extends \WP_UnitTestCase
{
    private CandidateLoginController $controller;
    private int $pageId;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var CandidateLoginController $controller */
        $controller = Plugin::instance()->container()->get(CandidateLoginController::class);
        $this->controller = $controller;

        $this->pageId = self::factory()->post->create([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_content' => '[wpcbtpro_login]',
        ]);

        $GLOBALS['post'] = get_post($this->pageId);
        $this->go_to(get_permalink($this->pageId));
    }

    public function testHookedToTemplateRedirectNotTheShortcode(): void
    {
        self::assertNotFalse(
            has_action('template_redirect', [$this->controller, 'maybeProcessLogin']),
            'Login must be processed on template_redirect, before the page has started rendering.'
        );
    }

    public function testShortcodeRegistersItselfAsTheLoginPageAndRendersAFormWhenLoggedOut(): void
    {
        $html = do_shortcode('[wpcbtpro_login]');

        self::assertStringContainsString('name="wpcbtpro_login_user"', $html);
        self::assertStringContainsString('name="wpcbtpro_login_pass"', $html);
        self::assertSame($this->pageId, (int) get_option('wpcbtpro_login_page_id'));

        $loginUrl = CandidateLoginController::loginUrl('https://example.org/exam');
        $expectedBase = explode('?', get_permalink($this->pageId))[0];
        self::assertStringStartsWith($expectedBase, $loginUrl, 'Once a login page exists, loginUrl() should point at it, not wp_login_url().');
    }

    public function testSuccessfulLoginAuthenticatesAndRedirectsToThePostedTarget(): void
    {
        self::factory()->user->create(['user_login' => 'candidate1', 'user_pass' => 'correct horse']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['wpcbtpro_login_nonce'] = wp_create_nonce('wpcbtpro_candidate_login');
        $_POST['wpcbtpro_login_user'] = 'candidate1';
        $_POST['wpcbtpro_login_pass'] = 'correct horse';
        $_POST['redirect_to'] = 'https://example.org/take-exam';

        $caughtLocation = null;
        add_filter('wp_redirect', function (string $location) use (&$caughtLocation): string {
            $caughtLocation = $location;
            throw new \Exception('redirect-intercepted');
        });

        try {
            $this->controller->maybeProcessLogin();
            self::fail('Expected the redirect to be intercepted.');
        } catch (\Exception $e) {
            self::assertSame('redirect-intercepted', $e->getMessage());
        }

        // wp_signon() deliberately doesn't set the current user for this same
        // request (its own docblock says so) — the redirect firing at all,
        // to the exact posted target, is the proof wp_signon() succeeded
        // rather than returning a WP_Error (the failure path below never
        // redirects, it redisplays the form with an error instead).
        self::assertSame('https://example.org/take-exam', $caughtLocation);
    }

    public function testWrongPasswordShowsAnErrorAndDoesNotAuthenticate(): void
    {
        self::factory()->user->create(['user_login' => 'candidate2', 'user_pass' => 'correct horse']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['wpcbtpro_login_nonce'] = wp_create_nonce('wpcbtpro_candidate_login');
        $_POST['wpcbtpro_login_user'] = 'candidate2';
        $_POST['wpcbtpro_login_pass'] = 'wrong password';

        $this->controller->maybeProcessLogin();

        self::assertFalse(is_user_logged_in());

        $html = do_shortcode('[wpcbtpro_login]');
        self::assertStringContainsString('Incorrect username/email or password', $html);
    }

    /**
     * The actual ask this covers: a candidate who goes straight to the bare
     * login page (no exam-page "please log in" link, so no redirect_to)
     * should still land on their exam afterward, not the homepage — as long
     * as there's exactly one exam available to send them to unambiguously.
     */
    public function testSuccessfulLoginWithNoRedirectTargetGoesStraightToTheOneAvailableExam(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();

        $examId = (new ExamRepository())->insert([
            'institution_id' => $institutionId,
            'name' => 'Only Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
            'result_visibility' => 'immediate',
        ]);

        $examPageId = self::factory()->post->create([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_content' => '[wpcbtpro_exam id="' . $examId . '"]',
        ]);
        $GLOBALS['post'] = get_post($examPageId);
        $this->go_to(get_permalink($examPageId));
        do_shortcode('[wpcbtpro_exam id="' . $examId . '"]'); // self-registers the exam's page URL

        // Back to the login page for the actual login request.
        $GLOBALS['post'] = get_post($this->pageId);
        $this->go_to(get_permalink($this->pageId));

        $userId = self::factory()->user->create(['user_login' => 'onlyexamcandidate', 'user_pass' => 'secret pass']);
        (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-LOGIN-' . wp_generate_password(6, false, false),
            'first_name' => 'Only',
            'last_name' => 'Exam',
            'status' => 'active',
            'wp_user_id' => $userId,
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['wpcbtpro_login_nonce'] = wp_create_nonce('wpcbtpro_candidate_login');
        $_POST['wpcbtpro_login_user'] = 'onlyexamcandidate';
        $_POST['wpcbtpro_login_pass'] = 'secret pass';
        $_POST['redirect_to'] = '';

        $caughtLocation = null;
        add_filter('wp_redirect', function (string $location) use (&$caughtLocation): string {
            $caughtLocation = $location;
            throw new \Exception('redirect-intercepted');
        });

        try {
            $this->controller->maybeProcessLogin();
            self::fail('Expected the redirect to be intercepted.');
        } catch (\Exception $e) {
            self::assertSame('redirect-intercepted', $e->getMessage());
        }

        $expectedUrl = explode('?', get_permalink($examPageId))[0];
        self::assertStringStartsWith(
            $expectedUrl,
            $caughtLocation,
            'With exactly one exam available and no explicit redirect target, login should go straight to that exam, not the homepage.'
        );
    }

    public function testSuccessfulLoginWithNoAvailableExamsStaysOnTheLoginPageInsteadOfGuessing(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();

        $userId = self::factory()->user->create(['user_login' => 'noexamcandidate', 'user_pass' => 'secret pass']);
        (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-LOGIN-' . wp_generate_password(6, false, false),
            'first_name' => 'No',
            'last_name' => 'Exam',
            'status' => 'active',
            'wp_user_id' => $userId,
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['wpcbtpro_login_nonce'] = wp_create_nonce('wpcbtpro_candidate_login');
        $_POST['wpcbtpro_login_user'] = 'noexamcandidate';
        $_POST['wpcbtpro_login_pass'] = 'secret pass';
        $_POST['redirect_to'] = '';

        $caughtLocation = null;
        add_filter('wp_redirect', function (string $location) use (&$caughtLocation): string {
            $caughtLocation = $location;
            throw new \Exception('redirect-intercepted');
        });

        try {
            $this->controller->maybeProcessLogin();
            self::fail('Expected the redirect to be intercepted.');
        } catch (\Exception $e) {
            self::assertSame('redirect-intercepted', $e->getMessage());
        }

        $expectedUrl = explode('?', get_permalink($this->pageId))[0];
        self::assertStringStartsWith($expectedUrl, $caughtLocation);
    }
}
