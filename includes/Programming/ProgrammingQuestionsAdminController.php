<?php

declare(strict_types=1);

namespace WPCBTPro\Programming;

use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Questions\Types\Programming\ProgrammingAdminEditor;
use WPCBTPro\Security\AuditLogger;
use WPCBTPro\Security\Capabilities;

/**
 * A dedicated builder for programming questions rather than a fully
 * generic multi-type Question Bank screen (that remains a documented gap
 * — see the folder-level notes). It still reuses ProgrammingAdminEditor's
 * render()/extract() exactly as the §5 AdminEditorView contract intends;
 * it just knows, concretely, that it's always talking to type 'programming'.
 */
final class ProgrammingQuestionsAdminController
{
    /**
     * A POST/delete on this page is processed on admin_init — before
     * WordPress starts streaming the admin page's HTML — because
     * wp_safe_redirect() from inside the add_submenu_page() render
     * callback itself is always too late: WP has already sent the page
     * header by the time that callback runs, so the redirect silently
     * fails ("headers already sent") and the admin is left looking at a
     * blank page. render() only ever displays; it never mutates or redirects.
     *
     * @var array<string, string>|null
     */
    private ?array $pendingErrors = null;
    private ?string $pendingAction = null;

    public function __construct(
        private readonly QuestionRepository $questions,
        private readonly ProgrammingQuestionRepository $programmingQuestions,
        private readonly ProgrammingAdminEditor $editor,
        private readonly InstitutionContext $institutionContext,
    ) {
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'maybeProcessRequest']);
    }

    public function maybeProcessRequest(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only: confirms this hook applies to our own page before doing anything.
        if (($_GET['page'] ?? '') !== 'wpcbtpro-programming') {
            return;
        }

        if (!current_user_can(Capabilities::MANAGE_PROGRAMMING_QUESTIONS)) {
            return; // render() will wp_die() with the proper message for a real page view.
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only routing; the real check happens in handleDelete()/handleSave() below.
        if (($_GET['action'] ?? '') === 'delete') {
            $this->handleDelete();
            return;
        }

        // handleSave() runs check_admin_referer() as its first statement; the reads below only decide whether to dispatch there.
        // phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_programming_nonce'])) {
            $errors = $this->handleSave();
            if ($errors !== []) {
                $this->pendingErrors = $errors;
                $this->pendingAction = empty($_POST['question_id']) ? 'new' : 'edit';
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_PROGRAMMING_QUESTIONS)) {
            wp_die(esc_html__('You do not have permission to manage programming questions.', 'wp-cbt-pro'));
        }

        $errors = $this->pendingErrors ?? [];
        $action = $this->pendingAction ?? sanitize_key($_GET['action'] ?? 'list');

        if (in_array($action, ['new', 'edit'], true)) {
            $this->renderForm($action, $errors);
            return;
        }

        $this->renderList();
    }

    private function renderList(): void
    {
        $institutionId = $this->institutionContext->currentId();
        $questions = $this->questions->paginate([
            'institution_id' => $institutionId,
            'per_page' => 100,
        ]);
        $questions = array_values(array_filter($questions, static fn (array $q): bool => $q['type'] === 'programming'));

        $addUrl = add_query_arg(['page' => 'wpcbtpro-programming', 'action' => 'new'], admin_url('admin.php'));

        include WPCBTPRO_PATH . 'admin/views/programming-list.php';
    }

    private function renderForm(string $action, array $errors): void
    {
        $question = null;

        if ($action === 'edit') {
            // phpcs:ignore WordPress.Security.NonceVerification -- read-only: resolves which record to display, not a state change.
            $id = isset($_GET['id']) ? absint($_GET['id']) : (isset($_POST['question_id']) ? absint($_POST['question_id']) : 0);
            $question = $this->questions->find($id);
            if ($question === null || $question['type'] !== 'programming') {
                wp_die(esc_html__('Programming question not found.', 'wp-cbt-pro'));
            }
        }

        $editor = $this->editor;

        include WPCBTPRO_PATH . 'admin/views/programming-form.php';
    }

    /** @return array<string, string> */
    private function handleSave(): array
    {
        check_admin_referer('wpcbtpro_save_programming', 'wpcbtpro_programming_nonce');

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput -- (int)/(float) casts below are the sanitization.
        $id = isset($_POST['question_id']) ? absint($_POST['question_id']) : 0;
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $marks = (float) ($_POST['marks'] ?? 0);
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput

        $errors = [];
        if (trim(wp_strip_all_tags($content)) === '') {
            $errors['content'] = __('A problem statement is required.', 'wp-cbt-pro');
        }
        if ($marks <= 0) {
            $errors['marks'] = __('Marks must be greater than zero.', 'wp-cbt-pro');
        }

        $extracted = $this->editor->extract(wp_unslash($_POST));
        if (count($extracted['programming']['test_cases']) === 0) {
            $errors['test_cases'] = __('At least one test case is required.', 'wp-cbt-pro');
        }

        if ($errors !== []) {
            return $errors;
        }

        $coreData = [
            'type' => 'programming',
            'content' => $content,
            'subject' => sanitize_text_field(wp_unslash($_POST['subject'] ?? '')),
            'topic' => sanitize_text_field(wp_unslash($_POST['topic'] ?? '')),
            'marks' => $marks,
            'negative_marks' => 0,
            'status' => 'active',
        ];

        if ($id === 0) {
            $coreData['institution_id'] = $this->institutionContext->requireCurrentId();
            $coreData['author_id'] = get_current_user_id();
            $id = $this->questions->insert($coreData);
            AuditLogger::record('question.created', 'question', $id, ['type' => 'programming']);
        } else {
            $this->questions->update($id, $coreData);
            AuditLogger::record('question.updated', 'question', $id);
        }

        $programming = $extracted['programming'];
        $this->programmingQuestions->upsert($id, [
            'language' => $programming['language'],
            'starter_code' => $programming['starter_code'],
            'entry_point' => $programming['entry_point'],
            'time_limit_ms' => $programming['time_limit_ms'],
            'memory_limit_mb' => $programming['memory_limit_mb'],
        ]);
        $this->programmingQuestions->replaceTestCases($id, $programming['test_cases']);

        wp_safe_redirect(add_query_arg([
            'page' => 'wpcbtpro-programming',
            'action' => 'edit',
            'id' => $id,
            'saved' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    private function handleDelete(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the id is only used to build the nonce action string; check_admin_referer() below rejects any tampering.
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('wpcbtpro_delete_programming_' . $id);

        $this->questions->delete($id);
        AuditLogger::record('question.deleted', 'question', $id);

        wp_safe_redirect(add_query_arg(['page' => 'wpcbtpro-programming', 'deleted' => 1], admin_url('admin.php')));
        exit;
    }
}
