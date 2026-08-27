<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Structures;

/**
 * Capacity doesn't fit the plain "thread a value list through
 * applyOperation()" shape every other linear structure uses, since it must
 * persist across operations without appearing in the canonical state
 * itself — so this overrides simulate() directly instead of using the
 * shared base loop. An optional leading "CAPACITY(n)" operation sets the
 * limit; it defaults to 5 (§17, §26).
 */
final class CircularQueueStructure extends AbstractLinearStructure
{
    private const DEFAULT_CAPACITY = 5;

    public function id(): string
    {
        return 'circular_queue';
    }

    public function label(): string
    {
        return __('Circular Queue', 'wp-cbt-pro');
    }

    public function allowedOperations(): array
    {
        return ['CAPACITY', 'ENQUEUE', 'DEQUEUE'];
    }

    public function simulate(array $operations): array
    {
        $capacity = self::DEFAULT_CAPACITY;
        $state = [];

        foreach ($operations as $operation) {
            switch ($operation['op']) {
                case 'CAPACITY':
                    $capacity = max(1, (int) $operation['arg']);
                    break;
                case 'ENQUEUE':
                    if (count($state) < $capacity) {
                        $state[] = $operation['arg'];
                    }
                    // A full circular queue rejects the enqueue (classic
                    // textbook behavior) rather than overwriting — it does
                    // not silently grow or wrap over existing values.
                    break;
                case 'DEQUEUE':
                    $state = array_slice($state, 1);
                    break;
            }
        }

        return $state;
    }

    protected function applyOperation(array $state, array $operation): array
    {
        return $state; // unused — simulate() is overridden above
    }
}
