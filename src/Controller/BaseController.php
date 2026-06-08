<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Core\View;
use FlashMind\Http\Request;

abstract class BaseController
{
    protected function render(string $template, array $data = [], string $layout = 'layout/app'): void
    {
        $data['adminNav'] = $this->adminNavData();

        View::render($template, $data, $layout);
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    protected function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    protected function requireAuth(): void
    {
        if ($this->currentUser() === null) {
            $this->redirect('/login');
        }
    }

    protected function requireAccount(): void
    {
        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if (($user['is_guest'] ?? false) === true || ($user['role'] ?? '') === 'GUEST') {
            $username = (string) ($user['username'] ?? 'Guest');

            $this->render('errors/account_required', [
                'title' => 'Account required',
                'displayName' => $username,
                'userInitials' => strtoupper(substr($username, 0, 1)),
                'nav' => [
                    'dashboard' => '',
                    'decks' => '',
                    'explore' => '',
                    'stats' => '',
                    'settings' => '',
                ],
                'raw' => [
                    'extraCss' => '<link rel="stylesheet" href="/styles/settings.css?v=3">',
                    'extraJs' => '',
                ],
            ], 'layout/dashboard');
            exit;
        }
    }

    protected function isGuestUser(?array $user = null): bool
    {
        $user ??= $this->currentUser();

        return $user !== null && (($user['is_guest'] ?? false) === true || ($user['role'] ?? '') === 'GUEST');
    }

    protected function withOldInput(Request $request): array
    {
        return [
            'old' => $request->post,
        ];
    }

    private function adminNavData(): array
    {
        $user = $this->currentUser();
        if (($user['role'] ?? 'USER') !== 'ADMIN') {
            return ['class' => 'is-hidden'];
        }

        $active = '';
        if (isset($_SERVER['REQUEST_URI'])) {
            $path = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
            $active = str_starts_with($path, '/admin') ? ' is-active' : '';
        }

        return ['class' => trim($active)];
    }
}
