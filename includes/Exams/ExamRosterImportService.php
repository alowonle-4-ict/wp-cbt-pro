<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

use WPCBTPro\Candidates\CandidateBulkImportService;
use WPCBTPro\Candidates\CandidateRepository;

/**
 * "Upload candidates to an exam directly from an Excel file" (§ feature
 * backlog): reuses CandidateBulkImportService's own spreadsheet parsing and
 * validation exactly — same columns, same preview-then-confirm shape — but
 * each confirmed row is added to one exam's roster rather than only the
 * general candidate list. A row matching an existing candidate (by
 * registration number, then email) is added to the roster as-is instead of
 * being recreated, so uploading a subset of an institution's existing
 * candidates for one exam doesn't produce duplicates.
 */
final class ExamRosterImportService
{
    public function __construct(
        private readonly CandidateBulkImportService $candidateImportService,
        private readonly CandidateRepository $candidateRepository,
        private readonly ExamCandidateRosterRepository $roster,
    ) {
    }

    /** @return array<int, array<string, mixed>> preview rows (CandidateBulkImportService's shape, plus existing_candidate_id) */
    public function parseFile(string $filePath, int $institutionId): array
    {
        $rows = $this->candidateImportService->parseFile($filePath, $institutionId);

        foreach ($rows as $index => $row) {
            $existingId = $this->findExisting($row['input'], $institutionId);
            $rows[$index]['existing_candidate_id'] = $existingId;

            if ($existingId !== null) {
                // CandidateBulkImportService's own "already exists" warning
                // assumes creating a new candidate is always the intent —
                // here, matching an existing one is the intended, desired
                // outcome (avoids creating a duplicate), so replace it with
                // an accurate, non-alarming note instead.
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
     * @return array{candidate_id:int}|array{error:string}
     */
    public function importToExam(array $row, int $examId): array
    {
        $candidateId = $row['existing_candidate_id'];

        if ($candidateId === null) {
            $result = $this->candidateImportService->import($row);
            if (isset($result['error'])) {
                return $result;
            }
            $candidateId = $result['candidate_id'];
        }

        $this->roster->add($examId, (int) $candidateId);

        return ['candidate_id' => (int) $candidateId];
    }

    /** @param array<string, mixed> $input */
    private function findExisting(array $input, int $institutionId): ?int
    {
        if ($input['registration_number'] !== '') {
            $existing = $this->candidateRepository->findByRegistrationNumber($institutionId, $input['registration_number']);
            if ($existing !== null) {
                return (int) $existing['id'];
            }
        }

        if ($input['email'] !== '') {
            $existing = $this->candidateRepository->findByEmail($input['email']);
            if ($existing !== null && (int) $existing['institution_id'] === $institutionId) {
                return (int) $existing['id'];
            }
        }

        return null;
    }
}
