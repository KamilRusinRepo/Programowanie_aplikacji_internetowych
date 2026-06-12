<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use FlashMind\Controller\BaseController;
use PHPUnit\Framework\TestCase;

final class BaseControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testCsrfTokenIsGeneratedAndStoredInSession(): void
    {
        $controller = new TestableBaseController();
        $token = $controller->publicCsrfToken();

        self::assertSame(64, strlen($token));
        self::assertSame($token, $_SESSION['csrf_token']);
    }

    public function testExistingCsrfTokenIsReused(): void
    {
        $_SESSION['csrf_token'] = str_repeat('a', 64);

        $controller = new TestableBaseController();

        self::assertSame(str_repeat('a', 64), $controller->publicCsrfToken());
    }

    public function testValidatesCsrfTokenWithHashEquals(): void
    {
        $controller = new TestableBaseController();
        $token = $controller->publicCsrfToken();

        self::assertTrue($controller->publicIsValidCsrfToken($token));
        self::assertFalse($controller->publicIsValidCsrfToken('invalid-token'));
        self::assertFalse($controller->publicIsValidCsrfToken(null));
    }

    public function testDetectsGuestUser(): void
    {
        $controller = new TestableBaseController();

        self::assertTrue($controller->publicIsGuestUser(['is_guest' => true]));
        self::assertTrue($controller->publicIsGuestUser(['role' => 'GUEST']));
        self::assertFalse($controller->publicIsGuestUser(['role' => 'USER']));
        self::assertFalse($controller->publicIsGuestUser(null));
    }
}

final class TestableBaseController extends BaseController
{
    public function publicCsrfToken(): string
    {
        return $this->csrfToken();
    }

    public function publicIsValidCsrfToken(?string $token): bool
    {
        return $this->isValidCsrfToken($token);
    }

    public function publicIsGuestUser(?array $user): bool
    {
        return $this->isGuestUser($user);
    }
}
