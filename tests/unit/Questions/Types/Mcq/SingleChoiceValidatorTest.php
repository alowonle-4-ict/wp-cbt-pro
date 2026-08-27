<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Questions\Types\Mcq;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Questions\Types\Mcq\SingleChoiceValidator;

final class SingleChoiceValidatorTest extends TestCase
{
    private SingleChoiceValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SingleChoiceValidator();
    }

    private function question(): array
    {
        return [
            'options' => [
                ['id' => 1, 'is_correct' => false],
                ['id' => 2, 'is_correct' => true],
            ],
        ];
    }

    public function testValidOptionIdPasses(): void
    {
        self::assertSame([], $this->validator->validate($this->question(), '2'));
    }

    public function testValidOptionIdAsArrayPasses(): void
    {
        self::assertSame([], $this->validator->validate($this->question(), ['1']));
    }

    public function testUnansweredIsStructurallyValid(): void
    {
        self::assertSame([], $this->validator->validate($this->question(), null));
        self::assertSame([], $this->validator->validate($this->question(), ''));
    }

    public function testOptionIdNotBelongingToQuestionFails(): void
    {
        $errors = $this->validator->validate($this->question(), '999');

        self::assertNotEmpty($errors);
    }

    public function testQuestionWithNoOptionsSkipsMembershipCheck(): void
    {
        self::assertSame([], $this->validator->validate(['options' => []], 'anything'));
    }
}
