<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Structures;

/** A max-priority queue: EXTRACT_MAX always removes the numerically largest value (§17). */
final class PriorityQueueStructure extends AbstractLinearStructure
{
    public function id(): string
    {
        return 'priority_queue';
    }

    public function label(): string
    {
        return __('Priority Queue', 'wp-cbt-pro');
    }

    public function allowedOperations(): array
    {
        return ['INSERT', 'EXTRACT_MAX'];
    }

    protected function applyOperation(array $state, array $operation): array
    {
        if ($operation['op'] === 'INSERT') {
            return [...$state, $operation['arg']];
        }

        if ($operation['op'] === 'EXTRACT_MAX' && $state !== []) {
            $maxIndex = 0;
            foreach ($state as $index => $value) {
                if ((float) $value > (float) $state[$maxIndex]) {
                    $maxIndex = $index;
                }
            }
            unset($state[$maxIndex]);
            return array_values($state);
        }

        return $state;
    }
}
