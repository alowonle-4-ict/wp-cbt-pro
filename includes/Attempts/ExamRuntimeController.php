<?php

declare(strict_types=1);

namespace WPCBTPro\Attempts;

use WPCBTPro\Camera\VerificationRepository;
use WPCBTPro\Candidates\CandidateLoginController;
use WPCBTPro\Candidates\CurrentCandidateResolver;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Monitoring\MonitoringEventRepository;
use WPCBTPro\Questions\Registry\QuestionTypeRegistry;
use WPCBTPro\REST\RestServiceProvider;
use WPCBTPro\Results\ResultRepository;

/**
 * The [wpcbtpro_exam id="N"] shortcode. Every state transition (start,
 * answer, navigate, submit) is a plain form POST handled here server-side
 * first — the JS layer (public/js/exam.js) only adds continuous autosave
 * and a live countdown on top; the exam is fully usable with JavaScript
 * disabled (§42, §44).
 */
final class ExamRuntimeController
{
    // Maps exam id => the id of the page that embeds it, so a candidate can
    // be sent straight to "their" exam after logging in (see
    // CandidateExamFinder) without an admin having to configure that link
    // separately — self-registered the same way CandidateLoginController
    // remembers its own page, the first time each exam's shortcode renders
    // on a real page.
    private const EXAM_PAGES_OPTION = 'wpcbtpro_exam_pages';

    public function __construct(
        private readonly CurrentCandidateResolver $candidateResolver,
        private readonly AttemptService $attemptService,
        private readonly AttemptRepository $attemptRepository,
        private readonly ExamRepository $examRepository,
        private readonly AnswerRepository $answerRepository,
        private readonly ResultRepository $resultRepository,
        private readonly QuestionTypeRegistry $registry,
        private readonly VerificationRepository $verificationRepository,
        private readonly MonitoringEventRepository $monitoringEvents,
    ) {
    }

