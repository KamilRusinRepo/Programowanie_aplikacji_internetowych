<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use FlashMind\Repository\CardRepository;
use FlashMind\Repository\DeckRepository;
use FlashMind\Repository\LearningRepository;
use FlashMind\Repository\LoginAttemptRepository;
use FlashMind\Repository\RoleRepository;
use FlashMind\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RepositoryContractTest extends TestCase
{
    #[DataProvider('repositoryMethods')]
    public function testRepositoryExposesExpectedPublicMethods(string $repositoryClass, array $expectedMethods): void
    {
        $reflection = new ReflectionClass($repositoryClass);

        self::assertTrue($reflection->isFinal());

        foreach ($expectedMethods as $method) {
            self::assertTrue(
                $reflection->hasMethod($method),
                sprintf('%s should expose method %s().', $repositoryClass, $method)
            );

            self::assertTrue($reflection->getMethod($method)->isPublic());
        }
    }

    public static function repositoryMethods(): array
    {
        return [
            'cards' => [CardRepository::class, ['create', 'findByDeckId', 'findForStudy', 'delete', 'update']],
            'decks' => [DeckRepository::class, ['create', 'findByUserId', 'findContinueLearning', 'publicDecks', 'followedDecks', 'findPublicById', 'followDeck', 'unfollowDeck', 'upsertReview', 'reviewsForDeck', 'deleteReview', 'findByIdForUser', 'findStudyableById', 'updateForUser', 'deleteForUser']],
            'learning' => [LearningRepository::class, ['recordStudySession', 'findStudySessionForUser', 'dashboardStats', 'deckStatistics', 'studyableDeckStatistics', 'statisticsOverview', 'cardsForDeckStatistics', 'deckMasteryBuckets']],
            'login attempts' => [LoginAttemptRepository::class, ['lockRemainingSeconds', 'record']],
            'roles' => [RoleRepository::class, ['findByName']],
            'users' => [UserRepository::class, ['countAll', 'create', 'assignRole', 'findByLogin', 'findByEmail', 'findByUsername', 'findById', 'usernameExistsForAnotherUser', 'emailExistsForAnotherUser', 'findForAdmin', 'setEnabled', 'updateAdminUser', 'deleteById', 'setRole']],
        ];
    }
}
