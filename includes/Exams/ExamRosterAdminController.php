<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

use WPCBTPro\Core\SpreadsheetSupport;
use WPCBTPro\Security\Capabilities;

/**
 * Per-exam roster management: view/remove current members, and the same
 * upload -> preview -> confirm shape as every other import in this plugin
 * for adding more. Reached from the exam edit form once an exam has an id
 * (registered with a null parent so it doesn't get its own wp-admin nav
 * item — see AdminMenu::addMenuPages()).
 */
final class ExamRosterAdminController
{
    private const TRANSIENT_PREFIX = 'wpcbtpro_exam_roster_import_';

    public function __construct(
        private readonly ExamRosterImportService $importService,
        private readonly ExamCandidateRosterRepository $roster,
        private readonly ExamRepository $examRepository,
    ) {
    }

    public function register(): void
    {
        // Processed on admin_init — before WordPress starts streaming the admin
        // page's HTML — see WordImportAdminController::register() for why a
        // redirect from inside the add_submenu_page() callback itself is
        // always too late.
        add_action('admin_init', [$this, 'maybeProcessRequest']);
    }

    public function maybeProcessRequest(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only: confirms this hook applies to our own page before doing anything.
        if (($_GET['page'] ?? '') !== 'wpcbtpro-exam-roster') {
            return;
        }

        if (!current_user_can(Capabilities::MANAGE_CBT_EXAMS)) {
            return; // render() will wp_die() with the proper message for a real page view.
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only routing; the real check happens in handleRemove() below.
        if (($_GET['action'] ?? '') === 'remove') {
            $this->handleRemove();
            return;
        }

        // handleUpload()/handleConfirm() each run check_admin_referer() as their first statement; the reads below only decide whether to dispatch there.
        // phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_exam_roster_upload_nonce'])) {
            $this->handleUpload();
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_exam_roster_confirm_nonce'])) {
            $this->handleConfirm();
        }
        // phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT_EXAMS)) {
            wp_die(esc_html__('You do not have permission to manage exams.', 'wp-cbt-pro'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: resolves which exam's roster to display, not a state change.
        $examId = isset($_GET['exam_id']) ? absint($_GET['exam_id']) : 0;
        $exam = $examId > 0 ? $this->examRepository->find($examId) : null;
        if ($exam === null) {
            wp_die(esc_html__('Exam not found.', 'wp-cbt-pro'));
        }

        if (!SpreadsheetSupport::available()) {
            wp_die(esc_html(SpreadsheetSupport::missingMessage()));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: resolves which preview session to display, not a state change.
        $session = sanitize_key($_GET['session'] ?? '');
        $rows = $session !== '' ? get_transient(self::TRANSIENT_PREFIX . $session) : false;

        if ($rows !== false) {
            $this->renderPreview($exam, $session, $rows);
            return;
        }

        $this->renderRoster($exam);
    }

    /** @param array<string, mixed> $exam */
    private function renderRoster(array $exam): void
    {
        $candidates = $this->roster->candidatesForExam((int) $exam['id']);
        include WPCBTPRO_PATH . 'admin/views/exam-roster.php';
    }

    /**
     * @param array<string, mixed> $exam
     * @param array<int, array<string, mixed>> $rows
     */
    private function renderPreview(array $exam, string $session, array $rows): void
    {
        include WPCBTPRO_PATH . 'admin/views/exam-roster-preview.php';
    }

    private function handleUpload(): void
    {
        $examId = isset($_POST['exam_id']) ? absint($_POST['exam_id']) : 0;
        check_admin_referer('wpcbtpro_exam_roster_upload_' . $examId, 'wpcbtpro_exam_roster_upload_nonce');

        $exam = $this->examRepository->find($examId);
        if ($exam === null) {
            wp_die(esc_html__('Exam not found.', 'wp-cbt-pro'));
        }

        if (empty($_FILES['wpcbtpro_roster']['name'])) {
            wp_die(esc_html__('No file was uploaded.', 'wp-cbt-pro'));
        }

        $filename = sanitize_file_name($_FILES['wpcbtpro_roster']['name']);
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            wp_die(esc_html__('Please upload an .xlsx, .xls, or .csv file.', 'wp-cbt-pro'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- is_uploaded_file() below is the correct validation for a tmp_name, not sanitize_text_field().
        $tmpPath = $_FILES['wpcbtpro_roster']['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpPath)) {
            wp_die(esc_html__('The upload could not be verified.', 'wp-cbt-pro'));
        }

        try {
            $rows = $this->importService->parseFile($tmpPath, (int) $exam['institution_id']);
        } catch (\Throwable $e) {
            wp_die(esc_html(sprintf(
                /* translators: %s: underlying error message */
                __('Could not read this file: %s', 'wp-cbt-pro'),
                $e->getMessage()
            )));
        }

        if ($rows === []) {
            wp_die(esc_html__('No candidate rows were found in this file. The first row must be a header with at least "First Name" and "Last Name" columns.', 'wp-cbt-pro'));
        }

        $session = wp_generate_password(16, false, false);
        set_transient(self::TRANSIENT_PREFIX . $session, $rows, HOUR_IN_SECONDS);

        wp_safe_redirect(add_query_arg([
            'page' => 'wpcbtpro-exam-roster',
            'exam_id' => $examId,
            'session' => $session,
        ], admin_url('admin.php')));
        exit;
    }

    private function handleConfirm(): void
    {
        $examId = isset($_POST['exam_id']) ? absint($_POST['exam_id']) : 0;
        $session = sanitize_key($_POST['session'] ?? '');
        check_admin_referer('wpcbtpro_exam_roster_confirm_' . $session, 'wpcbtpro_exam_roster_confirm_nonce');

        $exam = $this->examRepository->find($examId);
        if ($exam === null) {
            wp_die(esc_html__('Exam not found.', 'wp-cbt-pro'));
        }

        $rows = get_transient(self::TRANSIENT_PREFIX . $session);
        if ($rows === false) {
            wp_die(esc_html__('This import session has expired. Please upload the file again.', 'wp-cbt-pro'));
        }

        $selected = array_map('intval', (array) ($_POST['rows'] ?? []));

        $added = 0;
        foreach ($rows as $index => $row) {
            if (!in_array($index, $selected, true) || $row['errors'] !== []) {
                continue;
            }

            $result = $this->importService->importToExam($row, $examId);
            if (isset($result['candidate_id'])) {
                $added++;
            }
        }

        delete_transient(self::TRANSIENT_PREFIX . $session);

        wp_safe_redirect(add_query_arg([
            'page' => 'wpcbtpro-exam-roster',
            'exam_id' => $examId,
            'added' => $added,
            'total' => count($selected),
        ], admin_url('admin.php')));
        exit;
    }

    private function handleRemove(): void
    {
        $examId = isset($_GET['exam_id']) ? absint($_GET['exam_id']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the id is only used to build the nonce action string; check_admin_referer() below rejects any tampering.
        $candidateId = isset($_GET['candidate_id']) ? absint($_GET['candidate_id']) : 0;
        check_admin_referer('wpcbtpro_exam_roster_remove_' . $examId . '_' . $candidateId);

        $this->roster->remove($examId, $candidateId);

        wp_safe_redirect(add_query_arg([
            'page' => 'wpcbtpro-exam-roster',
            'exam_id' => $examId,
            'removed' => 1,
        ], admin_url('admin.php')));
        exit;
    }
}
