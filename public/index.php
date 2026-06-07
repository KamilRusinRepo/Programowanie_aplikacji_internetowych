<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use FlashMind\Controller\AuthController;
use FlashMind\Controller\DashboardController;
use FlashMind\Controller\DeckController;
use FlashMind\Controller\ExploreController;
use FlashMind\Controller\HomeController;
use FlashMind\Controller\SettingsController;
use FlashMind\Controller\StatisticsController;
use FlashMind\Controller\AdminController;
use FlashMind\Core\View;
use FlashMind\Http\Request;
use FlashMind\Http\Router;
use FlashMind\Repository\RoleRepository;
use FlashMind\Repository\CardRepository;
use FlashMind\Repository\DeckRepository;
use FlashMind\Repository\LearningRepository;
use FlashMind\Repository\UserRepository;
use FlashMind\Service\AuthService;

$request = Request::fromGlobals();

$userRepository = new UserRepository();
$deckRepository = new DeckRepository();
$cardRepository = new CardRepository();
$learningRepository = new LearningRepository();
$roleRepository = new RoleRepository();
$authService = new AuthService($userRepository, $roleRepository);

$homeController = new HomeController();
$authController = new AuthController($authService, $deckRepository, $cardRepository);
$dashboardController = new DashboardController($userRepository, $learningRepository, $deckRepository);
$settingsController = new SettingsController($userRepository);
$deckController = new DeckController($deckRepository, $cardRepository, $userRepository, $learningRepository);
$exploreController = new ExploreController($deckRepository, $cardRepository, $userRepository);
$statisticsController = new StatisticsController($userRepository, $deckRepository, $learningRepository);
$adminController = new AdminController($userRepository);

$router = new Router();
$router->get('/', [$homeController, 'index']);
$router->get('/login', [$authController, 'showLogin']);
$router->post('/login', [$authController, 'login']);
$router->get('/register', [$authController, 'showRegister']);
$router->post('/register', [$authController, 'register']);
$router->get('/register/validate', [$authController, 'validateRegister']);
$router->get('/guest', [$authController, 'guest']);
$router->get('/logout', [$authController, 'logout']);
$router->post('/logout', [$authController, 'logout']);
$router->get('/dashboard', [$dashboardController, 'index']);
$router->get('/settings', [$settingsController, 'index']);
$router->post('/settings/profile', [$settingsController, 'updateProfile']);
$router->post('/settings/password', [$settingsController, 'updatePassword']);
$router->get('/explore', [$exploreController, 'index']);
$router->get('/explore/decks/{id}', [$exploreController, 'show']);
$router->post('/explore/decks/{id}/follow', [$exploreController, 'follow']);
$router->post('/explore/decks/{id}/unfollow', [$exploreController, 'unfollow']);
$router->post('/explore/decks/{id}/review', [$exploreController, 'review']);
$router->post('/explore/decks/{id}/reviews/{reviewId}/delete', [$exploreController, 'deleteReview']);
$router->get('/statistics', [$statisticsController, 'index']);
$router->get('/statistics/decks', [$statisticsController, 'decks']);
$router->get('/statistics/decks/{id}', [$statisticsController, 'show']);
$router->get('/admin', [$adminController, 'index']);
$router->get('/admin/users/validate', [$adminController, 'validateUser']);
$router->post('/admin/users', [$adminController, 'createUser']);
$router->post('/admin/users/{id}/toggle', [$adminController, 'toggleUser']);
$router->post('/admin/users/{id}/role', [$adminController, 'changeRole']);
$router->post('/admin/users/{id}/edit', [$adminController, 'updateUser']);
$router->post('/admin/users/{id}/delete', [$adminController, 'deleteUser']);
$router->get('/decks', [$deckController, 'index']);
$router->get('/decks/create', [$deckController, 'create']);
$router->get('/decks/{id}/edit', [$deckController, 'edit']);
$router->get('/decks/{id}', [$deckController, 'show']);
$router->get('/decks/{id}/export', [$deckController, 'exportCsv']);
$router->get('/decks/{id}/study', [$deckController, 'study']);
$router->get('/decks/{id}/study/summary/{sessionId}', [$deckController, 'studySummary']);
$router->post('/decks/{id}/study/complete', [$deckController, 'completeStudy']);
$router->post('/decks', [$deckController, 'store']);
$router->post('/decks/{id}/edit', [$deckController, 'update']);
$router->post('/decks/{id}/import', [$deckController, 'importCsv']);
$router->post('/decks/{id}/cards', [$deckController, 'addCard']);
$router->post('/decks/{id}/cards/{cardId}', [$deckController, 'updateCard']);
$router->post('/decks/{id}/cards/{cardId}/delete', [$deckController, 'deleteCard']);
$router->post('/decks/{id}/delete', [$deckController, 'delete']);

$router->dispatch($request, function () {
    View::render('errors/404', ['title' => 'Page not found'], 'layout/app');
});
