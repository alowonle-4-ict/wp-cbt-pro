<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

use WPCBTPro\Attempts\ExamRuntimeController;
use WPCBTPro\Exams\ExamCandidateRosterRepository;
use WPCBTPro\Exams\ExamRepository;

/**
 * Which exams a given candidate can currently open, and where — used right
 * after login to skip straight to "the" exam when there's exactly one,
 * instead of leaving the candidate stranded on the homepage with no idea
 * where to go next (the login page itself has no concept of "which exam").
 */
final class CandidateExamFinder
{
    public function __construct(
        private readonly ExamRepository $exams,
        private readonly ExamCandidateRosterRepository $roster,
    ) {
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<int, array{exam: array<string, mixed>, url: string}>
     */
    public function availableFor(array $candidate): array
    {
        $exams = $this->exams->paginate([
            'institution_id' => (int) $candidate['institution_id'],
            'per_page' => 200,
        ]);

        $available = [];
        foreach ($exams as $exam) {
            if ($exam['status'] !== 'active') {
                continue;
            }

            if (
                !empty($exam['restrict_to_roster'])
                && !$this->roster->isMember((int) $exam['id'], (int) $candidate['id'])
            ) {
                continue;
            }

            $url = ExamRuntimeController::examUrl((int) $exam['id']);
            if ($url === null) {
                continue; // nobody has embedded this exam on any page yet
            }

            $available[] = ['exam' => $exam, 'url' => $url];
        }

        return $available;
    }
}
