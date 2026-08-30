<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Import;

use WPCBTPro\Core\Plugin;
use WPCBTPro\Import\Word\WordImportService;

/**
 * WordImportPreviewTest exercises the .docx path against the real bundled
 * template; this is the .txt counterpart — proving the whole
 * WordImportService::parseFile()/buildPreviewRow() pipeline (not just
 * TxtQuestionParser in isolation, which TxtQuestionParserTest already
 * covers) correctly maps a real .txt file into an importable row using
 * the user's own example format.
 */
final class TxtImportTest extends \WP_UnitTestCase
{
    public function testAPlainTxtFileMapsToTwoImportableMcqRows(): void
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

        $tmpFile = tempnam(sys_get_temp_dir(), 'wpcbtpro-txt-import-');
        file_put_contents($tmpFile, $text);

        try {
            /** @var WordImportService $importService */
            $importService = Plugin::instance()->container()->get(WordImportService::class);
            $rows = $importService->parseFile($tmpFile, 'txt');
        } finally {
            unlink($tmpFile);
        }

        self::assertCount(2, $rows);

        self::assertSame('mcq_single', $rows[0]['type_id']);
        // No MARKS: line in this (deliberately minimal, matching the user's own
        // example) file — SingleChoiceImportHandler::validate() warns and
        // defaults to 1, exactly as it already does for a .docx block missing
        // MARKS, confirming the shared block shape gets the same validation
        // either way rather than .txt silently skipping it.
        self::assertSame(['No MARKS value was found; defaulting to 1.'], $rows[0]['warnings']);
        self::assertNotNull($rows[0]['mapped']);
        self::assertSame(1.0, $rows[0]['mapped']['marks']);
        self::assertCount(4, $rows[0]['mapped']['options']);

        $correctOptionB = array_values(array_filter(
            $rows[0]['mapped']['options'],
            static fn (array $o): bool => !empty($o['is_correct'])
        ))[0];
        self::assertSame('Kunle', $correctOptionB['label']);

        $correctOptionA = array_values(array_filter(
            $rows[1]['mapped']['options'],
            static fn (array $o): bool => !empty($o['is_correct'])
        ))[0];
        self::assertSame('Ade', $correctOptionA['label']);
    }
}
