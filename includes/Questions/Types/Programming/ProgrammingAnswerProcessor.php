<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Programming;

use WPCBTPro\Questions\Contracts\AnswerProcessor;

/**
 * Code is stored verbatim — no HTML stripping, no trimming of meaningful
 * whitespace/indentation. Only null bytes are removed, since MySQL cannot
 * store them.
 */
final class ProgrammingAnswerProcessor implements AnswerProcessor
{
    public function process(array $question, mixed $rawAnswer): string
    {
        return str_replace("\0", '', (string) $rawAnswer);
    }
}
