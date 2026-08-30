<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Import;

use WPCBTPro\Core\Plugin;
use WPCBTPro\Import\Word\OmmlToMathMlConverter;
use WPCBTPro\Import\Word\WordImportService;

/**
 * Renders the real Import Preview view against the plugin's own bundled
 * templates/wp-cbt-pro-question-template.docx — the exact file real
 * institutions are told to download and fill in. Written after finding
 * admin/views/import-preview.php called `self::mathmlAllowedHtml()`, a
 * method that doesn't exist anywhere (the correct $mathmlAllowedHtml
 * variable was one line above it) — a fatal that only ever showed up when
 * previewing a real question with options, i.e. every real import. No
 * unit test caught it because none of them render the view; this one does.
 */
final class WordImportPreviewTest extends \WP_UnitTestCase
{
    public function testBundledTemplateRendersWithoutFatalAndFlagsUnsupportedDsaClearly(): void
    {
        /** @var WordImportService $importService */
        $importService = Plugin::instance()->container()->get(WordImportService::class);

        $templatePath = WPCBTPRO_PATH . 'templates/wp-cbt-pro-question-template.docx';
        self::assertFileExists($templatePath, 'The bundled question template is missing.');

        $rows = $importService->parseFile($templatePath);
        self::assertNotEmpty($rows, 'Expected at least one question block from the bundled template.');

        $session = 'test-session';
        $mathmlAllowedHtml = array_merge(wp_kses_allowed_html('post'), OmmlToMathMlConverter::allowedKsesTags());
        $mathmlAllowedProtocols = array_merge(wp_allowed_protocols(), ['data']);

        ob_start();
        include WPCBTPRO_PATH . 'admin/views/import-preview.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Solve the equation', $html);
        self::assertStringContainsString('<math', $html, 'The equation should have converted to MathML in the preview.');

        // DsaType::importHandler() deliberately returns null (DSA questions
        // are built in wp-admin, not importable) — the template's DSA
        // example should say so plainly, not "Unknown or unsupported type".
        self::assertStringContainsString('cannot be created via Word import yet', $html);
        self::assertStringNotContainsString('Unknown or unsupported question type', $html);
    }
}
