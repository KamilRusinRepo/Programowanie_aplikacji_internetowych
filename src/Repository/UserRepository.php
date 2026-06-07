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
        $this->ensureSchema();
    }

    public function countAll(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function create(string $username, string $email, string $passwordHash): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (username, email, password_hash, password_changed_at)
             VALUES (:username, :email, :password_hash, NOW())
             RETURNING id'
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
            'SELECT u.id, u.username, u.email, u.password_hash, u.is_enabled, u.created_at, u.password_changed_at, r.name AS role_name
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
            'SELECT u.id, u.username, u.email, u.password_hash, u.is_enabled, u.created_at, u.password_changed_at, r.name AS role_name
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
            'SELECT u.id, u.username, u.email, u.password_hash, u.is_enabled, u.created_at, u.password_changed_at, r.name AS role_name
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
            'SELECT u.id, u.username, u.email, u.password_hash, u.is_enabled, u.created_at, u.password_changed_at, r.name AS role_name
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

    public function usernameExistsForAnotherUser(string $username, int $excludedUserId = 0): bool
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(:username) AND id <> :excluded_id'
        );
        $statement->execute([
            'username' => $username,
            'excluded_id' => $excludedUserId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function emailExistsForAnotherUser(string $email, int $excludedUserId = 0): bool
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:email) AND id <> :excluded_id'
        );
        $statement->execute([
            'email' => $email,
            'excluded_id' => $excludedUserId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function findForAdmin(string $search = '', string $role = '', string $status = ''): array
    {
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(LOWER(u.username) LIKE :search OR LOWER(u.email) LIKE :search)';
            $params['search'] = '%' . strtolower($search) . '%';
        }

        if ($role !== '') {
            $where[] = 'r.name = :role';
            $params['role'] = strtoupper($role);
        }

        if ($status === 'enabled' || $status === 'disabled') {
            $where[] = 'u.is_enabled = :enabled';
            $params['enabled'] = $status === 'enabled' ? 'true' : 'false';
        }

        $sql = 'SELECT u.id, u.username, u.email, u.is_enabled, u.created_at,
                       COALESCE(r.name, \'USER\') AS role_name,
                       (SELECT MAX(created_at) FROM study_sessions WHERE user_id = u.id) AS last_activity,
                       (SELECT COALESCE(SUM(xp_earned), 0) FROM user_daily_progress WHERE user_id = u.id) AS xp_total
                FROM users u
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY u.id, r.name ORDER BY u.created_at DESC';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function setEnabled(int $userId, bool $enabled): void
    {
        $statement = $this->connection->prepare('UPDATE users SET is_enabled = :enabled WHERE id = :id');
        $statement->execute([
            'enabled' => $enabled ? 'true' : 'false',
            'id' => $userId,
        ]);
    }

    public function updateAdminUser(int $userId, string $username, string $email, ?string $passwordHash): void
    {
        if ($passwordHash === null) {
            $statement = $this->connection->prepare(
                'UPDATE users SET username = :username, email = :email WHERE id = :id'
            );
            $statement->execute([
                'username' => $username,
                'email' => $email,
                'id' => $userId,
            ]);
            return;
        }

        $statement = $this->connection->prepare(
            'UPDATE users
             SET username = :username,
                 email = :email,
                 password_hash = :password_hash,
                 password_changed_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'id' => $userId,
        ]);
    }

    public function deleteById(int $userId): void
    {
        $statement = $this->connection->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);
    }

    public function setRole(int $userId, string $roleName): void
    {
        $role = $this->connection->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $role->execute(['name' => strtoupper($roleName)]);
        $roleId = (int) $role->fetchColumn();

        if ($roleId <= 0) {
            return;
        }

        $this->connection->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute(['user_id' => $userId]);
        $this->assignRole($userId, $roleId);
    }

    private function ensureSchema(): void
    {
        $this->connection->exec(
            'ALTER TABLE users
             ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP'
        );

        $this->connection->exec(
            'UPDATE users
             SET password_changed_at = COALESCE(password_changed_at, created_at, CURRENT_TIMESTAMP)
             WHERE password_changed_at IS NULL'
        );
    }
}
