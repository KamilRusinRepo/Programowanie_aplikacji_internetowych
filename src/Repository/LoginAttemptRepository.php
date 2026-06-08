<?php

declare(strict_types=1);

namespace FlashMind\Repository;

use FlashMind\Core\Database;
use PDO;

final class LoginAttemptRepository
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Database::connection();
    }

    public function lockRemainingSeconds(string $login, string $ipAddress, int $limit, int $windowMinutes, int $lockSeconds): int
    {
        $statement = $this->connection->prepare(
            'SELECT login_lock_remaining_seconds(
                CAST(:login AS VARCHAR),
                CAST(:ip_address AS VARCHAR),
                CAST(:limit AS INT),
                CAST(:window_minutes AS INT),
                CAST(:lock_seconds AS INT)
            )'
        );
        $statement->execute([
            'login' => $this->normalizeLogin($login),
            'ip_address' => $this->normalizeIp($ipAddress),
            'limit' => $limit,
            'window_minutes' => $windowMinutes,
            'lock_seconds' => $lockSeconds,
        ]);

        return max(0, (int) $statement->fetchColumn());
    }

    public function record(string $login, string $ipAddress, string $userAgent, bool $wasSuccessful, ?string $failureReason = null): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO login_attempts (login_identifier, ip_address, user_agent, was_successful, failure_reason)
             VALUES (:login, :ip_address, :user_agent, :was_successful, :failure_reason)'
        );
        $statement->bindValue('login', $this->normalizeLogin($login));
        $statement->bindValue('ip_address', $this->normalizeIp($ipAddress));
        $statement->bindValue('user_agent', mb_substr($userAgent, 0, 512));
        $statement->bindValue('was_successful', $wasSuccessful, PDO::PARAM_BOOL);
        $statement->bindValue('failure_reason', $failureReason);
        $statement->execute();
    }

    private function normalizeLogin(string $login): string
    {
        return mb_strtolower(trim(mb_substr($login, 0, 255)));
    }

    private function normalizeIp(string $ipAddress): string
    {
        return mb_substr(trim($ipAddress), 0, 45);
    }
}
