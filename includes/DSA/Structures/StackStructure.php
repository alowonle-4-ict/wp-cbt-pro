<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Structures;

final class StackStructure extends AbstractLinearStructure
{
    public function id(): string
    {
        return 'stack';
    }

    public function label(): string
    {
        return __('Stack', 'wp-cbt-pro');
    }

    public function allowedOperations(): array
    {
        return ['PUSH', 'POP'];
    }

    protected function applyOperation(array $state, array $operation): array
    {
        return match ($operation['op']) {
            'PUSH' => [...$state, $operation['arg']],
            'POP' => array_slice($state, 0, -1),
            default => $state,
        };
    }
}
