<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Import\Word;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Import\Word\DocxQuestionParser;
use WPCBTPro\Import\Word\OmmlToMathMlConverter;

final class DocxQuestionParserTest extends TestCase
{
    private const XML_HEADER = <<<'XML'
    <w:document
        xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
        xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
        xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
        xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"
        xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    XML;

    private function documentWithImageRelationship(string $relationshipId): string
    {
        return self::XML_HEADER . <<<XML
        <w:body>
            <w:p><w:r><w:t>QUESTION 1</w:t></w:r></w:p>
            <w:p><w:r><w:t>TYPE: MCQ</w:t></w:r></w:p>
            <w:p><w:r><w:t>QUESTION:</w:t></w:r></w:p>
            <w:p><w:r><w:t>Identify the shape below:</w:t></w:r></w:p>
            <w:p><w:r>
                <w:drawing>
                    <wp:inline>
                        <a:graphic>
                            <a:graphicData>
                                <pic:pic>
                                    <pic:blipFill>
                                        <a:blip r:embed="{$relationshipId}"/>
                                    </pic:blipFill>
                                </pic:pic>
                            </a:graphicData>
                        </a:graphic>
                    </wp:inline>
                </w:drawing>
            </w:r></w:p>
            <w:p><w:r><w:t>A. Circle</w:t></w:r></w:p>
            <w:p><w:r><w:t>B. Square</w:t></w:r></w:p>
            <w:p><w:r><w:t>ANSWER: A</w:t></w:r></w:p>
        </w:body>
        </w:document>
        XML;
    }

    public function testAResolvedImageIsEmbeddedAsADataUriImgTag(): void
    {
        $xml = $this->documentWithImageRelationship('rId4');

        $blocks = (new DocxQuestionParser(new OmmlToMathMlConverter()))->parse(
            $xml,
            static fn (string $relId): ?string => $relId === 'rId4' ? 'data:image/png;base64,AAAA' : null
        );

        self::assertCount(1, $blocks);
        self::assertStringContainsString(
            '<img src="data:image/png;base64,AAAA" alt="">',
            $blocks[0]['body_html']
        );
        self::assertStringContainsString('Identify the shape below', $blocks[0]['body_html']);
    }

    public function testNoResolverOmitsTheImageInsteadOfBreaking(): void
    {
        $xml = $this->documentWithImageRelationship('rId4');

        $blocks = (new DocxQuestionParser(new OmmlToMathMlConverter()))->parse($xml);

        self::assertCount(1, $blocks);
        self::assertStringNotContainsString('<img', $blocks[0]['body_html']);
        self::assertStringContainsString('Identify the shape below', $blocks[0]['body_html']);
    }

    public function testAnUnresolvableRelationshipIdOmitsTheImage(): void
    {
        $xml = $this->documentWithImageRelationship('rId9');

        $blocks = (new DocxQuestionParser(new OmmlToMathMlConverter()))->parse(
            $xml,
            static fn (string $relId): ?string => null // e.g. an unsupported EMF/WMF image
        );

        self::assertCount(1, $blocks);
        self::assertStringNotContainsString('<img', $blocks[0]['body_html']);
    }
}
