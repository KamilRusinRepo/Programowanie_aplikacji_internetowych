<?php

declare(strict_types=1);

namespace FlashMind\Repository;

use FlashMind\Core\Database;
use FlashMind\Model\Role;
use PDO;

final class RoleRepository
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Database::connection();
    }

    public function findByName(string $name): ?Role
    {
        $statement = $this->connection->prepare('SELECT id, name FROM roles WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $name]);

        $row = $statement->fetch();

        return $row === false ? null : Role::fromArray($row);
    }
}