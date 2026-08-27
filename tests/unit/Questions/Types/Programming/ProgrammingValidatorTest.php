<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Questions\Types\Programming;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Questions\Types\Programming\ProgrammingValidator;

final class ProgrammingValidatorTest extends TestCase
{
    private ProgrammingValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ProgrammingValidator();
    }

    public function testStringSourceIsValid(): void
    {
        self::assertSame([], $this->validator->validate([], 'print("hi")'));
    }

    public function testEmptyStringIsStructurallyValid(): void
    {
        self::assertSame([], $this->validator->validate([], ''));
    }

    public function testNonStringSourceFails(): void
    {
        self::assertNotEmpty($this->validator->validate([], ['not', 'a', 'string']));
        self::assertNotEmpty($this->validator->validate([], null));
        self::assertNotEmpty($this->validator->validate([], 42));
    }
}
