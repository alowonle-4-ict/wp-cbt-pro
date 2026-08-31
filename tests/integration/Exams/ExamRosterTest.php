<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Exams;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Exams\ExamCandidateRosterRepository;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Exams\ExamRosterImportService;
use WPCBTPro\Institutions\InstitutionRepository;
use WPCBTPro\Questions\QuestionRepository;

/**
 * Exercises the real per-exam roster restriction end to end: uploading a
 * genuine .xlsx (reusing CandidateBulkImportService's own spreadsheet
 * parsing) either matches an existing candidate or creates one, adds them
 * to wp_cbt_exam_candidates, and AttemptService::startAttempt() enforces
 * membership only when the exam's restrict_to_roster flag is on.
 */
final class ExamRosterTest extends \WP_UnitTestCase
{
    private function writeSheet(array $header, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'wpcbtpro-roster-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function makeExam(int $institutionId, bool $restrictToRoster): int
    {
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
            'name' => 'Roster Test Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
            'result_visibility' => 'immediate',
            'restrict_to_roster' => $restrictToRoster ? 1 : 0,
        ]);
        $examRepository->setQuestions($examId, [['question_id' => $questionId]]);

        return $examId;
    }

    public function testUploadingARosterCreatesANewCandidateAndAddsThemToTheRoster(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $examId = $this->makeExam($institutionId, true);

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Registration Number'],
            [['Ada', 'Lovelace', 'ROSTER-001']]
        );

        /** @var ExamRosterImportService $service */
        $service = Plugin::instance()->container()->get(ExamRosterImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertNull($rows[0]['existing_candidate_id']);

        $result = $service->importToExam($rows[0], $examId);
        self::assertArrayHasKey('candidate_id', $result);

        $roster = Plugin::instance()->container()->get(ExamCandidateRosterRepository::class);
        self::assertTrue($roster->isMember($examId, $result['candidate_id']));
        self::assertSame(1, $roster->countForExam($examId));

        $candidate = (new CandidateRepository())->find($result['candidate_id']);
        self::assertSame('ROSTER-001', $candidate['registration_number']);
    }

    public function testUploadingARosterRowMatchingAnExistingCandidateReusesItInsteadOfDuplicating(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $examId = $this->makeExam($institutionId, true);

        $existingId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-ROSTER-' . wp_generate_password(6, false, false),
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'registration_number' => 'ROSTER-002',
            'status' => 'active',
        ]);

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Registration Number'],
            [['Grace', 'Hopper', 'ROSTER-002']]
        );

        /** @var ExamRosterImportService $service */
        $service = Plugin::instance()->container()->get(ExamRosterImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertSame($existingId, $rows[0]['existing_candidate_id']);

        $countBefore = (new CandidateRepository())->count(['institution_id' => $institutionId]);
        $result = $service->importToExam($rows[0], $examId);
        $countAfter = (new CandidateRepository())->count(['institution_id' => $institutionId]);

        self::assertSame($existingId, $result['candidate_id']);
        self::assertSame($countBefore, $countAfter, 'No new candidate should have been created.');

        $roster = Plugin::instance()->container()->get(ExamCandidateRosterRepository::class);
        self::assertTrue($roster->isMember($examId, $existingId));
    }

    public function testStartAttemptIsBlockedForACandidateNotOnARestrictedExamsRoster(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $examId = $this->makeExam($institutionId, true);

        $candidateId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-ROSTER-' . wp_generate_password(6, false, false),
            'first_name' => 'Not',
            'last_name' => 'OnRoster',
            'status' => 'active',
        ]);

        /** @var AttemptService $attemptService */
        $attemptService = Plugin::instance()->container()->get(AttemptService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not on the candidate list');
        $attemptService->startAttempt($examId, $candidateId);
    }

    public function testStartAttemptSucceedsForACandidateOnARestrictedExamsRoster(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $examId = $this->makeExam($institutionId, true);

        $candidateId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-ROSTER-' . wp_generate_password(6, false, false),
            'first_name' => 'On',
            'last_name' => 'Roster',
            'status' => 'active',
        ]);

        $roster = Plugin::instance()->container()->get(ExamCandidateRosterRepository::class);
        $roster->add($examId, $candidateId);

        /** @var AttemptService $attemptService */
        $attemptService = Plugin::instance()->container()->get(AttemptService::class);
        $attempt = $attemptService->startAttempt($examId, $candidateId);

        self::assertSame($candidateId, (int) $attempt['candidate_id']);
    }

    public function testStartAttemptIgnoresTheRosterWhenTheExamIsNotRestricted(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $examId = $this->makeExam($institutionId, false);

        $candidateId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-ROSTER-' . wp_generate_password(6, false, false),
            'first_name' => 'Unrestricted',
            'last_name' => 'Candidate',
            'status' => 'active',
        ]);

        /** @var AttemptService $attemptService */
        $attemptService = Plugin::instance()->container()->get(AttemptService::class);
        $attempt = $attemptService->startAttempt($examId, $candidateId);

        self::assertSame($candidateId, (int) $attempt['candidate_id']);
    }

    public function testRemovingACandidateFromTheRosterRevokesFutureAccess(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $examId = $this->makeExam($institutionId, true);

        $candidateId = (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-ROSTER-' . wp_generate_password(6, false, false),
            'first_name' => 'Removed',
            'last_name' => 'Candidate',
            'status' => 'active',
        ]);

        $roster = Plugin::instance()->container()->get(ExamCandidateRosterRepository::class);
        $roster->add($examId, $candidateId);
        self::assertTrue($roster->isMember($examId, $candidateId));

        $roster->remove($examId, $candidateId);
        self::assertFalse($roster->isMember($examId, $candidateId));

        /** @var AttemptService $attemptService */
        $attemptService = Plugin::instance()->container()->get(AttemptService::class);
        $this->expectException(\RuntimeException::class);
        $attemptService->startAttempt($examId, $candidateId);
    }
}
