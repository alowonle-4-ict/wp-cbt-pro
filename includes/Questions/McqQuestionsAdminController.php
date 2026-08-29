<?php

declare(strict_types=1);

namespace WPCBTPro\Questions;

use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Questions\Registry\QuestionTypeRegistry;
use WPCBTPro\Security\AuditLogger;
use WPCBTPro\Security\Capabilities;

/**
 * The admin "Questions" screen for the two Objective-category types (single
 * choice and true/false) — the one gap left after DSA and Programming each
 * got their own dedicated builder: MCQ/True-False questions could only be
 * created through the Word importer, never added by hand.
 *
 * Single choice and true/false share this one controller, rather than each
 * getting its own like DSA/Programming, because they share the same
 * question_options-backed shape and QuestionTypeRegistry::groupedByCategory()
 * already exists specifically to support an "Add Question" type picker like
 * this one.
 */
final class McqQuestionsAdminController
{
    private const TYPES = ['mcq_single', 'true_false'];
    private const DEFAULT_TYPE = 'mcq_single';

    /**
     * A POST/delete on this page is processed on admin_init — before
     * WordPress starts streaming the admin page's HTML — because
     * wp_safe_redirect() from inside the add_submenu_page() render
     * callback itself is always too late: WP has already sent the page
     * header by the time that callback runs, so the redirect silently
     * fails ("headers already sent") and the candidate/admin is left
     * looking at a blank page. render() only ever displays; it never
     * mutates or redirects.
     *
     * @var array<string, string>|null
     */
    private ?array $pendingErrors = null;
    private ?string $pendingAction = null;

