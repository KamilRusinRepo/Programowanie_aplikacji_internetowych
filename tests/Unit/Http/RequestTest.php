<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use FlashMind\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public function testInputReadsPostBeforeQuery(): void
    {
        $request = new Request('POST', '/login', ['login' => 'query'], ['login' => 'post']);

        self::assertSame('post', $request->input('login'));
    }

    public function testInputReturnsDefaultWhenKeyIsMissing(): void
    {
        $request = new Request('GET', '/missing', [], []);

        self::assertSame('fallback', $request->input('unknown', 'fallback'));
    }

    public function testDetectsJsonRequestsFromAcceptHeader(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request('GET', '/api', [], []);

        self::assertTrue($request->expectsJson());
    }

    public function testDetectsJsonRequestsFromXmlHttpRequestHeader(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $request = new Request('POST', '/api', [], []);

        self::assertTrue($request->expectsJson());
    }
}
