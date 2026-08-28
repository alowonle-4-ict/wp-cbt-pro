<?php

declare(strict_types=1);

namespace WPCBTPro\Results;

use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Security\Capabilities;

/**
 * A real CSV — not a claimed .xlsx it doesn't actually produce. Excel,
 * Sheets, and every gradebook tool open CSV natively; generating a genuine
 * binary spreadsheet format would mean bundling a library this plugin
 * doesn't otherwise need (§44).
 */
final class ResultsExportController
{
    public function __construct(
        private readonly ResultRepository $results,
        private readonly ExamRepository $exams,
        private readonly CandidateRepository $candidates,
        private readonly InstitutionContext $institutionContext,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_wpcbtpro_export_results_csv', [$this, 'handle']);
    }

    public function handle(): void
    {
        if (!current_user_can(Capabilities::VIEW_CBT_RESULTS)) {
            wp_die(esc_html__('You do not have permission to export results.', 'wp-cbt-pro'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- exam_id is only used to build the nonce action string; check_admin_referer() below rejects any tampering.
        $examId = isset($_GET['exam_id']) ? absint($_GET['exam_id']) : 0;
        check_admin_referer('wpcbtpro_export_results_csv_' . $examId);

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

        $filename = sanitize_file_name($exam['name']) . '-results.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Candidate Name', 'Candidate ID', 'Score', 'Percentage', 'Grade',
            'Pass/Fail', 'Correct', 'Incorrect', 'Unanswered', 'Pending Review',
            'Status', 'Time Used (s)', 'Submitted At', 'Released',
        ]);

        $examResults = $this->results->allForExam($examId);
        $candidates = $this->candidates->findMany(array_column($examResults, 'candidate_id'));

        foreach ($examResults as $result) {
            $candidate = $candidates[(int) $result['candidate_id']] ?? null;
            if ($candidate === null) {
                continue;
            }

            fputcsv($out, [
                trim($candidate['first_name'] . ' ' . $candidate['last_name']),
                $candidate['candidate_ref'],
                $result['score'],
                $result['percentage'],
                $result['grade'] ?? '',
                $result['pass_status'] ?? '',
                $result['correct_count'],
                $result['incorrect_count'],
                $result['unanswered_count'],
                $result['pending_review_count'],
                $result['status'],
                $result['time_used_seconds'],
                $result['submitted_at'],
                empty($result['released_at']) ? 'No' : 'Yes',
            ]);
        }

        fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- streaming to php://output, which WP_Filesystem cannot address.
        exit;
    }
}
