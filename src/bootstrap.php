<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);
$envPath = $rootPath . DIRECTORY_SEPARATOR . '.env';

if (is_file($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

if (!function_exists('app_env')) {
    function app_env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        return $value === false || $value === null || $value === '' ? $default : $value;
    }
}

spl_autoload_register(static function (string $class) use ($rootPath): void {
    $prefix = 'FlashMind\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $rootPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $secureCookie = filter_var(
        app_env('SESSION_COOKIE_SECURE', $isHttps ? 'true' : 'false'),
        FILTER_VALIDATE_BOOLEAN
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}