    public function __construct(
        private readonly QuestionRepository $questions,
        private readonly QuestionTypeRegistry $registry,
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
        if (($_GET['page'] ?? '') !== 'wpcbtpro-questions') {
            return;
        }

        if (!current_user_can(Capabilities::MANAGE_CBT_QUESTIONS)) {
            return; // render() will wp_die() with the proper message for a real page view.
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only routing; the real check happens in handleDelete()/handleSave() below.
        if (($_GET['action'] ?? '') === 'delete') {
            $this->handleDelete();
            return;
        }

        // handleSave() runs check_admin_referer() as its first statement; the reads below only decide whether to dispatch there.
        // phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_mcq_nonce'])) {
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
        if (!current_user_can(Capabilities::MANAGE_CBT_QUESTIONS)) {
            wp_die(esc_html__('You do not have permission to manage questions.', 'wp-cbt-pro'));
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
        $questions = $this->questions->paginate(['institution_id' => $institutionId, 'per_page' => 200]);
        $questions = array_values(array_filter(
            $questions,
            static fn (array $q): bool => in_array($q['type'], self::TYPES, true)
        ));

        $typeLabels = [
            'mcq_single' => $this->registry->get('mcq_single')->label(),
            'true_false' => $this->registry->get('true_false')->label(),
        ];

        $addSingleChoiceUrl = add_query_arg(['page' => 'wpcbtpro-questions', 'action' => 'new', 'type' => 'mcq_single'], admin_url('admin.php'));
        $addTrueFalseUrl = add_query_arg(['page' => 'wpcbtpro-questions', 'action' => 'new', 'type' => 'true_false'], admin_url('admin.php'));

        include WPCBTPRO_PATH . 'admin/views/mcq-list.php';
    }

    private function renderForm(string $action, array $errors): void
    {
        $question = null;
        $type = self::DEFAULT_TYPE;

        if ($action === 'edit') {
            // phpcs:ignore WordPress.Security.NonceVerification -- read-only: resolves which record to display, not a state change.
            $id = isset($_GET['id']) ? absint($_GET['id']) : (isset($_POST['question_id']) ? absint($_POST['question_id']) : 0);
            $question = $this->questions->find($id);
            if ($question === null || !in_array($question['type'], self::TYPES, true)) {
                wp_die(esc_html__('Question not found.', 'wp-cbt-pro'));
            }
            $type = $question['type'];
        } else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which type's "add" form to render, not a state change.
            $requested = isset($_GET['type']) ? sanitize_key($_GET['type']) : self::DEFAULT_TYPE;
            $type = in_array($requested, self::TYPES, true) ? $requested : self::DEFAULT_TYPE;
        }

        $editor = $this->registry->get($type)->adminEditor();
        $typeLabel = $this->registry->get($type)->label();

        include WPCBTPRO_PATH . 'admin/views/mcq-form.php';
    }

    /** @return array<string, string> validation errors, empty on success */
    private function handleSave(): array
    {
        check_admin_referer('wpcbtpro_save_mcq', 'wpcbtpro_mcq_nonce');

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput -- absint()/sanitize_key() below are the sanitization.
        $id = isset($_POST['question_id']) ? absint($_POST['question_id']) : 0;
        $postedType = isset($_POST['type']) ? sanitize_key($_POST['type']) : self::DEFAULT_TYPE;
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput

        $existing = $id !== 0 ? $this->questions->find($id) : null;
        if ($id !== 0 && ($existing === null || !in_array($existing['type'], self::TYPES, true))) {
            wp_die(esc_html__('Question not found.', 'wp-cbt-pro'));
        }

        // A question's type is fixed at creation — never trust the posted type for an edit, only for a new question.
        $type = $existing !== null ? $existing['type'] : (in_array($postedType, self::TYPES, true) ? $postedType : self::DEFAULT_TYPE);

        $editor = $this->registry->get($type)->adminEditor();

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput -- (float) casts below are the sanitization; content is wp_kses_post()'d.
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $marks = (float) ($_POST['marks'] ?? 0);
        $negativeMarks = (float) ($_POST['negative_marks'] ?? 0);
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput

        $errors = [];
        if (trim(wp_strip_all_tags($content)) === '') {
            $errors['content'] = __('A question prompt is required.', 'wp-cbt-pro');
        }
        if ($marks <= 0) {
            $errors['marks'] = __('Marks must be greater than zero.', 'wp-cbt-pro');
        }
        if ($negativeMarks < 0) {
            $errors['negative_marks'] = __('Negative marks cannot be less than zero.', 'wp-cbt-pro');
        }

        $extracted = $editor->extract(wp_unslash($_POST));
        $options = $extracted['options'] ?? [];
        if (count($options) < 2) {
            $errors['options'] = __('At least two options are required.', 'wp-cbt-pro');
        } elseif (!array_filter($options, static fn (array $o): bool => !empty($o['is_correct']))) {
            $errors['options'] = __('Mark which option is correct.', 'wp-cbt-pro');
        }

        if ($errors !== []) {
            return $errors;
        }

        $coreData = [
            'type' => $type,
            'content' => $content,
            'subject' => sanitize_text_field(wp_unslash($_POST['subject'] ?? '')),
            'topic' => sanitize_text_field(wp_unslash($_POST['topic'] ?? '')),
            'marks' => $marks,
            'negative_marks' => $negativeMarks,
            'status' => 'active',
        ];

        if ($id === 0) {
            $coreData['institution_id'] = $this->institutionContext->requireCurrentId();
            $coreData['author_id'] = get_current_user_id();
            $id = $this->questions->insert($coreData, $options);
            AuditLogger::record('question.created', 'question', $id, ['type' => $type]);
        } else {
            $this->questions->update($id, $coreData);
            $this->questions->replaceOptions($id, $options);
            AuditLogger::record('question.updated', 'question', $id);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'wpcbtpro-questions',
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
        check_admin_referer('wpcbtpro_delete_mcq_' . $id);

        $question = $this->questions->find($id);
        if ($question !== null && in_array($question['type'], self::TYPES, true)) {
            $this->questions->delete($id);
            AuditLogger::record('question.deleted', 'question', $id);
        }

        wp_safe_redirect(add_query_arg(['page' => 'wpcbtpro-questions', 'deleted' => 1], admin_url('admin.php')));
        exit;
    }
}
