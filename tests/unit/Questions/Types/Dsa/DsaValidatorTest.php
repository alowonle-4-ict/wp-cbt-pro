<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Questions\Types\Dsa;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Questions\Types\Dsa\DsaValidator;

final class DsaValidatorTest extends TestCase
{
    private DsaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DsaValidator();
    }

    public function testUnansweredIsStructurallyValid(): void
    {
        self::assertSame([], $this->validator->validate(['dsa' => ['mode' => 'simulation']], null));
        self::assertSame([], $this->validator->validate(['dsa' => ['mode' => 'simulation']], ''));
    }

    public function testNonStringAnswerFails(): void
    {
        self::assertNotEmpty($this->validator->validate(['dsa' => ['mode' => 'simulation']], ['x']));
    }

    public function testSimulationModeAcceptsFreeText(): void
    {
        $question = ['dsa' => ['mode' => 'simulation']];

        self::assertSame([], $this->validator->validate($question, 'push 1, push 2, pop'));
    }

    public function testInteractiveModeAcceptsValidJson(): void
    {
        $question = ['dsa' => ['mode' => 'interactive']];

        self::assertSame([], $this->validator->validate($question, '[1,2,3]'));
    }

    public function testInteractiveModeAcceptsLiteralJsonNull(): void
    {
        $question = ['dsa' => ['mode' => 'interactive']];

        self::assertSame([], $this->validator->validate($question, 'null'));
    }

    public function testInteractiveModeRejectsUnparsableJson(): void
    {
        $question = ['dsa' => ['mode' => 'interactive']];

        self::assertNotEmpty($this->validator->validate($question, '{not json'));
    }

    public function testDefaultsToSimulationModeWhenModeMissing(): void
    {
        self::assertSame([], $this->validator->validate([], 'not json at all'));
    }
}
