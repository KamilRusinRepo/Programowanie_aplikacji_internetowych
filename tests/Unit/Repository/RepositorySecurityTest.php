<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepositorySecurityTest extends TestCase
{
    #[DataProvider('repositoryFiles')]
    public function testRepositoryDoesNotReadRequestSuperglobalsDirectly(string $path): void
    {
        $source = $this->source($path);

        self::assertStringNotContainsString('$_GET', $source);
        self::assertStringNotContainsString('$_POST', $source);
        self::assertStringNotContainsString('$_REQUEST', $source);
        self::assertStringNotContainsString('$_COOKIE', $source);
    }

    #[DataProvider('repositoryFiles')]
    public function testRepositoryUsesPreparedStatementsForDatabaseOperations(string $path): void
    {
        $source = $this->source($path);

        self::assertStringContainsString('->prepare(', $source);
        self::assertStringNotContainsString('SELECT *', strtoupper($source));
    }

    public static function repositoryFiles(): array
    {
        return [
            'cards' => ['src/Repository/CardRepository.php'],
            'decks' => ['src/Repository/DeckRepository.php'],
            'learning' => ['src/Repository/LearningRepository.php'],
            'login attempts' => ['src/Repository/LoginAttemptRepository.php'],
            'roles' => ['src/Repository/RoleRepository.php'],
            'users' => ['src/Repository/UserRepository.php'],
        ];
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $path);

        self::assertIsString($source);

        return $source;
    }
}
