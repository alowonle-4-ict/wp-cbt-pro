<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Registry;

use WPCBTPro\Programming\Contracts\LanguageDefinition;

final class LanguageRegistry
{
    /** @var array<string, LanguageDefinition> */
    private array $languages = [];

    public function register(LanguageDefinition $language): void
    {
        if (isset($this->languages[$language->id()])) {
            throw new \InvalidArgumentException("Language '{$language->id()}' is already registered.");
        }

        $this->languages[$language->id()] = $language;
    }

    public function has(string $id): bool
    {
        return isset($this->languages[$id]);
    }

    public function get(string $id): LanguageDefinition
    {
        if (!isset($this->languages[$id])) {
            throw new \OutOfBoundsException("Unknown language '{$id}'.");
        }

        return $this->languages[$id];
    }

    /** @return array<string, LanguageDefinition> */
    public function all(): array
    {
        return $this->languages;
    }
}
