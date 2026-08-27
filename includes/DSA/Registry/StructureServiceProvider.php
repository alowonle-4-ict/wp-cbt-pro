<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Registry;

use WPCBTPro\DSA\Structures\BstStructure;
use WPCBTPro\DSA\Structures\CircularQueueStructure;
use WPCBTPro\DSA\Structures\DequeStructure;
use WPCBTPro\DSA\Structures\LinkedListStructure;
use WPCBTPro\DSA\Structures\PriorityQueueStructure;
use WPCBTPro\DSA\Structures\QueueStructure;
use WPCBTPro\DSA\Structures\StackStructure;

/**
 * Seven of the architecture's 26 named structures ship as genuine, tested
 * implementations — Stack, Queue, Circular Queue, Deque, Priority Queue,
 * Linked List, and Binary Search Tree. That set deliberately covers both
 * interaction shapes the engine supports (a flat value list, and a tree
 * reduced to one) rather than spreading effort thin across all 26 with
 * shallow stubs. AVL Tree, Heap, Graph, Trie, and the rest register here
 * the same way — one new StructureDefinition class, no changes to
 * DsaType, the scoring strategy, or the admin builder.
 */
final class StructureServiceProvider
{
    public function __construct(private readonly StructureRegistry $registry)
    {
    }

    public function register(): void
    {
        $this->registry->register(new StackStructure());
        $this->registry->register(new QueueStructure());
        $this->registry->register(new CircularQueueStructure());
        $this->registry->register(new DequeStructure());
        $this->registry->register(new PriorityQueueStructure());
        $this->registry->register(new LinkedListStructure());
        $this->registry->register(new BstStructure());

        /** @param StructureRegistry $registry */
        do_action('wpcbtpro_register_dsa_structures', $this->registry);
    }
}
