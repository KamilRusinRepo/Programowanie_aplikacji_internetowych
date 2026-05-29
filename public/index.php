<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use FlashMind\Controller\AuthController;
use FlashMind\Controller\DashboardController;
use FlashMind\Controller\DeckController;
use FlashMind\Controller\HomeController;
use FlashMind\Controller\SettingsController;
use FlashMind\Core\View;
use FlashMind\Http\Request;
use FlashMind\Http\Router;
use FlashMind\Repository\RoleRepository;
use FlashMind\Repository\CardRepository;
use FlashMind\Repository\DeckRepository;
use FlashMind\Repository\UserRepository;
use FlashMind\Service\AuthService;

$request = Request::fromGlobals();

$userRepository = new UserRepository();
$deckRepository = new DeckRepository();
$cardRepository = new CardRepository();
$roleRepository = new RoleRepository();
$authService = new AuthService($userRepository, $roleRepository);

$homeController = new HomeController();
$authController = new AuthController($authService);
$dashboardController = new DashboardController($userRepository);
$settingsController = new SettingsController($userRepository);
$deckController = new DeckController($deckRepository, $cardRepository, $userRepository);

$router = new Router();
$router->get('/', [$homeController, 'index']);
$router->get('/login', [$authController, 'showLogin']);
$router->post('/login', [$authController, 'login']);
$router->get('/register', [$authController, 'showRegister']);
$router->post('/register', [$authController, 'register']);
$router->get('/logout', [$authController, 'logout']);
$router->post('/logout', [$authController, 'logout']);
$router->get('/dashboard', [$dashboardController, 'index']);
$router->get('/settings', [$settingsController, 'index']);
$router->get('/decks', [$deckController, 'index']);
$router->get('/decks/create', [$deckController, 'create']);
$router->get('/decks/{id}', [$deckController, 'show']);
$router->post('/decks', [$deckController, 'store']);
$router->post('/decks/{id}/cards', [$deckController, 'addCard']);
$router->post('/decks/{id}/cards/{cardId}', [$deckController, 'updateCard']);
$router->post('/decks/{id}/cards/{cardId}/delete', [$deckController, 'deleteCard']);
$router->post('/decks/{id}/delete', [$deckController, 'delete']);

$router->dispatch($request, function () {
    View::render('errors/404', ['title' => 'Page not found'], 'layout/app');
});
