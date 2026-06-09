<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use FlashMind\Service\AuthService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ServiceContractTest extends TestCase
{
    public function testAuthServiceExposesAuthenticationUseCases(): void
    {
        $reflection = new ReflectionClass(AuthService::class);

        self::assertTrue($reflection->isFinal());

        foreach (['register', 'login', 'usernameExists', 'emailExists'] as $method) {
            self::assertTrue($reflection->hasMethod($method));
            self::assertTrue($reflection->getMethod($method)->isPublic());
        }
    }
}