    public function register(): void
    {
        add_shortcode('wpcbtpro_exam', [$this, 'renderShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'maybeEnqueueAssets']);
    }

    public function renderShortcode($atts): string
    {
        $atts = shortcode_atts(['id' => 0], $atts, 'wpcbtpro_exam');
        $examId = (int) $atts['id'];

        if ($examId === 0) {
            return '<p class="wpcbtpro-notice">' . esc_html__('No exam was specified.', 'wp-cbt-pro') . '</p>';
        }

        if (is_singular()) {
            $pages = get_option(self::EXAM_PAGES_OPTION);
            $pages = is_array($pages) ? $pages : [];
            if (($pages[$examId] ?? null) !== get_the_ID()) {
                $pages[$examId] = get_the_ID();
                update_option(self::EXAM_PAGES_OPTION, $pages);
            }
        }

        ob_start();
        $this->render($examId);
        return (string) ob_get_clean();
    }

    /**
     * The URL of the page embedding [wpcbtpro_exam id="$examId"], if that
     * page has ever been viewed (which is how it gets registered above) and
     * is still published — null otherwise, e.g. an exam nobody has embedded
     * anywhere yet, or whose page was later unpublished.
     */
    public static function examUrl(int $examId): ?string
    {
        $pages = get_option(self::EXAM_PAGES_OPTION);
        $pageId = is_array($pages) ? ($pages[$examId] ?? null) : null;
        if ($pageId === null) {
            return null;
        }

        $page = get_post((int) $pageId);
        if ($page === null || $page->post_status !== 'publish') {
            return null;
        }

        $url = get_permalink((int) $pageId);
        return $url !== false ? $url : null;
    }

    private function render(int $examId): void
    {
        if (!is_user_logged_in()) {
            $this->renderLoginPrompt();
            return;
        }

        $candidate = $this->candidateResolver->resolve();
        if ($candidate === null) {
            echo '<p class="wpcbtpro-notice">' . esc_html__(
                'Your account is not registered as an exam candidate. Please contact your institution.',
                'wp-cbt-pro'
            ) . '</p>';
            return;
        }

        $exam = $this->examRepository->find($examId);
        if ($exam === null || $exam['status'] !== 'active') {
            echo '<p class="wpcbtpro-notice">' . esc_html__('This exam is not currently available.', 'wp-cbt-pro') . '</p>';
            return;
        }

        $startError = null;
        if ($this->isPostback('wpcbtpro_start_nonce')) {
            check_admin_referer('wpcbtpro_start_exam_' . $examId, 'wpcbtpro_start_nonce');

            $requiresConsent = !empty($exam['camera_required']) || !empty($exam['identity_verification']);
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- check_admin_referer() above already verified this request; comparing a fixed '1' literal needs no further sanitization.
            if ($requiresConsent && (wp_unslash($_POST['consent_given'] ?? '0')) !== '1') {
                $startError = __('You must complete the system check and accept the monitoring policy to continue.', 'wp-cbt-pro');
            } else {
                try {
                    $newAttempt = $this->attemptService->startAttempt($examId, (int) $candidate['id']);
                    if ($requiresConsent) {
                        $this->monitoringEvents->record((int) $newAttempt['id'], 'CONSENT_GIVEN');
                    }
                } catch (\RuntimeException $e) {
                    $startError = $e->getMessage();
                }
            }
        }

        $attempt = $this->attemptRepository->findActive($examId, (int) $candidate['id']);

        if ($attempt === null) {
            $attemptsUsed = $this->attemptRepository->countForCandidateExam($examId, (int) $candidate['id']);
            $this->renderStart($exam, $candidate, $attemptsUsed, $startError);
            return;
        }

        if (in_array($attempt['status'], ['in_progress', 'paused'], true) && $this->attemptService->isExpired($attempt)) {
            $this->attemptService->submitAttempt($exam, $attempt);
            $attempt = $this->attemptRepository->find((int) $attempt['id']);
        }

        if ($attempt['status'] === 'paused') {
            $this->renderPaused($exam, $candidate, $attempt);
            return;
        }

        if ($attempt['status'] !== 'in_progress') {
            $this->renderSubmitted($exam, $attempt);
            return;
        }

        if (!empty($exam['identity_verification']) && $this->verificationRepository->findLatestByAttempt((int) $attempt['id']) === null) {
            $this->renderVerify($exam, $candidate, $attempt);
            return;
        }

        if ($this->isPostback('wpcbtpro_submit_exam_nonce')) {
            check_admin_referer('wpcbtpro_submit_exam_' . $attempt['id'], 'wpcbtpro_submit_exam_nonce');
            $this->attemptService->submitAttempt($exam, $attempt);
            $attempt = $this->attemptRepository->find((int) $attempt['id']);
            $this->renderSubmitted($exam, $attempt);
            return;
        }

        $total = count($this->attemptService->resolvedQuestionIds($exam, $attempt));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which question index to display, not a state change.
        $currentIndex = max(0, isset($_GET['q']) ? absint($_GET['q']) : 0);
        $answerError = null;

        if ($this->isPostback('wpcbtpro_answer_nonce')) {
            check_admin_referer('wpcbtpro_save_answer_' . $attempt['id'], 'wpcbtpro_answer_nonce');

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- check_admin_referer() above already verified this request.
            $questionId = isset($_POST['question_id']) ? absint($_POST['question_id']) : 0;
            $nav = sanitize_text_field(wp_unslash($_POST['wpcbtpro_nav'] ?? ''));
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- shape varies per question type (an option id, a DSA JSON state, raw source code); type->validator() below does the type-appropriate validation, so a generic sanitizer here would corrupt legitimate answers (e.g. programming source).
            $rawAnswer = $nav === 'clear' ? '' : wp_unslash($_POST['wpcbtpro_answer'] ?? '');
            $marked = !empty($_POST['wpcbtpro_marked_for_review']);

            $result = $this->attemptService->saveAnswer($exam, $attempt, $questionId, $rawAnswer, $marked);
            if (!$result['ok']) {
                $answerError = implode(' ', $result['errors']);
            }

            $currentIndex = $this->resolveNavTarget($nav === 'clear' ? '' : $nav, $currentIndex, $total);
        }

        $this->renderQuestion($exam, $candidate, $attempt, $currentIndex, $answerError);
    }

    private function isPostback(string $nonceField): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- every caller runs check_admin_referer() immediately after; this only decides whether to.
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST[$nonceField]);
    }

    private function resolveNavTarget(string $nav, int $current, int $total): int
    {
        $last = max(0, $total - 1);

        if ($nav === 'prev') {
            return max(0, $current - 1);
        }
        if ($nav === 'next') {
            return min($last, $current + 1);
        }
        if (str_starts_with($nav, 'index:')) {
            return max(0, min($last, (int) substr($nav, 6)));
        }

        return $current;
    }

    private function renderLoginPrompt(): void
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- the result is passed through esc_url() below before output; this is the standard wp_login_url() redirect-back pattern.
        $redirect = home_url(add_query_arg([], wp_unslash($_SERVER['REQUEST_URI'] ?? '')));
        $message = sprintf(
            /* translators: %s: login URL */
            __('Please <a href="%s">log in</a> to access this exam.', 'wp-cbt-pro'),
            esc_url(CandidateLoginController::loginUrl($redirect))
        );

