<?php

declare(strict_types=1);

namespace FlashMind\Repository;

use FlashMind\Core\Database;
use PDO;

final class DeckRepository
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Database::connection();
    }

    public function create(array $data): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO decks (user_id, name, description, deck_type, source_language, target_language, category, is_public)
             VALUES (:user_id, :name, :description, :deck_type, :source_language, :target_language, :category, :is_public)
             RETURNING id'
        );
        $statement->bindValue('user_id', $data['user_id'], PDO::PARAM_INT);
        $statement->bindValue('name', $data['name']);
        $statement->bindValue('description', $data['description']);
        $statement->bindValue('deck_type', $data['deck_type']);
        $statement->bindValue('source_language', $data['source_language']);
        $statement->bindValue('target_language', $data['target_language']);
        $statement->bindValue('category', $data['category']);
        $statement->bindValue('is_public', $data['is_public'] ? 'true' : 'false');
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function findByUserId(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, description, deck_type, source_language, target_language, category, is_public, created_at
             FROM decks
             WHERE user_id = :user_id
             ORDER BY created_at DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }
}
