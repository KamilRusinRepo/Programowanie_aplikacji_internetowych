<?php

declare(strict_types=1);

namespace FlashMind\Repository;

use FlashMind\Core\Database;
use PDO;

final class CardRepository
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Database::connection();
    }

    public function create(array $data): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO cards (deck_id, front_question, example_sentence, image_url, answer, translated_example)
             VALUES (:deck_id, :front_question, :example_sentence, :image_url, :answer, :translated_example)
             RETURNING id'
        );
        $statement->bindValue('deck_id', $data['deck_id'], PDO::PARAM_INT);
        $statement->bindValue('front_question', $data['front_question']);
        $statement->bindValue('example_sentence', $data['example_sentence']);
        $statement->bindValue('image_url', $data['image_url']);
        $statement->bindValue('answer', $data['answer']);
        $statement->bindValue('translated_example', $data['translated_example']);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function findByDeckId(int $deckId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, front_question, example_sentence, image_url, answer, translated_example, created_at
             FROM cards
             WHERE deck_id = :deck_id
             ORDER BY created_at DESC'
        );
        $statement->execute(['deck_id' => $deckId]);

        return $statement->fetchAll();
    }

    public function findForStudy(int $deckId, int $userId, int $limit = 10): array
    {
        $statement = $this->connection->prepare(
            'SELECT c.id, c.front_question, c.example_sentence, c.image_url, c.answer, c.translated_example
             FROM cards c
             LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.user_id = :user_id
             WHERE c.deck_id = :deck_id
             ORDER BY
                CASE WHEN cp.next_review_at IS NULL OR cp.next_review_at <= NOW() THEN 0 ELSE 1 END,
                cp.next_review_at ASC NULLS FIRST,
                COALESCE(cp.mastery_level, 0) ASC,
                c.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('deck_id', $deckId, PDO::PARAM_INT);
        $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function delete(int $cardId, int $deckId): void
    {
        $statement = $this->connection->prepare('DELETE FROM cards WHERE id = :card_id AND deck_id = :deck_id');
        $statement->execute([
            'card_id' => $cardId,
            'deck_id' => $deckId,
        ]);
    }

    public function update(int $cardId, int $deckId, array $data): void
    {
        $statement = $this->connection->prepare(
            'UPDATE cards
             SET front_question = :front_question,
                 example_sentence = :example_sentence,
                 image_url = :image_url,
                 answer = :answer,
                 translated_example = :translated_example
             WHERE id = :card_id AND deck_id = :deck_id'
        );
        $statement->execute([
            'front_question' => $data['front_question'],
            'example_sentence' => $data['example_sentence'],
            'image_url' => $data['image_url'],
            'answer' => $data['answer'],
            'translated_example' => $data['translated_example'],
            'card_id' => $cardId,
            'deck_id' => $deckId,
        ]);
    }
}
