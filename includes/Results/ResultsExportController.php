<?php

declare(strict_types=1);

namespace WPCBTPro\Results;

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use WPCBTPro\Core\SpreadsheetSupport;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Security\Capabilities;

/**
 * CSV (the default, since Excel/Sheets/every gradebook tool opens it
 * natively with no extra dependency) plus a real .xlsx option — now that
 * phpoffice/phpspreadsheet is a real dependency (added for candidate/roster
 * import, §44) — for anyone who specifically wants a native Excel file
 * rather than a text export. Just headers/auth/exit glue: ResultsExportService
 * builds the actual data.
 */
final class ResultsExportController
{
    public function __construct(
        private readonly ResultsExportService $exportService,
        private readonly ExamRepository $exams,
        private readonly InstitutionContext $institutionContext,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_wpcbtpro_export_results', [$this, 'handle']);
    }

    public function handle(): void
    {
        if (!current_user_can(Capabilities::VIEW_CBT_RESULTS)) {
            wp_die(esc_html__('You do not have permission to export results.', 'wp-cbt-pro'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- exam_id is only used to build the nonce action string; check_admin_referer() below rejects any tampering.
        $examId = isset($_GET['exam_id']) ? absint($_GET['exam_id']) : 0;
        check_admin_referer('wpcbtpro_export_results_' . $examId);

        $exam = $this->exams->find($examId);
        if ($exam === null) {
            wp_die(esc_html__('Exam not found.', 'wp-cbt-pro'));
        }

        if (
            !current_user_can(Capabilities::MANAGE_CBT)
            && (int) $exam['institution_id'] !== $this->institutionContext->currentId()
        ) {
            wp_die(esc_html__('You do not have permission to export this exam.', 'wp-cbt-pro'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- only ever chooses which export function below runs; not a state change.
        $format = sanitize_key($_GET['format'] ?? 'csv');
        $rows = $this->exportService->buildRows($examId);

        if ($format === 'xlsx' && SpreadsheetSupport::available()) {
            $this->exportXlsx($exam['name'], $rows);
        } else {
            $this->exportCsv($exam['name'], $rows);
        }

        exit;
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function exportCsv(string $examName, array $rows): void
    {
        $filename = sanitize_file_name($examName) . '-results.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ResultsExportService::COLUMNS);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming to php://output, which WP_Filesystem cannot address.
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function exportXlsx(string $examName, array $rows): void
    {
        $filename = sanitize_file_name($examName) . '-results.xlsx';
        $spreadsheet = $this->exportService->buildSpreadsheet($rows);

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        (new Xlsx($spreadsheet))->save('php://output');
    }
}
