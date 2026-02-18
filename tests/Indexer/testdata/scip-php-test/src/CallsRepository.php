<?php

declare(strict_types=1);

namespace TestData;

class CallsRepository
{
    public function save(object $entity, bool $flush = false): void
    {
    }

    /** @return list<object> */
    public function findAll(): array
    {
        return [];
    }

    public static function create(string $name): self
    {
        return new self();
    }
}
