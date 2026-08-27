<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Structures;

final class QueueStructure extends AbstractLinearStructure
{
    public function id(): string
    {
        return 'queue';
    }

    public function label(): string
    {
        return __('Queue', 'wp-cbt-pro');
    }

    public function allowedOperations(): array
    {
        return ['ENQUEUE', 'DEQUEUE'];
    }

    protected function applyOperation(array $state, array $operation): array
    {
        return match ($operation['op']) {
            'ENQUEUE' => [...$state, $operation['arg']],
            'DEQUEUE' => array_slice($state, 1),
            default => $state,
        };
    }
}
