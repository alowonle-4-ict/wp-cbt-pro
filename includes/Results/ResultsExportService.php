<?php

declare(strict_types=1);

namespace WPCBTPro\Results;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use WPCBTPro\Candidates\CandidateRepository;

/**
 * The data-shaping half of results export — CSV/XLSX rows built once here,
 * so ResultsExportController (headers, admin_post glue, exit) has no logic
 * worth testing on its own and this does, the same split used for every
 * other import/export feature in this plugin.
 */
final class ResultsExportService
{
    public const COLUMNS = [
        'Candidate Name', 'Registration Number', 'Department', 'Level', 'Candidate ID',
        'Score', 'Percentage', 'Grade', 'Pass/Fail', 'Correct', 'Incorrect',
        'Unanswered', 'Pending Review', 'Status', 'Time Used (s)', 'Submitted At', 'Released',
    ];

    public function __construct(
        private readonly ResultRepository $results,
        private readonly CandidateRepository $candidates,
    ) {
    }

    /** @return array<int, array<int, mixed>> one row per result, in COLUMNS order */
    public function buildRows(int $examId): array
    {
        $examResults = $this->results->allForExam($examId);
        $candidates = $this->candidates->findMany(array_column($examResults, 'candidate_id'));

        $rows = [];
        foreach ($examResults as $result) {
            $candidate = $candidates[(int) $result['candidate_id']] ?? null;
            if ($candidate === null) {
                continue;
            }

            $rows[] = [
                trim($candidate['first_name'] . ' ' . $candidate['last_name']),
                $candidate['registration_number'] ?? '',
                $candidate['department'] ?? '',
                $candidate['class'] ?? '',
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
            ];
        }

        return $rows;
    }

    /** @param array<int, array<int, mixed>> $rows */
    public function buildSpreadsheet(array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::COLUMNS, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        return $spreadsheet;
    }
}
