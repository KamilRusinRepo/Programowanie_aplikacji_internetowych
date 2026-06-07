<?php

declare(strict_types=1);

namespace FlashMind\Model;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly ?string $roleName,
        public readonly bool $isEnabled,
        public readonly ?string $createdAt = null,
        public readonly ?string $passwordChangedAt = null,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) $row['password_hash'],
            $row['role_name'] ?? null,
            (bool) $row['is_enabled'],
            $row['created_at'] ?? null,
            $row['password_changed_at'] ?? null,
        );
    }
}
