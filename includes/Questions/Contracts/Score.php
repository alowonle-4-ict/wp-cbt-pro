<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

/**
 * The only shape a ScoringStrategy is allowed to hand back to the grading
 * pipeline — server-side and immutable, so nothing downstream can mistake a
 * client-supplied number for a real score (§19).
 */
final class Score
{
    /**
     * @param array<string, mixed> $breakdown type-specific detail (e.g. per-test-case results)
     */
    public function __construct(
        public readonly float $earned,
        public readonly float $max,
        public readonly ?bool $isCorrect = null,
        public readonly array $breakdown = [],
    ) {
    }

    public static function unanswered(float $max): self
    {
        return new self(0.0, $max, null);
    }

    public static function pendingManualReview(float $max): self
    {
        return new self(0.0, $max, null, ['status' => 'pending_review']);
    }

    public function percentage(): float
    {
        return $this->max > 0.0 ? round(($this->earned / $this->max) * 100, 2) : 0.0;
    }
}
