<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Questions\Types\Mcq;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Questions\Types\Mcq\SingleChoiceScoringStrategy;

final class SingleChoiceScoringStrategyTest extends TestCase
{
    private SingleChoiceScoringStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new SingleChoiceScoringStrategy();
    }

    private function question(float $marks = 5.0, float $negative = 0.0): array
    {
        return [
            'marks' => $marks,
            'negative_marks' => $negative,
            'options' => [
                ['id' => 1, 'is_correct' => false],
                ['id' => 2, 'is_correct' => true],
            ],
        ];
    }

    public function testCorrectAnswerEarnsFullMarks(): void
    {
        $score = $this->strategy->score($this->question(), ['value' => '2']);

        self::assertSame(5.0, $score->earned);
        self::assertSame(5.0, $score->max);
        self::assertTrue($score->isCorrect);
    }

    public function testIncorrectAnswerWithNoNegativeMarkingEarnsZero(): void
    {
        $score = $this->strategy->score($this->question(), ['value' => '1']);

        self::assertSame(0.0, $score->earned);
        self::assertFalse($score->isCorrect);
    }

    public function testIncorrectAnswerAppliesNegativeMarking(): void
    {
        $score = $this->strategy->score($this->question(marks: 5.0, negative: 1.25), ['value' => '1']);

        self::assertSame(-1.25, $score->earned);
        self::assertFalse($score->isCorrect);
    }

    public function testQuestionWithNoCorrectOptionFlaggedIsNeverCorrect(): void
    {
        $question = $this->question();
        $question['options'] = [
            ['id' => 1, 'is_correct' => false],
            ['id' => 2, 'is_correct' => false],
        ];

        $score = $this->strategy->score($question, ['value' => '2']);

        self::assertFalse($score->isCorrect);
    }
}
