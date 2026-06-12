<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use FlashMind\Service\AuthService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuthServiceTest extends TestCase
{
    public function testStrongPasswordRequiresLowercaseUppercaseNumberAndSpecialCharacter(): void
    {
        self::assertTrue($this->callPrivateBool('isStrongPassword', 'FlashMind1!'));
        self::assertFalse($this->callPrivateBool('isStrongPassword', 'flashmind1!'));
        self::assertFalse($this->callPrivateBool('isStrongPassword', 'FLASHMIND1!'));
        self::assertFalse($this->callPrivateBool('isStrongPassword', 'FlashMind!'));
        self::assertFalse($this->callPrivateBool('isStrongPassword', 'FlashMind1'));
    }

    public function testLockTimeIsFormattedForUiMessages(): void
    {
        self::assertSame('30 seconds', $this->callPrivateString('formatLockTime', 30));
        self::assertSame('1 minute', $this->callPrivateString('formatLockTime', 60));
        self::assertSame('2 minutes', $this->callPrivateString('formatLockTime', 61));
    }

    public function testStrongPasswordAcceptsDifferentSpecialCharacters(): void
    {
        self::assertTrue($this->callPrivateBool('isStrongPassword', 'FlashMind1#'));
        self::assertTrue($this->callPrivateBool('isStrongPassword', 'FlashMind1?'));
    }

    private function callPrivateBool(string $methodName, mixed ...$arguments): bool
    {
        return (bool) $this->callPrivate($methodName, ...$arguments);
    }

    private function callPrivateString(string $methodName, mixed ...$arguments): string
    {
        return (string) $this->callPrivate($methodName, ...$arguments);
    }

    private function callPrivate(string $methodName, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionClass(AuthService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($service, ...$arguments);
    }
}
