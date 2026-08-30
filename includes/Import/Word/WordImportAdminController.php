<?php

declare(strict_types=1);

namespace WPCBTPro\Import\Word;

use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Security\AuditLogger;
use WPCBTPro\Security\Capabilities;

/**
 * Upload -> parse -> preview -> confirm (§6.1). Parsed rows live in a
 * transient keyed by a random session id between requests; nothing reaches
 * wp_cbt_questions until handleConfirm() runs against explicitly checked
 * rows.
 */
final class WordImportAdminController
{
    private const TRANSIENT_PREFIX = 'wpcbtpro_import_';

    public function __construct(
        private readonly WordImportService $importService,
        private readonly QuestionRepository $questionRepository,
        private readonly InstitutionContext $institutionContext,
    ) {
    }

    public function register(): void
    {
        // Processed on admin_init — before WordPress starts streaming the admin
        // page's HTML — because wp_safe_redirect() from inside the
        // add_submenu_page() render callback itself is always too late: WP has
        // already sent the page header by the time that callback runs, so the
        // redirect silently fails ("headers already sent") and the admin is
        // left looking at a blank page. render() only ever displays.
        add_action('admin_init', [$this, 'maybeProcessRequest']);
    }

    public function maybeProcessRequest(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only: confirms this hook applies to our own page before doing anything.
        if (($_GET['page'] ?? '') !== 'wpcbtpro-import-questions') {
            return;
        }

        if (!current_user_can(Capabilities::MANAGE_CBT_QUESTIONS)) {
            return; // render() will wp_die() with the proper message for a real page view.
        }

        // handleUpload()/handleConfirm() each run check_admin_referer() as their first statement; the reads below only decide whether to dispatch there.
        // phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_import_upload_nonce'])) {
            $this->handleUpload();
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_import_confirm_nonce'])) {
            $this->handleConfirm();
        }
        // phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT_QUESTIONS)) {
            wp_die(esc_html__('You do not have permission to manage questions.', 'wp-cbt-pro'));
        }

        $session = sanitize_key($_GET['session'] ?? '');
        $rows = $session !== '' ? get_transient(self::TRANSIENT_PREFIX . $session) : false;

        if ($rows !== false) {
            $this->renderPreview($session, $rows);
            return;
        }

        $this->renderUploadForm();
    }

    private function renderUploadForm(): void
    {
        $templateUrl = WPCBTPRO_URL . 'templates/wp-cbt-pro-question-template.docx';
        include WPCBTPRO_PATH . 'admin/views/import-upload.php';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function renderPreview(string $session, array $rows): void
    {
        $mathmlAllowedHtml = array_merge(wp_kses_allowed_html('post'), OmmlToMathMlConverter::allowedKsesTags());
        include WPCBTPRO_PATH . 'admin/views/import-preview.php';
    }

    private function handleUpload(): void
    {
        check_admin_referer('wpcbtpro_word_import_upload', 'wpcbtpro_import_upload_nonce');

        if (empty($_FILES['wpcbtpro_docx']['name'])) {
            wp_die(esc_html__('No file was uploaded.', 'wp-cbt-pro'));
        }

        $filename = sanitize_file_name($_FILES['wpcbtpro_docx']['name']);
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['docx', 'txt'], true)) {
            wp_die(esc_html__('Please upload a .docx or .txt file.', 'wp-cbt-pro'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- is_uploaded_file() below is the correct validation for a tmp_name, not sanitize_text_field().
        $tmpPath = $_FILES['wpcbtpro_docx']['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpPath)) {
            wp_die(esc_html__('The upload could not be verified.', 'wp-cbt-pro'));
        }

        try {
            $rows = $this->importService->parseFile($tmpPath, $extension);
        } catch (\Throwable $e) {
            wp_die(esc_html(sprintf(
                /* translators: %s: underlying error message */
                __('Could not read this file: %s', 'wp-cbt-pro'),
                $e->getMessage()
            )));
        }

        if ($rows === []) {
            $message = $extension === 'txt'
                ? __('No questions were found in this file. Separate each question (with its A./B./… options) from the next with a blank line.', 'wp-cbt-pro')
                : __('No "QUESTION" blocks were found in this document. Use the downloadable template as a starting point.', 'wp-cbt-pro');
            wp_die(esc_html($message));
        }

        $session = wp_generate_password(16, false, false);
        set_transient(self::TRANSIENT_PREFIX . $session, $rows, HOUR_IN_SECONDS);

        wp_safe_redirect(add_query_arg([
            'page' => 'wpcbtpro-import-questions',
            'session' => $session,
        ], admin_url('admin.php')));
        exit;
    }

    private function handleConfirm(): void
    {
        $session = sanitize_key($_POST['session'] ?? '');
        check_admin_referer('wpcbtpro_word_import_confirm_' . $session, 'wpcbtpro_import_confirm_nonce');

        $rows = get_transient(self::TRANSIENT_PREFIX . $session);
        if ($rows === false) {
            wp_die(esc_html__('This import session has expired. Please upload the file again.', 'wp-cbt-pro'));
        }

        $selected = array_map('intval', (array) ($_POST['rows'] ?? []));
        $institutionId = $this->institutionContext->requireCurrentId();

        $imported = 0;
        foreach ($rows as $index => $row) {
            if (!in_array($index, $selected, true) || $row['mapped'] === null) {
                continue;
            }

            $mapped = $row['mapped'];
            $options = $mapped['options'] ?? [];
            unset($mapped['options']);

            $questionId = $this->questionRepository->insert(array_merge($mapped, [
                'institution_id' => $institutionId,
                'author_id' => get_current_user_id(),
                'status' => 'active',
            ]), $options);

            AuditLogger::record('question.imported', 'question', $questionId, ['source' => 'word_import']);
            $imported++;
        }

        delete_transient(self::TRANSIENT_PREFIX . $session);

        wp_safe_redirect(add_query_arg([
            'page' => 'wpcbtpro-import-questions',
            'imported' => $imported,
            'total' => count($selected),
        ], admin_url('admin.php')));
        exit;
    }
}
