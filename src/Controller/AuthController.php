<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Http\Request;
use FlashMind\Service\AuthService;

final class AuthController extends BaseController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function showLogin(Request $request): void
    {
        if ($this->currentUser() !== null) {
            $this->redirect('/dashboard');
        }

        $this->render('auth/login', [
            'title' => 'Login',
            'errors' => [],
            'old' => [],
        ]);
    }

    public function login(Request $request): void
    {
        $result = $this->authService->login($request->post);

        if (!$result['success']) {
            if ($request->expectsJson()) {
                $this->json([
                    'success' => false,
                    'errors' => $result['errors'],
                ], 422);
            }

            $this->render('auth/login', [
                'title' => 'Login',
                'errors' => $result['errors'],
                'old' => $request->post,
            ]);

            return;
        }

        $_SESSION['user'] = [
            'id' => $result['user']->id,
            'username' => $result['user']->username,
            'email' => $result['user']->email,
            'role' => $result['user']->roleName,
        ];

        if ($request->expectsJson()) {
            $this->json([
                'success' => true,
                'redirect' => '/dashboard',
                'user' => $_SESSION['user'],
            ]);
        }

        $this->redirect('/dashboard');
    }

    public function showRegister(Request $request): void
    {
        if ($this->currentUser() !== null) {
            $this->redirect('/dashboard');
        }

        $this->render('auth/register', [
            'title' => 'Register',
            'errors' => [],
            'old' => [],
        ]);
    }

    public function register(Request $request): void
    {
        $result = $this->authService->register($request->post);

        if (!$result['success']) {
            if ($request->expectsJson()) {
                $this->json([
                    'success' => false,
                    'errors' => $result['errors'],
                ], 422);
            }

            $this->render('auth/register', [
                'title' => 'Register',
                'errors' => $result['errors'],
                'old' => $request->post,
            ]);

            return;
        }

        $_SESSION['user'] = [
            'id' => $result['user']->id,
            'username' => $result['user']->username,
            'email' => $result['user']->email,
            'role' => $result['user']->roleName,
        ];

        if ($request->expectsJson()) {
            $this->json([
                'success' => true,
                'redirect' => '/dashboard',
                'user' => $_SESSION['user'],
            ]);
        }

        $this->redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        unset($_SESSION['user']);
        $this->redirect('/login');
    }
}