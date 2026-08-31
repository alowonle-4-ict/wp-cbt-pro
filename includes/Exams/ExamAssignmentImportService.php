<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

use WPCBTPro\Candidates\CandidateBulkImportService;
use WPCBTPro\Candidates\CandidateRepository;

/**
 * "Upload candidates to exam directly, like Moodle" — one spreadsheet, an
 * "Exam" column per row naming which exam that candidate belongs to,
 * instead of picking one exam first (see ExamRosterImportService for that
 * narrower, single-exam flow, which this doesn't replace). Matches by the
 * exam's own Name field, case-insensitive, scoped to the current
 * institution; a name matching no exam — or matching more than one, since
 * names aren't required to be unique — is a per-row error, not a guess.
 *
 * Using this at all is enrollment in the Moodle sense: a row's exam is
 * switched to restrict_to_roster on import, since the whole point of
 * naming a specific exam per candidate is that only those candidates
 * should be able to start it.
 */
final class ExamAssignmentImportService
{
    public function __construct(
        private readonly CandidateBulkImportService $candidateImportService,
        private readonly CandidateRepository $candidateRepository,
        private readonly ExamRepository $exams,
        private readonly ExamCandidateRosterRepository $roster,
    ) {
    }

    /** @return array<int, array<string, mixed>> preview rows (CandidateBulkImportService's shape, plus exam/existing_candidate_id) */
    public function parseFile(string $filePath, int $institutionId): array
    {
        $rows = $this->candidateImportService->parseFile($filePath, $institutionId);
        $examsByName = $this->indexExamsByName($institutionId);

        foreach ($rows as $index => $row) {
            $examName = trim((string) ($row['input']['exam'] ?? ''));
            $exam = $examName !== '' ? ($examsByName[strtolower($examName)] ?? null) : null;

            $errors = $row['errors'];
            if ($examName === '') {
                $errors['exam'] = __('No exam was specified for this row.', 'wp-cbt-pro');
            } elseif ($exam === null) {
                $errors['exam'] = sprintf(
                    /* translators: %s: the exam name/text from the spreadsheet row */
                    __('No exam named "%s" was found.', 'wp-cbt-pro'),
                    $examName
                );
            }

            $existing = $exam !== null
                ? $this->candidateRepository->findExistingForImportRow($row['input'], $institutionId)
                : null;

            $rows[$index]['exam'] = $exam;
            $rows[$index]['existing_candidate_id'] = $existing !== null ? (int) $existing['id'] : null;
            $rows[$index]['errors'] = $errors;

            if ($existing !== null) {
                // CandidateBulkImportService's own "already exists" warning
                // assumes creating a new candidate is always the intent —
                // here, matching an existing one is the desired outcome
                // (avoids a duplicate), so replace it with an accurate note.
                $rows[$index]['warnings'] = array_values(array_filter(
                    $row['warnings'],
                    static fn (string $w): bool => !str_contains($w, 'already exists')
                ));
                $rows[$index]['warnings'][] = __(
                    'Matches an existing candidate — will be added to this exam\'s roster without creating a duplicate record.',
                    'wp-cbt-pro'
                );
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row one of parseFile()'s preview rows
     * @return array{candidate_id:int, exam_id:int}|array{error:string}
     */
    public function importRow(array $row): array
    {
        $exam = $row['exam'];
        $candidateId = $row['existing_candidate_id'];

        if ($candidateId === null) {
            $result = $this->candidateImportService->import($row);
            if (isset($result['error'])) {
                return $result;
            }
            $candidateId = $result['candidate_id'];
        }

        $examId = (int) $exam['id'];
        $this->roster->add($examId, (int) $candidateId);

        if (empty($exam['restrict_to_roster'])) {
            $this->exams->update($examId, ['restrict_to_roster' => 1]);
        }

        return ['candidate_id' => (int) $candidateId, 'exam_id' => $examId];
    }

    /** @return array<string, array<string, mixed>> lowercase exam name => exam row */
    private function indexExamsByName(int $institutionId): array
    {
        $exams = $this->exams->paginate(['institution_id' => $institutionId, 'per_page' => 500]);

        $indexed = [];
        foreach ($exams as $exam) {
            $indexed[strtolower(trim((string) $exam['name']))] = $exam;
        }

        return $indexed;
    }
}
