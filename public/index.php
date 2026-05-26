<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use FlashMind\Controller\AuthController;
use FlashMind\Controller\DashboardController;
use FlashMind\Controller\HomeController;
use FlashMind\Core\View;
use FlashMind\Http\Request;
use FlashMind\Http\Router;
use FlashMind\Repository\RoleRepository;
use FlashMind\Repository\UserRepository;
use FlashMind\Service\AuthService;

$request = Request::fromGlobals();

$userRepository = new UserRepository();
$roleRepository = new RoleRepository();
$authService = new AuthService($userRepository, $roleRepository);

$homeController = new HomeController();
$authController = new AuthController($authService);
$dashboardController = new DashboardController($userRepository);

$router = new Router();
$router->get('/', [$homeController, 'index']);
$router->get('/login', [$authController, 'showLogin']);
$router->post('/login', [$authController, 'login']);
$router->get('/register', [$authController, 'showRegister']);
$router->post('/register', [$authController, 'register']);
$router->post('/logout', [$authController, 'logout']);
$router->get('/dashboard', [$dashboardController, 'index']);

$router->dispatch($request, function () {
    View::render('errors/404', ['title' => 'Page not found'], 'layout/app');
});
