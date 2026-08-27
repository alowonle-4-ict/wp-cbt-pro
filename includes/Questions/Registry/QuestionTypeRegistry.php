<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Registry;

use WPCBTPro\Questions\Contracts\QuestionType;

final class QuestionTypeRegistry
{
    /** @var array<string, QuestionType> */
    private array $types = [];

    public function register(QuestionType $type): void
    {
        if (isset($this->types[$type->id()])) {
            throw new \InvalidArgumentException(
                "Question type '{$type->id()}' is already registered."
            );
        }

        $this->types[$type->id()] = $type;
    }

    public function has(string $id): bool
    {
        return isset($this->types[$id]);
    }

    public function get(string $id): QuestionType
    {
        if (!isset($this->types[$id])) {
            throw new \OutOfBoundsException("Unknown question type '{$id}'.");
        }

        return $this->types[$id];
    }

    /** @return array<string, QuestionType> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return array<string, QuestionType[]> category value => types, for the admin "add question" picker */
    public function groupedByCategory(): array
    {
        $grouped = [];
        foreach ($this->types as $type) {
            $grouped[$type->category()->value][] = $type;
        }

        return $grouped;
    }
}
