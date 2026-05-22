<?php

declare(strict_types=1);

namespace FlashMind\Core;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            app_env('DB_HOST', 'db'),
            app_env('DB_PORT', '5432'),
            app_env('DB_NAME', 'flashmind')
        );

        self::$connection = new PDO($dsn, (string) app_env('DB_USER', 'flashmind'), (string) app_env('DB_PASSWORD', 'flashmind'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$connection;
    }
}