<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use FlashMind\Controller\AdminController;
use FlashMind\Controller\AuthController;
use FlashMind\Controller\BaseController;
use FlashMind\Controller\DashboardController;
use FlashMind\Controller\DeckController;
use FlashMind\Controller\ExploreController;
use FlashMind\Controller\HomeController;
use FlashMind\Controller\SettingsController;
use FlashMind\Controller\StatisticsController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ControllerContractTest extends TestCase
{
    #[DataProvider('controllerActions')]
    public function testControllerExposesExpectedActions(string $controllerClass, array $expectedActions): void
    {
        $reflection = new ReflectionClass($controllerClass);

        self::assertTrue($reflection->isSubclassOf(BaseController::class));

        foreach ($expectedActions as $action) {
            self::assertTrue(
                $reflection->hasMethod($action),
                sprintf('%s should expose action %s().', $controllerClass, $action)
            );

            self::assertTrue($reflection->getMethod($action)->isPublic());
        }
    }

    public static function controllerActions(): array
    {
        return [
            'auth' => [AuthController::class, ['showLogin', 'login', 'showRegister', 'register', 'validateRegister', 'guest', 'logout']],
            'admin' => [AdminController::class, ['index', 'createUser', 'toggleUser', 'updateUser', 'validateUser', 'deleteUser', 'changeRole']],
            'dashboard' => [DashboardController::class, ['index']],
            'deck' => [DeckController::class, ['create', 'edit', 'index', 'show', 'study', 'completeStudy', 'studySummary', 'exportCsv', 'importCsv', 'store', 'update', 'addCard', 'updateCard', 'delete', 'deleteCard']],
            'explore' => [ExploreController::class, ['index', 'show', 'follow', 'unfollow', 'review', 'deleteReview']],
            'home' => [HomeController::class, ['index']],
            'settings' => [SettingsController::class, ['index', 'updateProfile', 'updatePassword']],
            'statistics' => [StatisticsController::class, ['index', 'decks', 'show']],
        ];
    }
}
