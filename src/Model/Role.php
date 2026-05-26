<?php

declare(strict_types=1);

namespace FlashMind\Model;

final class Role
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
        );
    }
}