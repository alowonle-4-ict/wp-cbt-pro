<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Structures;

final class LinkedListStructure extends AbstractLinearStructure
{
    public function id(): string
    {
        return 'linked_list';
    }

    public function label(): string
    {
        return __('Linked List', 'wp-cbt-pro');
    }

    public function allowedOperations(): array
    {
        return ['INSERT_FRONT', 'INSERT_BACK', 'DELETE'];
    }

    protected function applyOperation(array $state, array $operation): array
    {
        switch ($operation['op']) {
            case 'INSERT_FRONT':
                return [$operation['arg'], ...$state];
            case 'INSERT_BACK':
                return [...$state, $operation['arg']];
            case 'DELETE':
                $index = array_search($operation['arg'], $state, true);
                if ($index !== false) {
                    unset($state[$index]);
                }
                return array_values($state);
            default:
                return $state;
        }
    }
}
