<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Questions\Types\Dsa;

use PHPUnit\Framework\TestCase;
use WPCBTPro\DSA\Contracts\StructureDefinition;
use WPCBTPro\DSA\Registry\StructureRegistry;
use WPCBTPro\Questions\Types\Dsa\DsaScoringStrategy;

final class DsaScoringStrategyTest extends TestCase
{
    private function stackStub(): StructureDefinition
    {
        $stub = $this->createStub(StructureDefinition::class);
        $stub->method('id')->willReturn('stack');
        $stub->method('simulate')->willReturn([10, 20, 30]);
        $stub->method('parseStatedAnswer')->willReturnCallback(
            static fn (string $text): array => array_map('intval', array_filter(explode(',', $text), 'strlen'))
        );
        $stub->method('parseInteractiveState')->willReturnCallback(
            static fn (array $decoded): array => $decoded
        );
        $stub->method('formatState')->willReturnCallback(
            static fn (array $state): string => implode(',', $state)
        );

        return $stub;
    }

    private function registryWithStack(): StructureRegistry
    {
        $registry = new StructureRegistry();
        $registry->register($this->stackStub());

        return $registry;
    }

    public function testUnknownStructurePendsManualReview(): void
    {
        $strategy = new DsaScoringStrategy(new StructureRegistry());

        $question = ['marks' => 10.0, 'dsa' => ['structure' => 'stack', 'operations' => [], 'mode' => 'simulation']];
        $score = $strategy->score($question, ['value' => '10,20,30']);

        self::assertSame(0.0, $score->earned);
        self::assertNull($score->isCorrect);
        self::assertSame('pending_review', $score->breakdown['status']);
    }

    public function testMissingDsaMetadataPendsManualReview(): void
    {
        $strategy = new DsaScoringStrategy(new StructureRegistry());

        $score = $strategy->score(['marks' => 10.0], ['value' => 'anything']);

        self::assertNull($score->isCorrect);
    }

    public function testExactSimulationAnswerEarnsFullMarks(): void
    {
        $strategy = new DsaScoringStrategy($this->registryWithStack());

        $question = ['marks' => 10.0, 'dsa' => ['structure' => 'stack', 'operations' => [], 'mode' => 'simulation']];
        $score = $strategy->score($question, ['value' => '10,20,30']);

        self::assertSame(10.0, $score->earned);
        self::assertTrue($score->isCorrect);
    }

    public function testPartiallyCorrectSimulationAnswerEarnsProportionalCredit(): void
    {
        $strategy = new DsaScoringStrategy($this->registryWithStack());

        $question = ['marks' => 9.0, 'dsa' => ['structure' => 'stack', 'operations' => [], 'mode' => 'simulation']];
        // expected [10, 20, 30]; candidate matches only the first position.
        $score = $strategy->score($question, ['value' => '10,99,99']);

        self::assertSame(3.0, $score->earned);
        self::assertFalse($score->isCorrect);
    }

    public function testInteractiveModeParsesDecodedJsonState(): void
    {
        $strategy = new DsaScoringStrategy($this->registryWithStack());

        $question = ['marks' => 10.0, 'dsa' => ['structure' => 'stack', 'operations' => [], 'mode' => 'interactive']];
        $score = $strategy->score($question, ['value' => '[10,20,30]']);

        self::assertSame(10.0, $score->earned);
        self::assertTrue($score->isCorrect);
    }

    public function testInteractiveModeWithUnparsableJsonEarnsZero(): void
    {
        $strategy = new DsaScoringStrategy($this->registryWithStack());

        $question = ['marks' => 10.0, 'dsa' => ['structure' => 'stack', 'operations' => [], 'mode' => 'interactive']];
        $score = $strategy->score($question, ['value' => 'not json']);

        self::assertSame(0.0, $score->earned);
        self::assertFalse($score->isCorrect);
    }
}
