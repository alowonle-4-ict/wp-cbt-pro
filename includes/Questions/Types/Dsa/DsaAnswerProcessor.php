<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Dsa;

use WPCBTPro\Questions\Contracts\AnswerProcessor;

/** Stored verbatim — free text for simulation mode, a JSON string for interactive mode. */
final class DsaAnswerProcessor implements AnswerProcessor
{
    public function process(array $question, mixed $rawAnswer): string
    {
        return trim((string) $rawAnswer);
    }
}
