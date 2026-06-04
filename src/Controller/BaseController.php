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
