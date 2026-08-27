<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Programming\Registry;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Programming\Contracts\LanguageDefinition;
use WPCBTPro\Programming\Registry\LanguageRegistry;

final class LanguageRegistryTest extends TestCase
{
    private function languageStub(string $id): LanguageDefinition
    {
        $stub = $this->createStub(LanguageDefinition::class);
        $stub->method('id')->willReturn($id);

        return $stub;
    }

    public function testRegisterAndGet(): void
    {
        $registry = new LanguageRegistry();
        $python = $this->languageStub('python3');

        $registry->register($python);

        self::assertTrue($registry->has('python3'));
        self::assertFalse($registry->has('java'));
        self::assertSame($python, $registry->get('python3'));
    }

    public function testGetUnknownLanguageThrows(): void
    {
        $registry = new LanguageRegistry();

        $this->expectException(\OutOfBoundsException::class);

        $registry->get('cobol');
    }

    public function testRegisterDuplicateIdThrows(): void
    {
        $registry = new LanguageRegistry();
        $registry->register($this->languageStub('python3'));

        $this->expectException(\InvalidArgumentException::class);

        $registry->register($this->languageStub('python3'));
    }

    public function testAllReturnsEveryRegisteredLanguageKeyedById(): void
    {
        $registry = new LanguageRegistry();
        $registry->register($this->languageStub('python3'));
        $registry->register($this->languageStub('java'));

        self::assertSame(['python3', 'java'], array_keys($registry->all()));
    }
}
