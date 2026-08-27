<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Exams;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Exams\RandomizationService;

final class RandomizationServiceTest extends TestCase
{
    private RandomizationService $service;

    protected function setUp(): void
    {
        $this->service = new RandomizationService();
    }

    public function testGenerateSeedProducesUniqueThirtyTwoCharacterHex(): void
    {
        $a = $this->service->generateSeed();
        $b = $this->service->generateSeed();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $a);
        self::assertNotSame($a, $b);
    }

    public function testSeededShuffleIsDeterministicForSameSeed(): void
    {
        $items = range(1, 20);

        $first = $this->service->seededShuffle($items, 'fixed-seed');
        $second = $this->service->seededShuffle($items, 'fixed-seed');

        self::assertSame($first, $second);
    }

    public function testSeededShuffleReordersItems(): void
    {
        $items = range(1, 20);

        $shuffled = $this->service->seededShuffle($items, 'fixed-seed');

        self::assertNotSame($items, $shuffled);
        sort($shuffled);
        self::assertSame($items, $shuffled);
    }

    public function testSeededShuffleDiffersByDiscriminator(): void
    {
        $items = range(1, 20);

        $a = $this->service->seededShuffle($items, 'same-seed', 'question_order');
        $b = $this->service->seededShuffle($items, 'same-seed', 'options:42');

        self::assertNotSame($a, $b);
    }

    public function testSeededShuffleWorksWithAssociativeItemArrays(): void
    {
        $items = [
            ['id' => 1, 'label' => 'A'],
            ['id' => 2, 'label' => 'B'],
            ['id' => 3, 'label' => 'C'],
        ];

        $shuffled = $this->service->seededShuffle($items, 'seed');

        self::assertCount(3, $shuffled);
        self::assertSame([1, 2, 3], array_map(
            static fn (array $item): int => $item['id'],
            $this->sortById($shuffled)
        ));
    }

    public function testDrawFromPoolReturnsRequestedCount(): void
    {
        $pool = range(100, 109);

        $drawn = $this->service->drawFromPool($pool, 3, 'seed', 'pool-a');

        self::assertCount(3, $drawn);
        foreach ($drawn as $id) {
            self::assertContains($id, $pool);
        }
    }

    public function testDrawFromPoolClampsCountToPoolSize(): void
    {
        $pool = [1, 2, 3];

        $drawn = $this->service->drawFromPool($pool, 10, 'seed', 'pool-a');

        self::assertCount(3, $drawn);
    }

    public function testDrawFromPoolIsDeterministic(): void
    {
        $pool = range(1, 50);

        $first = $this->service->drawFromPool($pool, 5, 'seed', 'pool-a');
        $second = $this->service->drawFromPool($pool, 5, 'seed', 'pool-a');

        self::assertSame($first, $second);
    }

    /** @param array<int, array{id: int, label: string}> $items @return array<int, array{id: int, label: string}> */
    private function sortById(array $items): array
    {
        usort($items, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
        return $items;
    }
}
