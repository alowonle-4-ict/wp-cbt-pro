<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Registry;

use WPCBTPro\DSA\Contracts\StructureDefinition;

final class StructureRegistry
{
    /** @var array<string, StructureDefinition> */
    private array $structures = [];

    public function register(StructureDefinition $structure): void
    {
        if (isset($this->structures[$structure->id()])) {
            throw new \InvalidArgumentException("Structure '{$structure->id()}' is already registered.");
        }

        $this->structures[$structure->id()] = $structure;
    }

    public function has(string $id): bool
    {
        return isset($this->structures[$id]);
    }

    public function get(string $id): StructureDefinition
    {
        if (!isset($this->structures[$id])) {
            throw new \OutOfBoundsException("Unknown structure '{$id}'.");
        }

        return $this->structures[$id];
    }

    /** @return array<string, StructureDefinition> */
    public function all(): array
    {
        return $this->structures;
    }
}
