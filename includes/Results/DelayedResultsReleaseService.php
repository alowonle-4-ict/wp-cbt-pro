<?php

declare(strict_types=1);

namespace WPCBTPro\Results;

use WPCBTPro\Exams\ExamRepository;

/**
 * The automatic half of §34's "delayed results" option — the manual half
 * is an explicit admin action (ResultsAdminController::handleReleaseAll).
 * A WP-Cron worker, not anything a candidate's request ever triggers.
 */
final class DelayedResultsReleaseService
{
    public function __construct(
        private readonly ExamRepository $exams,
        private readonly ResultRepository $results,
    ) {
    }

    public function run(): void
    {
        foreach ($this->exams->findDelayedReleaseDue() as $exam) {
            $this->results->releaseAllForExam((int) $exam['id']);
        }
    }
}
