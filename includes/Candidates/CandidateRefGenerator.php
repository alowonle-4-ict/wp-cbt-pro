<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

final class CandidateRefGenerator
{
    public function __construct(private readonly CandidateRepository $repository)
    {
    }

    public function generate(int $institutionId, ?string $year = null): string
    {
        $year ??= gmdate('Y');
        $sequence = $this->repository->countForInstitutionYear($institutionId, $year) + 1;

        do {
            $ref = sprintf('CBT-%s-%05d', $year, $sequence);
            $sequence++;
        } while ($this->repository->findByRef($ref) !== null);

        return $ref;
    }
}
