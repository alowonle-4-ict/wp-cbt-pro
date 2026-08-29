<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Questions;

use WPCBTPro\Core\Plugin;
use WPCBTPro\Questions\Registry\QuestionTypeRegistry;

/**
 * Guards against the exact bug this test was written after: SingleChoiceType,
 * ProgrammingType, and DsaType all declared `renderer(): QuestionRenderer {
 * return $this; }` without actually `implements QuestionRenderer` — a
 * fatal TypeError that only ever showed up when a candidate's browser
 * rendered a real question, since neither the unit suite nor the REST-only
 * integration tests ever called renderer()/renderPrompt(). 67 unit tests and
 * 15 REST-level integration tests all stayed green while every question
 * type in the plugin crashed the moment anyone actually took an exam.
 */
final class QuestionTypeRenderingTest extends \WP_UnitTestCase
{
    public function testEveryRegisteredTypesRendererProducesSafeHtml(): void
    {
        /** @var QuestionTypeRegistry $registry */
        $registry = Plugin::instance()->container()->get(QuestionTypeRegistry::class);

        self::assertNotEmpty($registry->all(), 'No question types are registered — the registry itself is broken.');

        foreach ($registry->all() as $type) {
            $renderer = $type->renderer();
            $html = $renderer->renderPrompt(['content' => '<p>Sample prompt</p><script>alert(1)</script>']);

            self::assertIsString(
                $html,
                sprintf('%s::renderer()->renderPrompt() did not return a string.', get_class($type))
            );
            self::assertStringNotContainsString(
                '<script',
                $html,
                sprintf('%s renders unsanitized content — <script> survived renderPrompt().', $type->id())
            );
        }
    }
}
