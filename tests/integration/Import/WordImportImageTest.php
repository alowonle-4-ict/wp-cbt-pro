<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Import;

use WPCBTPro\Core\Plugin;
use WPCBTPro\Import\Word\EmbeddedImageUploader;
use WPCBTPro\Import\Word\OmmlToMathMlConverter;
use WPCBTPro\Import\Word\WordImportService;

/**
 * Exercises the real embedded-image pipeline end to end against a genuine
 * .docx fixture (tests/fixtures/question-with-image.docx, built with a real
 * GD-rendered PNG so the ZIP/relationship/binary plumbing is proven, not
 * just the XML recognition unit tests already cover):
 * parseFile() -> data: URI in the preview HTML -> EmbeddedImageUploader
 * turns it into a real wp attachment on confirm, exactly what
 * WordImportAdminController::handleConfirm() does with the confirmed rows.
 */
final class WordImportImageTest extends \WP_UnitTestCase
{
    public function testAnEmbeddedImageBecomesADataUriInPreviewThenARealAttachmentOnConfirm(): void
    {
        /** @var WordImportService $importService */
        $importService = Plugin::instance()->container()->get(WordImportService::class);

        $fixturePath = WPCBTPRO_PATH . 'tests/fixtures/question-with-image.docx';
        self::assertFileExists($fixturePath);

        $rows = $importService->parseFile($fixturePath);
        self::assertCount(1, $rows);

        $content = (string) $rows[0]['mapped']['content'];
        self::assertMatchesRegularExpression('#<img src="data:image/png;base64,[A-Za-z0-9+/=]+" alt="">#', $content);

        // The preview view must actually be able to render this: WordPress's
        // default kses protocol allowlist doesn't include "data", so this
        // proves the preview's expanded allowlist (see
        // WordImportAdminController::renderPreview()) really is required —
        // without it wp_kses() would silently strip the src attribute.
        $mathmlAllowedHtml = array_merge(wp_kses_allowed_html('post'), OmmlToMathMlConverter::allowedKsesTags());
        $strippedWithDefaultProtocols = wp_kses($content, $mathmlAllowedHtml);
        self::assertStringNotContainsString('data:image', $strippedWithDefaultProtocols, 'Sanity check: without the expanded protocol allowlist, wp_kses() should strip the data: URI.');

        $mathmlAllowedProtocols = array_merge(wp_allowed_protocols(), ['data']);
        $renderedInPreview = wp_kses($content, $mathmlAllowedHtml, $mathmlAllowedProtocols);
        self::assertStringContainsString('data:image', $renderedInPreview);

        /** @var EmbeddedImageUploader $uploader */
        $uploader = Plugin::instance()->container()->get(EmbeddedImageUploader::class);
        $persisted = $uploader->persist($content, 'wpcbtpro-question-test');

        self::assertStringNotContainsString('data:image', $persisted, 'The data: URI should have been replaced with a real attachment URL.');
        self::assertMatchesRegularExpression('#<img src="https?://[^"]+\.png" alt="">#', $persisted);

        preg_match('#<img src="([^"]+)"#', $persisted, $m);
        $attachmentId = attachment_url_to_postid($m[1]);
        self::assertGreaterThan(0, $attachmentId, 'The rewritten URL should resolve to a real attachment.');
        self::assertSame('image/png', get_post_mime_type($attachmentId));
    }
}
