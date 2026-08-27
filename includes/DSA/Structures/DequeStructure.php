<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Structures;

final class DequeStructure extends AbstractLinearStructure
{
    public function id(): string
    {
        return 'deque';
    }

    public function label(): string
    {
        return __('Deque', 'wp-cbt-pro');
    }

    public function allowedOperations(): array
    {
        return ['PUSH_FRONT', 'PUSH_BACK', 'POP_FRONT', 'POP_BACK'];
    }

    protected function applyOperation(array $state, array $operation): array
    {
        return match ($operation['op']) {
            'PUSH_FRONT' => [$operation['arg'], ...$state],
            'PUSH_BACK' => [...$state, $operation['arg']],
            'POP_FRONT' => array_slice($state, 1),
            'POP_BACK' => array_slice($state, 0, -1),
            default => $state,
        };
    }
}
