<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Import\Word;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Import\Word\TxtQuestionParser;

final class TxtQuestionParserTest extends TestCase
{
    public function testParsesTwoQuestionsSeparatedByABlankLine(): void
    {
        $text = <<<'TXT'
        What is your name?
        A. Ade
        B. Kunle
        C. Dada
        D. Abdul
        ANSWER: B

        What is your name?
        A. Ade
        B. Kunle
        C. Dada
        D. Abdul
        ANSWER: A
        TXT;

        $blocks = (new TxtQuestionParser())->parse($text);

        self::assertCount(2, $blocks);

        self::assertSame(1, $blocks[0]['index']);
        self::assertSame('MCQ_SINGLE', $blocks[0]['type']);
        self::assertSame('<p>What is your name?</p>', $blocks[0]['body_html']);
        self::assertSame('B', $blocks[0]['answer']);
        self::assertCount(4, $blocks[0]['options']);
        self::assertSame(['letter' => 'A', 'html' => 'Ade'], $blocks[0]['options'][0]);
        self::assertSame(['letter' => 'D', 'html' => 'Abdul'], $blocks[0]['options'][3]);

        self::assertSame('A', $blocks[1]['answer']);
    }

    public function testOptionalMetadataLinesAreRecognized(): void
    {
        $text = <<<'TXT'
        SUBJECT: Geography
        TOPIC: Capitals
        What is the capital of Nigeria?
        A. Lagos
        B. Abuja
        MARKS: 2
        NEGATIVE: 0.5
        ANSWER: B
        TXT;

        $blocks = (new TxtQuestionParser())->parse($text);

        self::assertSame('Geography', $blocks[0]['subject']);
        self::assertSame('Capitals', $blocks[0]['topic']);
        self::assertSame(2.0, $blocks[0]['marks']);
        self::assertSame(0.5, $blocks[0]['negative']);
        self::assertSame('B', $blocks[0]['answer']);
    }

    public function testMinimalBlockWithNoOptionalLinesStillParses(): void
    {
        $blocks = (new TxtQuestionParser())->parse("Is the sky blue?\nA. Yes\nB. No");

        self::assertCount(1, $blocks);
        self::assertSame('', $blocks[0]['subject']);
        self::assertNull($blocks[0]['marks']);
        self::assertSame('', $blocks[0]['answer']);
        self::assertCount(2, $blocks[0]['options']);
    }

    public function testBlankInputProducesNoBlocks(): void
    {
        self::assertSame([], (new TxtQuestionParser())->parse("\n\n   \n"));
    }

    public function testAcceptsADotOrParenAfterTheOptionLetter(): void
    {
        $blocks = (new TxtQuestionParser())->parse("Pick one\nA) First\nB. Second");

        self::assertSame('First', $blocks[0]['options'][0]['html']);
        self::assertSame('Second', $blocks[0]['options'][1]['html']);
    }

    public function testOptionTextIsHtmlEscaped(): void
    {
        $blocks = (new TxtQuestionParser())->parse("Which is bigger?\nA. 2 < 5\nB. 5 < 2");

        self::assertSame('2 &lt; 5', $blocks[0]['options'][0]['html']);
    }
}
