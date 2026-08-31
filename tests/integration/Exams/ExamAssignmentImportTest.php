<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Exams;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Exams\ExamAssignmentImportService;
use WPCBTPro\Exams\ExamCandidateRosterRepository;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Institutions\InstitutionRepository;

/**
 * "Upload candidates to exam directly, like Moodle" — one spreadsheet with
 * an Exam column per row, assigning to whichever exam each row names,
 * rather than picking one exam first (ExamRosterImportService covers that
 * narrower flow). Exercises the real spreadsheet parsing, name matching,
 * and the auto-restrict-to-roster behavior end to end.
 */
final class ExamAssignmentImportTest extends \WP_UnitTestCase
{
    private function writeSheet(array $header, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'wpcbtpro-assignment-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function makeExam(int $institutionId, string $name, bool $restrictToRoster = false): int
    {
        $examRepository = new ExamRepository();
        return $examRepository->insert([
            'institution_id' => $institutionId,
            'name' => $name,
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
            'result_visibility' => 'immediate',
            'restrict_to_roster' => $restrictToRoster ? 1 : 0,
        ]);
    }

    public function testARowNamingAnExamByNameIsMatchedCaseInsensitively(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $examId = $this->makeExam($institutionId, 'Mathematics Mid-Term 2026');

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Exam'],
            [['Ada', 'Lovelace', 'mathematics mid-term 2026']]
        );

        /** @var ExamAssignmentImportService $service */
        $service = Plugin::instance()->container()->get(ExamAssignmentImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertCount(1, $rows);
        self::assertSame([], $rows[0]['errors']);
        self::assertNotNull($rows[0]['exam']);
        self::assertSame($examId, (int) $rows[0]['exam']['id']);
    }

    public function testAnUnmatchedExamNameIsABlockingError(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $this->makeExam($institutionId, 'Mathematics Mid-Term 2026');

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Exam'],
            [['Ada', 'Lovelace', 'Some Exam That Does Not Exist']]
        );

        /** @var ExamAssignmentImportService $service */
        $service = Plugin::instance()->container()->get(ExamAssignmentImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertArrayHasKey('exam', $rows[0]['errors']);
        self::assertNull($rows[0]['exam']);
    }

    public function testABlankExamColumnIsABlockingError(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Exam'],
            [['Ada', 'Lovelace', '']]
        );

        /** @var ExamAssignmentImportService $service */
        $service = Plugin::instance()->container()->get(ExamAssignmentImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertArrayHasKey('exam', $rows[0]['errors']);
    }

    public function testImportRowAddsANewCandidateToTheRosterAndEnablesRestriction(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $examId = $this->makeExam($institutionId, 'Physics Final 2026', restrictToRoster: false);

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Exam'],
            [['Grace', 'Hopper', 'Physics Final 2026']]
        );

        /** @var ExamAssignmentImportService $service */
        $service = Plugin::instance()->container()->get(ExamAssignmentImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        $result = $service->importRow($rows[0]);
        self::assertArrayHasKey('candidate_id', $result);
        self::assertSame($examId, $result['exam_id']);

        $roster = Plugin::instance()->container()->get(ExamCandidateRosterRepository::class);
        self::assertTrue($roster->isMember($examId, $result['candidate_id']));

        $exam = (new ExamRepository())->find($examId);
        self::assertSame(1, (int) $exam['restrict_to_roster'], 'Assigning a candidate this way should turn restrict_to_roster on.');
    }

    public function testImportRowMatchingAnExistingCandidateReusesItInsteadOfDuplicating(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $examId = $this->makeExam($institutionId, 'Chemistry Resit 2026');

        $existingId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-ASSIGN-' . wp_generate_password(6, false, false),
            'first_name' => 'Marie',
            'last_name' => 'Curie',
            'registration_number' => 'ASSIGN-001',
            'status' => 'active',
        ]);

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Registration Number', 'Exam'],
            [['Marie', 'Curie', 'ASSIGN-001', 'Chemistry Resit 2026']]
        );

        /** @var ExamAssignmentImportService $service */
        $service = Plugin::instance()->container()->get(ExamAssignmentImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertSame($existingId, $rows[0]['existing_candidate_id']);

        $countBefore = (new CandidateRepository())->count(['institution_id' => $institutionId]);
        $result = $service->importRow($rows[0]);
        $countAfter = (new CandidateRepository())->count(['institution_id' => $institutionId]);

        self::assertSame($existingId, $result['candidate_id']);
        self::assertSame($countBefore, $countAfter, 'No new candidate should have been created.');
    }

    public function testMultipleRowsCanTargetDifferentExamsInOneUpload(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $mathExamId = $this->makeExam($institutionId, 'Multi Math 2026');
        $physicsExamId = $this->makeExam($institutionId, 'Multi Physics 2026');

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Exam'],
            [
                ['Ada', 'Lovelace', 'Multi Math 2026'],
                ['Grace', 'Hopper', 'Multi Physics 2026'],
            ]
        );

        /** @var ExamAssignmentImportService $service */
        $service = Plugin::instance()->container()->get(ExamAssignmentImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertSame($mathExamId, (int) $rows[0]['exam']['id']);
        self::assertSame($physicsExamId, (int) $rows[1]['exam']['id']);

        $mathResult = $service->importRow($rows[0]);
        $physicsResult = $service->importRow($rows[1]);

        $roster = Plugin::instance()->container()->get(ExamCandidateRosterRepository::class);
        self::assertTrue($roster->isMember($mathExamId, $mathResult['candidate_id']));
        self::assertTrue($roster->isMember($physicsExamId, $physicsResult['candidate_id']));
        self::assertFalse($roster->isMember($mathExamId, $physicsResult['candidate_id']));
    }
}
