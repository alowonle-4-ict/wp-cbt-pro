<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Import\Word;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Import\Word\OmmlToMathMlConverter;

final class OmmlToMathMlConverterTest extends TestCase
{
    private const M_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    private OmmlToMathMlConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new OmmlToMathMlConverter();
    }

    private function parseOMath(string $innerXml): \DOMElement
    {
        $xml = '<m:oMath xmlns:m="' . self::M_NS . '">' . $innerXml . '</m:oMath>';
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        return $doc->documentElement;
    }

    private function textRun(string $text): string
    {
        return '<m:r><m:t>' . $text . '</m:t></m:r>';
    }

    public function testConvertWrapsOutputInMathMlRootElement(): void
    {
        $result = $this->converter->convert($this->parseOMath($this->textRun('x')));

        self::assertStringStartsWith('<math xmlns="http://www.w3.org/1998/Math/MathML">', $result);
        self::assertStringEndsWith('</math>', $result);
    }

    public function testPlainRunTokenizesNumbersOperatorsAndIdentifiers(): void
    {
        $result = $this->converter->convert($this->parseOMath($this->textRun('x+2')));

        self::assertStringContainsString('<mi>x</mi>', $result);
        self::assertStringContainsString('<mo>+</mo>', $result);
        self::assertStringContainsString('<mn>2</mn>', $result);
    }

    public function testFractionConvertsToMfrac(): void
    {
        $xml = '<m:f><m:num>' . $this->textRun('1') . '</m:num><m:den>' . $this->textRun('2') . '</m:den></m:f>';

        $result = $this->converter->convert($this->parseOMath($xml));

        self::assertStringContainsString('<mfrac>', $result);
        self::assertStringContainsString('<mn>1</mn>', $result);
        self::assertStringContainsString('<mn>2</mn>', $result);
    }

    public function testSquareRootWithHiddenDegreeConvertsToMsqrt(): void
    {
        $xml = '<m:rad><m:radPr><m:degHide m:val="1"/></m:radPr><m:deg></m:deg><m:e>' . $this->textRun('4') . '</m:e></m:rad>';

        $result = $this->converter->convert($this->parseOMath($xml));

        self::assertStringContainsString('<msqrt>', $result);
        self::assertStringNotContainsString('<mroot>', $result);
    }

    public function testNthRootWithVisibleDegreeConvertsToMroot(): void
    {
        $xml = '<m:rad><m:deg>' . $this->textRun('3') . '</m:deg><m:e>' . $this->textRun('8') . '</m:e></m:rad>';

        $result = $this->converter->convert($this->parseOMath($xml));

        self::assertStringContainsString('<mroot>', $result);
    }

    public function testSuperscriptConvertsToMsup(): void
    {
        $xml = '<m:sSup><m:e>' . $this->textRun('x') . '</m:e><m:sup>' . $this->textRun('2') . '</m:sup></m:sSup>';

        $result = $this->converter->convert($this->parseOMath($xml));

        self::assertStringContainsString('<msup>', $result);
    }

    public function testSubscriptConvertsToMsub(): void
    {
        $xml = '<m:sSub><m:e>' . $this->textRun('a') . '</m:e><m:sub>' . $this->textRun('i') . '</m:sub></m:sSub>';

        $result = $this->converter->convert($this->parseOMath($xml));

        self::assertStringContainsString('<msub>', $result);
    }

    public function testNaryWithoutBoundsRendersPlainOperator(): void
    {
        $xml = '<m:nary><m:e>' . $this->textRun('x') . '</m:e></m:nary>';

        $result = $this->converter->convert($this->parseOMath($xml));

        self::assertStringContainsString('&#8721;', $result);
        self::assertStringNotContainsString('munderover', $result);
    }

    public function testNaryWithBoundsRendersMunderover(): void
    {
        $xml = '<m:nary><m:sub>' . $this->textRun('i=1') . '</m:sub><m:sup>' . $this->textRun('n') . '</m:sup><m:e>' . $this->textRun('i') . '</m:e></m:nary>';

        $result = $this->converter->convert($this->parseOMath($xml));

        self::assertStringContainsString('<munderover>', $result);
    }

    public function testDelimiterDefaultsToParentheses(): void
    {
        $xml = '<m:d><m:e>' . $this->textRun('x') . '</m:e></m:d>';

        $result = $this->converter->convert($this->parseOMath($xml));

        self::assertStringContainsString('<mo>(</mo>', $result);
        self::assertStringContainsString('<mo>)</mo>', $result);
    }

    public function testMatrixConvertsRowsAndCellsToMtableStructure(): void
    {
        $cell = static fn (string $text): string => '<m:e>' . $text . '</m:e>';
        $row = static fn (string $cells): string => '<m:mr>' . $cells . '</m:mr>';
        $xml = '<m:m>' . $row($cell($this->textRun('1')) . $cell($this->textRun('2'))) . '</m:m>';

        $result = $this->converter->convert($this->parseOMath($xml));

        self::assertStringContainsString('<mtable>', $result);
        self::assertStringContainsString('<mtr>', $result);
        self::assertSame(2, substr_count($result, '<mtd>'));
    }

    public function testUnsupportedConstructFallsBackToTextAndRecordsWarning(): void
    {
        $xml = '<m:eqArr>fallback text</m:eqArr>';

        $result = $this->converter->convert($this->parseOMath($xml));

        self::assertStringContainsString('<mtext>fallback text</mtext>', $result);
        self::assertNotEmpty($this->converter->warnings());
        self::assertStringContainsString('m:eqArr', $this->converter->warnings()[0]);
    }

    public function testWarningsResetBetweenConvertCalls(): void
    {
        $this->converter->convert($this->parseOMath('<m:eqArr>bad</m:eqArr>'));
        self::assertNotEmpty($this->converter->warnings());

        $this->converter->convert($this->parseOMath($this->textRun('x')));
        self::assertSame([], $this->converter->warnings());
    }

    public function testAllowedKsesTagsIncludesMathMlRootAndPermitsXmlnsAttribute(): void
    {
        $tags = OmmlToMathMlConverter::allowedKsesTags();

        self::assertArrayHasKey('math', $tags);
        self::assertTrue($tags['math']['xmlns']);
        self::assertArrayHasKey('mfrac', $tags);
    }
}
