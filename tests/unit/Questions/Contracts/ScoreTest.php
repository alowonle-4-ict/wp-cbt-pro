<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Questions\Contracts;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Questions\Contracts\Score;

final class ScoreTest extends TestCase
{
    public function testPercentageComputesEarnedOverMax(): void
    {
        $score = new Score(3.0, 4.0, true);

        self::assertSame(75.0, $score->percentage());
    }

    public function testPercentageIsZeroWhenMaxIsZero(): void
    {
        $score = new Score(0.0, 0.0, null);

        self::assertSame(0.0, $score->percentage());
    }

    public function testUnansweredFactoryHasNoEarnedMarksAndNullCorrectness(): void
    {
        $score = Score::unanswered(5.0);

        self::assertSame(0.0, $score->earned);
        self::assertSame(5.0, $score->max);
        self::assertNull($score->isCorrect);
    }

    public function testPendingManualReviewFactoryFlagsBreakdownStatus(): void
    {
        $score = Score::pendingManualReview(10.0);

        self::assertSame('pending_review', $score->breakdown['status']);
        self::assertNull($score->isCorrect);
    }

    public function testNegativeEarnedFromPenaltyMarkingStillComputesPercentage(): void
    {
        $score = new Score(-1.25, 5.0, false);

        self::assertSame(-25.0, $score->percentage());
    }
}
