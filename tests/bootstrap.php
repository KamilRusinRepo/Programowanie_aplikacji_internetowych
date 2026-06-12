<?php

declare(strict_types=1);

$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($composerAutoload)) {
    require $composerAutoload;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'FlashMind\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
