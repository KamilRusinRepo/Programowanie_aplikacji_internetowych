<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use FlashMind\Model\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testCreatesUserFromDatabaseRow(): void
    {
        $user = User::fromArray([
            'id' => '7',
            'username' => 'kamil',
            'email' => 'kamil@example.com',
            'password_hash' => '$2y$10$hash',
            'role_name' => 'ADMIN',
            'is_enabled' => true,
            'created_at' => '2026-06-09 10:00:00+02',
            'password_changed_at' => '2026-06-09 11:00:00+02',
        ]);

        self::assertSame(7, $user->id);
        self::assertSame('kamil', $user->username);
        self::assertSame('kamil@example.com', $user->email);
        self::assertSame('$2y$10$hash', $user->passwordHash);
        self::assertSame('ADMIN', $user->roleName);
        self::assertTrue($user->isEnabled);
        self::assertSame('2026-06-09 10:00:00+02', $user->createdAt);
        self::assertSame('2026-06-09 11:00:00+02', $user->passwordChangedAt);
    }

    public function testRoleCanBeMissing(): void
    {
        $user = User::fromArray([
            'id' => 3,
            'username' => 'guest',
            'email' => 'guest@example.com',
            'password_hash' => 'hash',
            'is_enabled' => false,
        ]);

        self::assertNull($user->roleName);
        self::assertFalse($user->isEnabled);
    }
}
