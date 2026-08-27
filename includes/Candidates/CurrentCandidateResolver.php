<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

/**
 * The one place that turns "the logged-in WordPress user" into "the
 * candidate record they take exams as" — REST controllers and the exam
 * runtime both go through this rather than reading wp_user_id lookups
 * themselves.
 */
final class CurrentCandidateResolver
{
    public function __construct(private readonly CandidateRepository $repository)
    {
    }

    public function resolve(): ?array
    {
        $userId = get_current_user_id();
        if ($userId === 0) {
            return null;
        }

        return $this->repository->findByWpUserId($userId);
    }
}
