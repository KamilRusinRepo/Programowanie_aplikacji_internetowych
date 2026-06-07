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
        $this->ensureSchema();
    }

    public function create(array $data): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO decks (user_id, name, description, deck_type, source_language, target_language, category, background_url, is_public)
             VALUES (:user_id, :name, :description, :deck_type, :source_language, :target_language, :category, :background_url, :is_public)
             RETURNING id'
        );
        $statement->bindValue('user_id', $data['user_id'], PDO::PARAM_INT);
        $statement->bindValue('name', $data['name']);
        $statement->bindValue('description', $data['description']);
        $statement->bindValue('deck_type', $data['deck_type']);
        $statement->bindValue('source_language', $data['source_language']);
        $statement->bindValue('target_language', $data['target_language']);
        $statement->bindValue('category', $data['category']);
        $statement->bindValue('background_url', $data['background_url']);
        $statement->bindValue('is_public', $data['is_public'] ? 'true' : 'false');
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function findByUserId(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, description, deck_type, source_language, target_language, category, background_url, is_public, created_at
             FROM decks
             WHERE user_id = :user_id
             ORDER BY created_at DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function findContinueLearning(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT d.id, d.name, d.category,
                    COUNT(c.id) FILTER (WHERE cp.next_review_at IS NULL OR cp.next_review_at <= NOW()) AS due_cards,
                    COALESCE(ROUND(AVG(COALESCE(cp.mastery_level, 0)) / 4 * 100), 0) AS mastery
             FROM decks d
             INNER JOIN cards c ON c.deck_id = d.id
             LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.user_id = :user_id
             WHERE d.user_id = :user_id
             GROUP BY d.id
             ORDER BY due_cards DESC, d.created_at DESC
             LIMIT 2'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function publicDecks(int $userId, string $search = '', string $category = '', string $sourceLanguage = '', string $sort = 'followers', string $deckType = '', string $targetLanguage = '', bool $excludeOwnAndFollowed = false, int $limit = 0): array
    {
        $conditions = ['d.is_public = true'];
        $parameters = ['user_id' => $userId];

        if ($search !== '') {
            $conditions[] = '(LOWER(d.name) LIKE :search OR LOWER(COALESCE(d.description, \'\')) LIKE :search)';
            $parameters['search'] = '%' . strtolower($search) . '%';
        }

        if ($category !== '') {
            $conditions[] = 'd.category = :category';
            $parameters['category'] = $category;
        }

        if ($sourceLanguage !== '') {
            $conditions[] = 'd.source_language = :source_language';
            $parameters['source_language'] = $sourceLanguage;
        }

        if ($deckType !== '') {
            $conditions[] = 'd.deck_type = :deck_type';
            $parameters['deck_type'] = $deckType;
        }

        if ($deckType === 'language' && $targetLanguage !== '') {
            $conditions[] = 'd.target_language = :target_language';
            $parameters['target_language'] = $targetLanguage;
        }

        if ($excludeOwnAndFollowed) {
            $conditions[] = 'd.user_id <> :user_id';
            $conditions[] = 'NOT EXISTS (
                SELECT 1 FROM deck_follows followed
                WHERE followed.deck_id = d.id AND followed.user_id = :user_id
            )';
        }

        $orderBy = match ($sort) {
            'cards' => 'card_count DESC, learner_count DESC, average_rating DESC, d.created_at DESC',
            'rating' => 'average_rating DESC, review_count DESC, learner_count DESC, d.created_at DESC',
            'reviews' => 'review_count DESC, average_rating DESC, learner_count DESC, d.created_at DESC',
            'newest' => 'd.created_at DESC',
            default => 'learner_count DESC, average_rating DESC, card_count DESC, d.created_at DESC',
        };

        $statement = $this->connection->prepare(
            'SELECT d.id, d.user_id, d.name, d.description, d.deck_type, d.source_language, d.target_language,
                    d.category, d.background_url, COUNT(DISTINCT c.id) AS card_count,
                    COUNT(DISTINCT df.id) AS learner_count,
                    COALESCE(ROUND(AVG(dr.rating), 1), 0) AS average_rating,
                    COUNT(DISTINCT dr.id) AS review_count,
                    EXISTS (
                        SELECT 1 FROM deck_follows mine
                        WHERE mine.deck_id = d.id AND mine.user_id = :user_id
                    ) AS is_following
             FROM decks d
             LEFT JOIN cards c ON c.deck_id = d.id
             LEFT JOIN deck_follows df ON df.deck_id = d.id
             LEFT JOIN deck_reviews dr ON dr.deck_id = d.id
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY d.id
             ORDER BY ' . $orderBy
            . ($limit > 0 ? ' LIMIT ' . $limit : '')
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function followedDecks(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT d.id, d.user_id, d.name, d.description, d.deck_type, d.source_language, d.target_language,
                    d.category, d.background_url, COUNT(DISTINCT c.id) AS card_count,
                    COUNT(DISTINCT df_all.id) AS learner_count,
                    COALESCE(ROUND(AVG(dr.rating), 1), 0) AS average_rating,
                    COUNT(DISTINCT dr.id) AS review_count,
                    MAX(df.created_at) AS followed_at,
                    true AS is_following
             FROM deck_follows df
             INNER JOIN decks d ON d.id = df.deck_id
             LEFT JOIN cards c ON c.deck_id = d.id
             LEFT JOIN deck_follows df_all ON df_all.deck_id = d.id
             LEFT JOIN deck_reviews dr ON dr.deck_id = d.id
             WHERE df.user_id = :user_id
             GROUP BY d.id
             ORDER BY followed_at DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function findPublicById(int $deckId, int $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT d.id, d.user_id, d.name, d.description, d.deck_type, d.source_language, d.target_language,
                    d.category, d.background_url, COUNT(DISTINCT c.id) AS card_count,
                    COUNT(DISTINCT df.id) AS learner_count,
                    COALESCE(ROUND(AVG(dr.rating), 1), 0) AS average_rating,
                    COUNT(DISTINCT dr.id) AS review_count,
                    EXISTS (
                        SELECT 1 FROM deck_follows mine
                        WHERE mine.deck_id = d.id AND mine.user_id = :user_id
                    ) AS is_following
             FROM decks d
             LEFT JOIN cards c ON c.deck_id = d.id
             LEFT JOIN deck_follows df ON df.deck_id = d.id
             LEFT JOIN deck_reviews dr ON dr.deck_id = d.id
             WHERE d.id = :deck_id AND d.is_public = true
             GROUP BY d.id
             LIMIT 1'
        );
        $statement->execute([
            'deck_id' => $deckId,
            'user_id' => $userId,
        ]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function followDeck(int $userId, int $deckId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO deck_follows (user_id, deck_id)
             VALUES (:user_id, :deck_id)
             ON CONFLICT (user_id, deck_id) DO NOTHING'
        );
        $statement->execute([
            'user_id' => $userId,
            'deck_id' => $deckId,
        ]);
    }

    public function unfollowDeck(int $userId, int $deckId): void
    {
        $statement = $this->connection->prepare(
            'DELETE FROM deck_follows
             WHERE user_id = :user_id AND deck_id = :deck_id'
        );
        $statement->execute([
            'user_id' => $userId,
            'deck_id' => $deckId,
        ]);
    }

    public function upsertReview(int $userId, int $deckId, int $rating, string $comment): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO deck_reviews (user_id, deck_id, rating, comment)
             VALUES (:user_id, :deck_id, :rating, :comment)
             ON CONFLICT (user_id, deck_id)
             DO UPDATE SET rating = EXCLUDED.rating,
                           comment = EXCLUDED.comment,
                           updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'user_id' => $userId,
            'deck_id' => $deckId,
            'rating' => $rating,
            'comment' => $comment === '' ? null : $comment,
        ]);
    }

    public function reviewsForDeck(int $deckId): array
    {
        $statement = $this->connection->prepare(
            'SELECT dr.id, dr.user_id, dr.rating, dr.comment, dr.updated_at, u.username
             FROM deck_reviews dr
             INNER JOIN users u ON u.id = dr.user_id
             WHERE dr.deck_id = :deck_id
             ORDER BY dr.updated_at DESC'
        );
        $statement->execute(['deck_id' => $deckId]);

        return $statement->fetchAll();
    }

    public function deleteReview(int $deckId, int $reviewId, int $userId, bool $isAdmin): void
    {
        $sql = 'DELETE FROM deck_reviews WHERE id = :review_id AND deck_id = :deck_id';
        $parameters = [
            'review_id' => $reviewId,
            'deck_id' => $deckId,
        ];

        if (!$isAdmin) {
            $sql .= ' AND user_id = :user_id';
            $parameters['user_id'] = $userId;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);
    }

    public function findByIdForUser(int $deckId, int $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, description, deck_type, source_language, target_language, category, background_url, is_public, created_at
             FROM decks
             WHERE id = :deck_id AND user_id = :user_id
             LIMIT 1'
        );
        $statement->execute([
            'deck_id' => $deckId,
            'user_id' => $userId,
        ]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function updateForUser(int $deckId, int $userId, array $data): void
    {
        $statement = $this->connection->prepare(
            'UPDATE decks
             SET name = :name,
                 description = :description,
                 deck_type = :deck_type,
                 source_language = :source_language,
                 target_language = :target_language,
                 category = :category,
                 background_url = :background_url,
                 is_public = :is_public
             WHERE id = :deck_id AND user_id = :user_id'
        );
        $statement->bindValue('deck_id', $deckId, PDO::PARAM_INT);
        $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue('name', $data['name']);
        $statement->bindValue('description', $data['description']);
        $statement->bindValue('deck_type', $data['deck_type']);
        $statement->bindValue('source_language', $data['source_language']);
        $statement->bindValue('target_language', $data['target_language']);
        $statement->bindValue('category', $data['category']);
        $statement->bindValue('background_url', $data['background_url']);
        $statement->bindValue('is_public', $data['is_public'] ? 'true' : 'false');
        $statement->execute();
    }

    public function deleteForUser(int $deckId, int $userId): void
    {
        $statement = $this->connection->prepare('DELETE FROM decks WHERE id = :deck_id AND user_id = :user_id');
        $statement->execute([
            'deck_id' => $deckId,
            'user_id' => $userId,
        ]);
    }

    private function ensureSchema(): void
    {
        $this->connection->exec(
            'ALTER TABLE decks ADD COLUMN IF NOT EXISTS background_url TEXT;

            CREATE TABLE IF NOT EXISTS deck_follows (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                deck_id INT NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
                created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT deck_follows_user_deck_unique UNIQUE (user_id, deck_id)
            );

            CREATE TABLE IF NOT EXISTS deck_reviews (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                deck_id INT NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
                rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
                comment TEXT,
                created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT deck_reviews_user_deck_unique UNIQUE (user_id, deck_id)
            );'
        );
    }
}
