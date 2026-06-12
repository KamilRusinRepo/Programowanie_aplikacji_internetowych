<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use FlashMind\Http\Request;
use FlashMind\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    protected function tearDown(): void
    {
        http_response_code(200);
    }

    public function testDispatchesExactRoute(): void
    {
        $router = new Router();
        $router->get('/login', static function (): void {
            echo 'login page';
        });

        ob_start();
        $router->dispatch(new Request('GET', '/login', [], []));
        $output = ob_get_clean();

        self::assertSame('login page', $output);
    }

    public function testDispatchesRouteWithParameter(): void
    {
        $router = new Router();
        $router->get('/decks/{id}', static function (Request $request, string $id): void {
            echo $request->path . ':' . $id;
        });

        ob_start();
        $router->dispatch(new Request('GET', '/decks/42', [], []));
        $output = ob_get_clean();

        self::assertSame('/decks/42:42', $output);
    }

    public function testUsesNotFoundHandlerWhenRouteIsMissing(): void
    {
        $router = new Router();

        ob_start();
        $router->dispatch(new Request('GET', '/missing', [], []), static function (): void {
            echo 'custom not found';
        });
        $output = ob_get_clean();

        self::assertSame(404, http_response_code());
        self::assertSame('custom not found', $output);
    }
}
