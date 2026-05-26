<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Core\View;
use FlashMind\Http\Request;

abstract class BaseController
{
    protected function render(string $template, array $data = [], string $layout = 'layout/app'): void
    {
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
}