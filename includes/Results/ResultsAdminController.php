<?php

declare(strict_types=1);

namespace WPCBTPro\Results;

use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Security\AuditLogger;
use WPCBTPro\Security\Capabilities;

/**
 * The admin-facing counterpart to the candidate's own single-attempt view
 * (exam-submitted.php, REST GET /result) — every candidate here belongs to
 * one exam the current user is allowed to see (§22, §34).
 */
final class ResultsAdminController
{
    public function __construct(
        private readonly ResultRepository $results,
        private readonly ExamRepository $exams,
        private readonly CandidateRepository $candidates,
        private readonly InstitutionContext $institutionContext,
    ) {
    }

    public function render(): void
    {
        if (!current_user_can(Capabilities::VIEW_CBT_RESULTS)) {
            wp_die(esc_html__('You do not have permission to view results.', 'wp-cbt-pro'));
        }

        // handleRelease() runs check_admin_referer() as its first statement; this only decides whether to dispatch there.
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['wpcbtpro_release_nonce'])) {
            $this->handleRelease();
            return;
        }

        $institutionId = current_user_can(Capabilities::MANAGE_CBT) ? null : $this->institutionContext->currentId();
        $exams = $this->exams->paginate(['institution_id' => $institutionId, 'per_page' => 200]);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: chooses which exam's results to display, not a state change.
        $examId = isset($_GET['exam_id']) ? absint($_GET['exam_id']) : (int) ($exams[0]['id'] ?? 0);
        $exam = $examId > 0 ? $this->exams->find($examId) : null;

        if ($exam !== null && $institutionId !== null && (int) $exam['institution_id'] !== $institutionId) {
            wp_die(esc_html__('You do not have permission to view this exam.', 'wp-cbt-pro'));
        }

        $rows = [];
        $analytics = null;

        if ($exam !== null) {
            $examResults = $this->results->allForExam($examId);
            $candidates = $this->candidates->findMany(array_column($examResults, 'candidate_id'));

            foreach ($examResults as $result) {
                $candidate = $candidates[(int) $result['candidate_id']] ?? null;
                if ($candidate === null) {
                    continue;
                }
                $rows[] = ['result' => $result, 'candidate' => $candidate];
            }
            $analytics = $this->computeAnalytics($rows);
        }

        $canRelease = current_user_can(Capabilities::MANAGE_CBT_EXAMS);
        $csvUrl = $exam !== null ? wp_nonce_url(
            add_query_arg(['action' => 'wpcbtpro_export_results_csv', 'exam_id' => $examId], admin_url('admin-post.php')),
            'wpcbtpro_export_results_csv_' . $examId
        ) : '';

        include WPCBTPRO_PATH . 'admin/views/results-list.php';
    }

    /** @param array<int, array{result: array, candidate: array}> $rows */
    private function computeAnalytics(array $rows): array
    {
        $total = count($rows);
        if ($total === 0) {
            return ['total' => 0, 'average_percentage' => 0.0, 'pass_rate' => 0.0, 'grades' => []];
        }

        $sumPercentage = 0.0;
        $passed = 0;
        $withPassStatus = 0;
        $grades = [];

        foreach ($rows as $row) {
            $result = $row['result'];
            $sumPercentage += (float) $result['percentage'];

            if ($result['pass_status'] !== null) {
                $withPassStatus++;
                if ($result['pass_status'] === 'pass') {
                    $passed++;
                }
            }

            $grade = $result['grade'] ?? __('Pending', 'wp-cbt-pro');
            $grades[$grade] = ($grades[$grade] ?? 0) + 1;
        }

        return [
            'total' => $total,
            'average_percentage' => round($sumPercentage / $total, 2),
            'pass_rate' => $withPassStatus > 0 ? round(($passed / $withPassStatus) * 100, 2) : null,
            'grades' => $grades,
        ];
    }

    private function handleRelease(): void
    {
        if (!current_user_can(Capabilities::MANAGE_CBT_EXAMS)) {
            wp_die(esc_html__('You do not have permission to release results.', 'wp-cbt-pro'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- exam_id is only used to build the nonce action string; check_admin_referer() below rejects any tampering.
        $examId = isset($_POST['exam_id']) ? absint($_POST['exam_id']) : 0;
        check_admin_referer('wpcbtpro_release_results_' . $examId, 'wpcbtpro_release_nonce');

        $this->assertExamInScope($examId);

        $released = $this->results->releaseAllForExam($examId);
        AuditLogger::record('results.released', 'exam', $examId, ['count' => $released]);

        wp_safe_redirect(add_query_arg([
            'page' => 'wpcbtpro-results',
            'exam_id' => $examId,
            'released' => $released,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Capability checks alone only prove "this role may manage some exams" —
     * without this, an Exam Manager could release or export another
     * institution's results just by changing exam_id in the request (§22).
     */
    private function assertExamInScope(int $examId): void
    {
        if (current_user_can(Capabilities::MANAGE_CBT)) {
            return;
        }

        $exam = $this->exams->find($examId);
        if ($exam === null || (int) $exam['institution_id'] !== $this->institutionContext->currentId()) {
            wp_die(esc_html__('You do not have permission to manage this exam.', 'wp-cbt-pro'));
        }
    }
}
