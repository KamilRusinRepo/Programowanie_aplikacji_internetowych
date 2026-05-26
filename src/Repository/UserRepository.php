<?php

declare(strict_types=1);

namespace FlashMind\Repository;

use FlashMind\Core\Database;
use FlashMind\Model\User;
use PDO;

final class UserRepository
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Database::connection();
    }

    public function countAll(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function create(string $username, string $email, string $passwordHash): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash) RETURNING id'
        );
        $statement->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id) ON CONFLICT DO NOTHING'
        );
        $statement->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    public function findByLogin(string $login): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT u.id, u.username, u.email, u.password_hash, u.is_enabled, u.created_at, r.name AS role_name
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.email = :login OR u.username = :login
             ORDER BY ur.id ASC
             LIMIT 1'
        );
        $statement->execute(['login' => $login]);

        $row = $statement->fetch();

        return $row === false ? null : User::fromArray($row);
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT u.id, u.username, u.email, u.password_hash, u.is_enabled, u.created_at, r.name AS role_name
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);

        $row = $statement->fetch();

        return $row === false ? null : User::fromArray($row);
    }

    public function findByUsername(string $username): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT u.id, u.username, u.email, u.password_hash, u.is_enabled, u.created_at, r.name AS role_name
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.username = :username
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);

        $row = $statement->fetch();

        return $row === false ? null : User::fromArray($row);
    }

    public function findById(int $id): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT u.id, u.username, u.email, u.password_hash, u.is_enabled, u.created_at, r.name AS role_name
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : User::fromArray($row);
    }
}