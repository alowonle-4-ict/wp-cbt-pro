<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

/**
 * Every shuffle here is a deterministic function of (attempt seed, item
 * identity, discriminator) rather than a stateful PRNG — so the exact paper
 * a candidate saw can be reconstructed later from the seed alone (§8, §31),
 * without ever storing the resolved order itself.
 */
final class RandomizationService
{
    public function generateSeed(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, mixed> same items, deterministically reordered
     */
    public function seededShuffle(array $items, string $seed, string $discriminator = ''): array
    {
        $keyed = [];
        foreach ($items as $item) {
            $identity = is_array($item) ? (string) ($item['id'] ?? serialize($item)) : (string) $item;
            $keyed[] = [
                'key' => hash('crc32b', $seed . '|' . $discriminator . '|' . $identity),
                'value' => $item,
            ];
        }

        usort($keyed, static fn (array $a, array $b): int => $a['key'] <=> $b['key']);

        return array_column($keyed, 'value');
    }

    /**
     * @param int[] $poolItemIds
     * @return int[] a deterministic subset of size min($drawCount, count($poolItemIds))
     */
    public function drawFromPool(array $poolItemIds, int $drawCount, string $seed, string $poolKey): array
    {
        $shuffled = $this->seededShuffle($poolItemIds, $seed, 'pool:' . $poolKey);

        return array_slice($shuffled, 0, max(0, min($drawCount, count($shuffled))));
    }
}