        echo '<p class="wpcbtpro-notice">' . wp_kses($message, ['a' => ['href' => true]]) . '</p>';
    }

    private function renderStart(array $exam, array $candidate, int $attemptsUsed, ?string $error): void
    {
        include WPCBTPRO_PATH . 'public/views/exam-start.php';
    }

    private function renderVerify(array $exam, array $candidate, array $attempt): void
    {
        include WPCBTPRO_PATH . 'public/views/exam-verify.php';
    }

    private function renderPaused(array $exam, array $candidate, array $attempt): void
    {
        include WPCBTPRO_PATH . 'public/views/exam-paused.php';
    }

    private function renderQuestion(array $exam, array $candidate, array $attempt, int $index, ?string $error): void
    {
        $resolvedIds = $this->attemptService->resolvedQuestionIds($exam, $attempt);
        $total = count($resolvedIds);
        $index = max(0, min($total - 1, $index));

        $question = $this->attemptService->questionAt($exam, $attempt, $index);
        $type = $question !== null && $this->registry->has($question['type'])
            ? $this->registry->get($question['type'])
            : null;

        $answers = $this->answerRepository->allForAttempt((int) $attempt['id']);
        $questionId = (int) ($question['id'] ?? 0);
        $currentAnswer = $answers[$questionId]['value'] ?? null;
        $markedForReview = !empty($answers[$questionId]['marked_for_review']);

        include WPCBTPRO_PATH . 'public/views/exam-question.php';
    }

    private function renderSubmitted(array $exam, array $attempt): void
    {
        $result = $this->resultRepository->findByAttempt((int) $attempt['id']);
        include WPCBTPRO_PATH . 'public/views/exam-submitted.php';
    }

    public function maybeEnqueueAssets(): void
    {
        global $post;
        if (!($post instanceof \WP_Post) || !has_shortcode($post->post_content, 'wpcbtpro_exam')) {
            return;
        }

        wp_enqueue_style('wpcbtpro-exam', WPCBTPRO_URL . 'public/css/exam.css', [], WPCBTPRO_VERSION);
        wp_enqueue_script('wpcbtpro-exam', WPCBTPRO_URL . 'public/js/exam.js', [], WPCBTPRO_VERSION, true);
        wp_enqueue_script('wpcbtpro-camera', WPCBTPRO_URL . 'public/js/camera.js', [], WPCBTPRO_VERSION, true);
        wp_enqueue_script('wpcbtpro-code-editor', WPCBTPRO_URL . 'public/js/code-editor.js', [], WPCBTPRO_VERSION, true);
        wp_enqueue_script('wpcbtpro-dsa', WPCBTPRO_URL . 'public/js/dsa.js', [], WPCBTPRO_VERSION, true);

        $monacoLoaderSrc = apply_filters('wpcbtpro_monaco_loader_src', 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js');
        wp_add_inline_script(
            'wpcbtpro-code-editor',
            'window.wpcbtproMonacoLoaderSrc = ' . wp_json_encode($monacoLoaderSrc) . ';',
            'before'
        );
        wp_localize_script('wpcbtpro-exam', 'wpcbtproExam', [
            'restUrl' => esc_url_raw(rest_url(RestServiceProvider::NAMESPACE_V1)),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => [
                'saving' => __('Saving…', 'wp-cbt-pro'),
                'saved' => __('Saved', 'wp-cbt-pro'),
                'offline' => __('Not saved — check your connection', 'wp-cbt-pro'),
            ],
            'cameraStrings' => [
                'checking' => __('Checking…', 'wp-cbt-pro'),
                'granted' => __('Camera ready', 'wp-cbt-pro'),
                'denied' => __('Camera access was denied. Enable it in your browser settings and reload this page.', 'wp-cbt-pro'),
                'notFound' => __('No camera was found on this device.', 'wp-cbt-pro'),
                'error' => __('Could not access the camera.', 'wp-cbt-pro'),
                'disconnected' => __('Camera connection lost — attempting to reconnect…', 'wp-cbt-pro'),
                'reconnected' => __('Camera reconnected.', 'wp-cbt-pro'),
                'unsupported' => __('This browser does not support camera access.', 'wp-cbt-pro'),
                'insecure' => __('Camera access requires a secure (HTTPS) connection.', 'wp-cbt-pro'),
            ],
        ]);
    }
}
