<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

use WPCBTPro\Core\SpreadsheetSupport;
use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Security\Capabilities;

/**
 * Upload -> preview -> confirm, the same shape as WordImportAdminController:
 * parsed rows live in a transient keyed by a random session id between
 * requests; nothing reaches wp_cbt_candidates or wp_users until
 * handleConfirm() runs against explicitly checked rows.
 */
final class CandidateBulkImportAdminController
{
    private const TRANSIENT_PREFIX = 'wpcbtpro_candidate_import_';
    private const FAILURES_TRANSIENT_PREFIX = 'wpcbtpro_candidate_import_failures_';

    public function __construct(
        private readonly CandidateBulkImportService $importService,
        private readonly InstitutionContext $institutionContext,
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
        if (($_GET['page'] ?? '') !== 'wpcbtpro-import-candidates') {
            return;
        }

        if (!current_user_can(Capabilities::MANAGE_CBT_CANDIDATES)) {
            return; // render() will wp_die() with the proper message for a real page view.
        }

        // handleUpload()/handleConfirm() each run check_admin_referer() as their first statement; the reads below only decide whether to dispatch there.
        // phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_candidate_import_upload_nonce'])) {
            $this->handleUpload();
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_candidate_import_confirm_nonce'])) {
            $this->handleConfirm();
        }
        // phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT_CANDIDATES)) {
            wp_die(esc_html__('You do not have permission to manage candidates.', 'wp-cbt-pro'));
        }

        if (!SpreadsheetSupport::available()) {
            wp_die(esc_html(SpreadsheetSupport::missingMessage()));
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
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success banner, not a state change; values are cast to int/array below.
        $failuresSession = sanitize_key($_GET['session'] ?? '');
        $failures = $failuresSession !== '' ? get_transient(self::FAILURES_TRANSIENT_PREFIX . $failuresSession) : false;
        if ($failures !== false) {
            delete_transient(self::FAILURES_TRANSIENT_PREFIX . $failuresSession);
        }

        include WPCBTPRO_PATH . 'admin/views/candidate-import-upload.php';
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function renderPreview(string $session, array $rows): void
    {
        include WPCBTPRO_PATH . 'admin/views/candidate-import-preview.php';
    }

    private function handleUpload(): void
    {
        check_admin_referer('wpcbtpro_candidate_import_upload', 'wpcbtpro_candidate_import_upload_nonce');

        if (empty($_FILES['wpcbtpro_candidates']['name'])) {
            wp_die(esc_html__('No file was uploaded.', 'wp-cbt-pro'));
        }

        $filename = sanitize_file_name($_FILES['wpcbtpro_candidates']['name']);
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            wp_die(esc_html__('Please upload an .xlsx, .xls, or .csv file.', 'wp-cbt-pro'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- is_uploaded_file() below is the correct validation for a tmp_name, not sanitize_text_field().
        $tmpPath = $_FILES['wpcbtpro_candidates']['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpPath)) {
            wp_die(esc_html__('The upload could not be verified.', 'wp-cbt-pro'));
        }

        try {
            $rows = $this->importService->parseFile($tmpPath, $this->institutionContext->requireCurrentId());
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
            'page' => 'wpcbtpro-import-candidates',
            'session' => $session,
        ], admin_url('admin.php')));
        exit;
    }

    private function handleConfirm(): void
    {
        $session = sanitize_key($_POST['session'] ?? '');
        check_admin_referer('wpcbtpro_candidate_import_confirm_' . $session, 'wpcbtpro_candidate_import_confirm_nonce');

        $rows = get_transient(self::TRANSIENT_PREFIX . $session);
        if ($rows === false) {
            wp_die(esc_html__('This import session has expired. Please upload the file again.', 'wp-cbt-pro'));
        }

        $selected = array_map('intval', (array) ($_POST['rows'] ?? []));

        $imported = 0;
        $failures = [];
        foreach ($rows as $index => $row) {
            if (!in_array($index, $selected, true) || $row['errors'] !== []) {
                continue;
            }

            $result = $this->importService->import($row);
            if (isset($result['error'])) {
                $failures[] = sprintf(
                    /* translators: 1: spreadsheet row number, 2: error message */
                    __('Row %1$d: %2$s', 'wp-cbt-pro'),
                    $row['row_number'],
                    $result['error']
                );
                continue;
            }

            $imported++;
        }

        delete_transient(self::TRANSIENT_PREFIX . $session);
        if ($failures !== []) {
            set_transient(self::FAILURES_TRANSIENT_PREFIX . $session, $failures, HOUR_IN_SECONDS);
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'wpcbtpro-import-candidates',
            'imported' => $imported,
            'total' => count($selected),
            'session' => $session,
        ], admin_url('admin.php')));
        exit;
    }
}
