<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

/**
 * Serializes a question of this type to a portable array — question bank
 * export/backup and printed-paper generation both consume this instead of
 * reading type-specific tables directly.
 */
interface ExportHandler
{
    /**
     * @param array<string, mixed> $question
     * @return array<string, mixed>
     */
    public function toArray(array $question): array;
}
