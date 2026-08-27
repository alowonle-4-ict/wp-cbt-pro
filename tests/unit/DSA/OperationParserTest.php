<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\DSA;

use PHPUnit\Framework\TestCase;
use WPCBTPro\DSA\Contracts\StructureDefinition;
use WPCBTPro\DSA\OperationParser;

final class OperationParserTest extends TestCase
{
    private OperationParser $parser;
    private StructureDefinition $stack;

    protected function setUp(): void
    {
        $this->parser = new OperationParser();

        $this->stack = $this->createStub(StructureDefinition::class);
        $this->stack->method('allowedOperations')->willReturn(['PUSH', 'POP']);
        $this->stack->method('label')->willReturn('Stack');
    }

    public function testParsesOperationsWithAndWithoutArguments(): void
    {
        $result = $this->parser->parse("PUSH(10)\nPOP()\nPUSH(20)", $this->stack);

        self::assertSame([
            ['op' => 'PUSH', 'arg' => '10'],
            ['op' => 'POP', 'arg' => null],
            ['op' => 'PUSH', 'arg' => '20'],
        ], $result);
    }

    public function testParseIsCaseInsensitiveOnOperationKeyword(): void
    {
        $result = $this->parser->parse('push(5)', $this->stack);

        self::assertSame([['op' => 'PUSH', 'arg' => '5']], $result);
    }

    public function testParseIgnoresBlankLines(): void
    {
        $result = $this->parser->parse("PUSH(1)\n\n\nPUSH(2)\n", $this->stack);

        self::assertCount(2, $result);
    }

    public function testParseThrowsOnMalformedLine(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse('PUSH 10', $this->stack);
    }

    public function testParseThrowsOnOperationNotAllowedByStructure(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse('ENQUEUE(1)', $this->stack);
    }

    public function testFormatRoundTripsWithParse(): void
    {
        $operations = [
            ['op' => 'PUSH', 'arg' => '10'],
            ['op' => 'POP', 'arg' => null],
        ];

        $formatted = $this->parser->format($operations);
        $reparsed = $this->parser->parse($formatted, $this->stack);

        self::assertSame($operations, $reparsed);
    }
}
