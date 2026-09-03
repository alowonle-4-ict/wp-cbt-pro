<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Results;

use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionRepository;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Results\ResultRepository;
use WPCBTPro\Results\ResultsExportService;

/**
 * Proves the real .xlsx path (added alongside the existing CSV export, now
 * that phpoffice/phpspreadsheet is a real dependency) produces a genuine
 * spreadsheet a real reader can open — not just that PhpSpreadsheet's own
 * API was called correctly, by round-tripping through IOFactory::load()
 * against an actual saved file.
 */
final class ResultsExportServiceTest extends \WP_UnitTestCase
{
    private function makeExamWithOneResult(): array
    {
        global $wpdb;

        $institutionId = (new InstitutionRepository())->ensureDefault();

        $questionId = (new QuestionRepository())->insert(
            [
                'institution_id' => $institutionId,
                'type' => 'mcq_single',
                'content' => 'Pick one.',
                'marks' => 1.0,
                'negative_marks' => 0.0,
                'status' => 'active',
            ],
            [
                ['label' => 'A', 'is_correct' => true, 'sort_order' => 0],
                ['label' => 'B', 'is_correct' => false, 'sort_order' => 1],
            ]
        );

        $examRepository = new ExamRepository();
        $examId = $examRepository->insert([
            'institution_id' => $institutionId,
            'name' => 'Export Test Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
            'result_visibility' => 'immediate',
        ]);
        $examRepository->setQuestions($examId, [['question_id' => $questionId]]);

        $candidateId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-EXPORT-' . wp_generate_password(6, false, false),
            'first_name' => 'Export',
            'last_name' => 'Candidate',
            'registration_number' => 'EXPORT-REG-001',
            'department' => 'Computer Science',
            'class' => '300L',
            'status' => 'active',
        ]);

        $now = current_time('mysql');
        $wpdb->insert($wpdb->prefix . 'cbt_attempts', [
            'exam_id' => $examId,
            'candidate_id' => $candidateId,
            'seed' => 'seed',
            'server_start' => $now,
            'server_end' => $now,
            'submitted_at' => $now,
            'status' => 'submitted',
            'created_at' => $now,
        ]);
        $attemptId = (int) $wpdb->insert_id;

        $wpdb->insert($wpdb->prefix . 'cbt_results', [
            'attempt_id' => $attemptId,
            'score' => 1.0,
            'percentage' => 100.0,
            'grade' => 'A',
            'pass_status' => 'pass',
            'correct_count' => 1,
            'incorrect_count' => 0,
            'unanswered_count' => 0,
            'pending_review_count' => 0,
            'status' => 'final',
            'time_used_seconds' => 120,
        ]);

        return ['exam_id' => $examId, 'candidate_id' => $candidateId];
    }

    /** Records another submitted attempt + result for an existing candidate/exam pair. */
    private function addAnotherAttempt(int $examId, int $candidateId, float $percentage): void
    {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->insert($wpdb->prefix . 'cbt_attempts', [
            'exam_id' => $examId,
            'candidate_id' => $candidateId,
            'seed' => 'seed-' . $percentage,
            'server_start' => $now,
            'server_end' => $now,
            'submitted_at' => $now,
            'status' => 'submitted',
            'created_at' => $now,
        ]);
        $attemptId = (int) $wpdb->insert_id;

        $wpdb->insert($wpdb->prefix . 'cbt_results', [
            'attempt_id' => $attemptId,
            'score' => $percentage / 100,
            'percentage' => $percentage,
            'grade' => $percentage >= 50 ? 'B' : 'F',
            'pass_status' => $percentage >= 50 ? 'pass' : 'fail',
            'correct_count' => 0,
            'incorrect_count' => 1,
            'unanswered_count' => 0,
            'pending_review_count' => 0,
            'status' => 'final',
            'time_used_seconds' => 90,
        ]);
    }

    public function testBuildRowsMapsOneRowPerResultInColumnOrder(): void
    {
        $fixture = $this->makeExamWithOneResult();

        /** @var ResultsExportService $service */
        $service = Plugin::instance()->container()->get(ResultsExportService::class);
        $rows = $service->buildRows($fixture['exam_id']);

        self::assertCount(1, $rows);
        self::assertSame('Export Candidate', $rows[0][0]);
        self::assertSame('EXPORT-REG-001', $rows[0][1]);
        self::assertSame('Computer Science', $rows[0][2]);
        self::assertSame('300L', $rows[0][3]);
        self::assertSame(1.0, (float) $rows[0][5]);
        self::assertSame(100.0, (float) $rows[0][6]);
        self::assertSame('A', $rows[0][7]);
        self::assertSame('pass', $rows[0][8]);
        self::assertSame('No', $rows[0][16], 'Not yet released.');
    }

    public function testBuildSpreadsheetProducesARealXlsxFileAReaderCanOpen(): void
    {
        $fixture = $this->makeExamWithOneResult();

        /** @var ResultsExportService $service */
        $service = Plugin::instance()->container()->get(ResultsExportService::class);
        $rows = $service->buildRows($fixture['exam_id']);
        $spreadsheet = $service->buildSpreadsheet($rows);

        $path = tempnam(sys_get_temp_dir(), 'wpcbtpro-results-export-') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        try {
            $reloaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheetArray = $reloaded->getActiveSheet()->toArray(null, true, true, false);
        } finally {
            unlink($path);
        }

        self::assertSame(ResultsExportService::COLUMNS, $sheetArray[0]);
        self::assertSame('Export Candidate', $sheetArray[1][0]);
    }

    public function testBuildRowsExportsOnlyTheCandidatesBestAttemptWhenTheyHaveSeveral(): void
    {
        $fixture = $this->makeExamWithOneResult(); // first attempt: 100%
        $this->addAnotherAttempt($fixture['exam_id'], $fixture['candidate_id'], 40.0);
        $this->addAnotherAttempt($fixture['exam_id'], $fixture['candidate_id'], 75.0);

        // The admin results list shows every attempt — confirm all three are
        // really there before checking that export collapses them.
        $resultRepository = new ResultRepository();
        self::assertCount(3, $resultRepository->allForExam($fixture['exam_id']));

        /** @var ResultsExportService $service */
        $service = Plugin::instance()->container()->get(ResultsExportService::class);
        $rows = $service->buildRows($fixture['exam_id']);

        self::assertCount(1, $rows, 'Only the candidate\'s best attempt should be exported.');
        self::assertSame(100.0, (float) $rows[0][6], 'The 100% attempt is the best one and should be the row exported.');
    }

    public function testBuildRowsExportsEachCandidatesOwnBestAttemptSeparately(): void
    {
        global $wpdb;

        $fixture = $this->makeExamWithOneResult(); // candidate A: 100%
        $this->addAnotherAttempt($fixture['exam_id'], $fixture['candidate_id'], 40.0);

        $institutionId = (new InstitutionRepository())->ensureDefault();
        $candidateB = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-EXPORT-B-' . wp_generate_password(6, false, false),
            'first_name' => 'Second',
            'last_name' => 'Candidate',
            'status' => 'active',
        ]);
        $this->addAnotherAttempt($fixture['exam_id'], $candidateB, 60.0);
        $this->addAnotherAttempt($fixture['exam_id'], $candidateB, 85.0);

        /** @var ResultsExportService $service */
        $service = Plugin::instance()->container()->get(ResultsExportService::class);
        $rows = $service->buildRows($fixture['exam_id']);

        self::assertCount(2, $rows, 'One row per candidate, not per attempt.');
        $percentages = array_map(static fn (array $row): float => (float) $row[6], $rows);
        sort($percentages);
        self::assertSame([85.0, 100.0], $percentages);
    }

    public function testBuildSpreadsheetWithNoResultsStillProducesAHeaderOnlyFile(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $examRepository = new ExamRepository();
        $examId = $examRepository->insert([
            'institution_id' => $institutionId,
            'name' => 'Empty Export Test Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'draft',
            'result_visibility' => 'immediate',
        ]);

        /** @var ResultsExportService $service */
        $service = Plugin::instance()->container()->get(ResultsExportService::class);
        $rows = $service->buildRows($examId);

        self::assertSame([], $rows);

        $spreadsheet = $service->buildSpreadsheet($rows);
        $sheetArray = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        self::assertSame(ResultsExportService::COLUMNS, $sheetArray[0]);
    }
}
