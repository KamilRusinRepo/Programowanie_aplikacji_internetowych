<?php

declare(strict_types=1);

namespace FlashMind\Repository;

use FlashMind\Core\Database;
use PDO;

final class LearningRepository
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = Database::connection();
        $this->ensureSchema();
    }

    public function recordStudySession(int $userId, int $deckId, array $answers, int $durationSeconds = 0): array
    {
        $cleanAnswers = [];

        foreach ($answers as $answer) {
            $cardId = (int) ($answer['cardId'] ?? 0);
            if ($cardId <= 0) {
                continue;
            }

            $cleanAnswers[] = [
                'card_id' => $cardId,
                'was_correct' => (bool) ($answer['correct'] ?? false),
                'user_answer' => trim((string) ($answer['userAnswer'] ?? '')),
                'used_hint' => (bool) ($answer['usedHint'] ?? false),
            ];
        }

        $total = count($cleanAnswers);
        $correct = count(array_filter($cleanAnswers, static fn (array $answer): bool => $answer['was_correct']));
        $wrong = $total - $correct;
        $xpEarned = array_reduce(
            $cleanAnswers,
            static function (int $carry, array $answer): int {
                if (!$answer['was_correct']) {
                    return $carry;
                }

                return $carry + ($answer['used_hint'] ? 5 : 10);
            },
            0
        );

        $this->connection->beginTransaction();

        try {
            $session = $this->connection->prepare(
                'INSERT INTO study_sessions (user_id, deck_id, total_cards, correct_cards, wrong_cards, xp_earned, duration_seconds)
                 VALUES (:user_id, :deck_id, :total_cards, :correct_cards, :wrong_cards, :xp_earned, :duration_seconds)
                 RETURNING id'
            );
            $session->execute([
                'user_id' => $userId,
                'deck_id' => $deckId,
                'total_cards' => $total,
                'correct_cards' => $correct,
                'wrong_cards' => $wrong,
                'xp_earned' => $xpEarned,
                'duration_seconds' => max(0, $durationSeconds),
            ]);

            $sessionId = (int) $session->fetchColumn();

            foreach ($cleanAnswers as $answer) {
                $this->recordAnswer($userId, $sessionId, $answer);
            }

            $daily = $this->connection->prepare(
                'INSERT INTO user_daily_progress (user_id, progress_date, answered_count, correct_count, xp_earned)
                 VALUES (:user_id, CURRENT_DATE, :answered_count, :correct_count, :xp_earned)
                 ON CONFLICT (user_id, progress_date)
                 DO UPDATE SET
                    answered_count = user_daily_progress.answered_count + EXCLUDED.answered_count,
                    correct_count = user_daily_progress.correct_count + EXCLUDED.correct_count,
                    xp_earned = user_daily_progress.xp_earned + EXCLUDED.xp_earned'
            );
            $daily->execute([
                'user_id' => $userId,
                'answered_count' => $total,
                'correct_count' => $correct,
                'xp_earned' => $xpEarned,
            ]);

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return [
            'sessionId' => $sessionId,
            'total' => $total,
            'correct' => $correct,
            'wrong' => $wrong,
            'xpEarned' => $xpEarned,
            'dashboard' => $this->dashboardStats($userId),
        ];
    }

    public function findStudySessionForUser(int $userId, int $deckId, int $sessionId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT ss.id, ss.deck_id, ss.total_cards, ss.correct_cards, ss.wrong_cards, ss.xp_earned, ss.created_at,
                    d.name AS deck_name, d.description AS deck_description
             FROM study_sessions ss
             INNER JOIN decks d ON d.id = ss.deck_id
             WHERE ss.id = :session_id AND ss.deck_id = :deck_id AND ss.user_id = :user_id
             LIMIT 1'
        );
        $statement->execute([
            'session_id' => $sessionId,
            'deck_id' => $deckId,
            'user_id' => $userId,
        ]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function dashboardStats(int $userId): array
    {
        $xp = (int) $this->fetchValue('SELECT COALESCE(SUM(xp_earned), 0) FROM user_daily_progress WHERE user_id = :user_id', ['user_id' => $userId]);
        $todayAnswered = (int) $this->fetchValue('SELECT COALESCE(answered_count, 0) FROM user_daily_progress WHERE user_id = :user_id AND progress_date = CURRENT_DATE', ['user_id' => $userId]);
        $dueCards = (int) $this->fetchValue(
            'SELECT COUNT(*)
             FROM cards c
             INNER JOIN decks d ON d.id = c.deck_id
             LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.user_id = :user_id
             WHERE (
                    d.user_id = :user_id
                    OR (
                        d.is_public = true
                        AND EXISTS (
                        SELECT 1 FROM deck_follows followed
                        WHERE followed.deck_id = d.id AND followed.user_id = :user_id
                        )
                    )
               )
               AND (cp.next_review_at IS NULL OR cp.next_review_at <= NOW())',
            ['user_id' => $userId]
        );

        $level = $this->levelFromXp($xp);
        $dailyGoal = 20;

        return [
            'streak' => $this->streakDays($userId, $dailyGoal),
            'dueCards' => $todayAnswered . '/' . $dailyGoal . ' cards',
            'dueReviews' => $dueCards,
            'dailyGoal' => $dailyGoal,
            'todayAnswered' => $todayAnswered,
            'level' => $level['level'],
            'xp' => $level['currentXp'] . '/' . $level['nextXp'] . ' XP',
            'progress' => $level['progress'],
            'totalXp' => $xp,
        ];
    }

    public function deckStatistics(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT d.id, d.name, d.description, d.deck_type, d.source_language, d.target_language, d.category, d.background_url, d.created_at,
                    COUNT(c.id) AS card_count,
                    COALESCE(ROUND(AVG(COALESCE(cp.mastery_level, 0)) / 4 * 100), 0) AS mastery,
                    COUNT(cp.id) FILTER (WHERE COALESCE(cp.mastery_level, 0) >= 4) AS mastered_count,
                    COALESCE(SUM(COALESCE(cp.correct_count, 0)), 0) AS correct_count,
                    COALESCE(SUM(COALESCE(cp.wrong_count, 0)), 0) AS wrong_count,
                    (
                        SELECT MAX(ss.created_at)
                        FROM study_sessions ss
                        WHERE ss.deck_id = d.id AND ss.user_id = :user_id
                    ) AS last_activity,
                    false AS is_followed
             FROM decks d
             LEFT JOIN cards c ON c.deck_id = d.id
             LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.user_id = :user_id
             WHERE d.user_id = :user_id
             GROUP BY d.id
             ORDER BY d.created_at DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function studyableDeckStatistics(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT d.id, d.name, d.description, d.deck_type, d.source_language, d.target_language, d.category, d.background_url, d.created_at,
                    COUNT(c.id) AS card_count,
                    COALESCE(ROUND(AVG(COALESCE(cp.mastery_level, 0)) / 4 * 100), 0) AS mastery,
                    COUNT(cp.id) FILTER (WHERE COALESCE(cp.mastery_level, 0) >= 4) AS mastered_count,
                    COALESCE(SUM(COALESCE(cp.correct_count, 0)), 0) AS correct_count,
                    COALESCE(SUM(COALESCE(cp.wrong_count, 0)), 0) AS wrong_count,
                    (
                        SELECT MAX(ss.created_at)
                        FROM study_sessions ss
                        WHERE ss.deck_id = d.id AND ss.user_id = :user_id
                    ) AS last_activity,
                    (
                        d.user_id <> :user_id
                        AND EXISTS (
                            SELECT 1 FROM deck_follows mine
                            WHERE mine.deck_id = d.id AND mine.user_id = :user_id
                        )
                    ) AS is_followed
             FROM decks d
             LEFT JOIN cards c ON c.deck_id = d.id
             LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.user_id = :user_id
             WHERE d.user_id = :user_id
                OR (
                    d.is_public = true
                    AND EXISTS (
                    SELECT 1 FROM deck_follows followed
                    WHERE followed.deck_id = d.id AND followed.user_id = :user_id
                    )
                )
             GROUP BY d.id
             ORDER BY last_activity DESC NULLS LAST, d.created_at DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function statisticsOverview(int $userId): array
    {
        $daily = $this->periodProgress($userId, 'progress_date = CURRENT_DATE', 'cp.last_answered_at::date = CURRENT_DATE', 'created_at::date = CURRENT_DATE');
        $weekly = $this->periodProgress($userId, 'progress_date >= CURRENT_DATE - INTERVAL \'6 days\'', 'cp.last_answered_at >= CURRENT_DATE - INTERVAL \'6 days\'', 'created_at >= CURRENT_DATE - INTERVAL \'6 days\'');
        $allTime = $this->periodProgress($userId, 'TRUE', 'TRUE', 'TRUE');
        $dashboard = $this->dashboardStats($userId);

        return [
            'daily' => $this->formatOverviewPeriod($daily),
            'weekly' => $this->formatOverviewPeriod($weekly),
            'all' => $this->formatOverviewPeriod($allTime),
            'weeklyPerformance' => $this->weeklyPerformance($userId),
            'activityHeatmap' => $this->activityHeatmap($userId),
            'level' => $dashboard['level'],
            'totalXp' => $dashboard['totalXp'],
            'globalRank' => $this->globalRank($userId, (int) $dashboard['totalXp']),
            'streak' => $dashboard['streak'],
        ];
    }

    public function cardsForDeckStatistics(int $userId, int $deckId): array
    {
        $statement = $this->connection->prepare(
            'SELECT c.id, c.front_question, c.answer,
                    COALESCE(cp.mastery_level, 0) AS mastery_level,
                    COALESCE(cp.correct_count, 0) AS correct_count,
                    COALESCE(cp.wrong_count, 0) AS wrong_count,
                    cp.last_answered_at,
                    cp.next_review_at
             FROM cards c
             INNER JOIN decks d ON d.id = c.deck_id
             LEFT JOIN card_progress cp ON cp.card_id = c.id AND cp.user_id = :user_id
             WHERE c.deck_id = :deck_id
               AND (
                    d.user_id = :user_id
                    OR (
                        d.is_public = true
                        AND EXISTS (
                        SELECT 1 FROM deck_follows followed
                        WHERE followed.deck_id = d.id AND followed.user_id = :user_id
                        )
                    )
               )
             ORDER BY COALESCE(cp.mastery_level, 0) ASC, c.created_at DESC'
        );
        $statement->execute([
            'user_id' => $userId,
            'deck_id' => $deckId,
        ]);

        return $statement->fetchAll();
    }

    public function deckMasteryBuckets(int $userId, int $deckId): array
    {
        $buckets = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $cards = $this->cardsForDeckStatistics($userId, $deckId);

        foreach ($cards as $card) {
            $level = max(1, min(4, (int) $card['mastery_level']));
            $buckets[$level]++;
        }

        return $buckets;
    }

    private function recordAnswer(int $userId, int $sessionId, array $answer): void
    {
        $wasCorrect = $answer['was_correct'];
        $cardId = (int) $answer['card_id'];

        $sessionAnswer = $this->connection->prepare(
            'INSERT INTO study_session_answers (session_id, card_id, was_correct, user_answer)
             VALUES (:session_id, :card_id, :was_correct, :user_answer)'
        );
        $sessionAnswer->execute([
            'session_id' => $sessionId,
            'card_id' => $cardId,
            'was_correct' => $wasCorrect ? 'true' : 'false',
            'user_answer' => $answer['user_answer'],
        ]);

        $current = $this->connection->prepare(
            'SELECT mastery_level FROM card_progress WHERE user_id = :user_id AND card_id = :card_id LIMIT 1'
        );
        $current->execute([
            'user_id' => $userId,
            'card_id' => $cardId,
        ]);
        $currentLevel = $current->fetchColumn();
        $mastery = $currentLevel === false ? 0 : (int) $currentLevel;
        if ($wasCorrect) {
            $mastery = $mastery <= 0 ? 2 : min(4, $mastery + 1);
        } else {
            $mastery = max(1, $mastery - 1);
        }
        $intervalDays = match ($mastery) {
            1 => 1,
            2 => 3,
            3 => 7,
            default => 14,
        };

        $progress = $this->connection->prepare(
            'INSERT INTO card_progress (user_id, card_id, attempts, correct_count, wrong_count, mastery_level, last_answered_at, next_review_at)
             VALUES (:user_id, :card_id, 1, :correct_count, :wrong_count, :mastery_level, NOW(), NOW() + (:interval_days * INTERVAL \'1 day\'))
             ON CONFLICT (user_id, card_id)
             DO UPDATE SET
                attempts = card_progress.attempts + 1,
                correct_count = card_progress.correct_count + EXCLUDED.correct_count,
                wrong_count = card_progress.wrong_count + EXCLUDED.wrong_count,
                mastery_level = EXCLUDED.mastery_level,
                last_answered_at = NOW(),
                next_review_at = EXCLUDED.next_review_at'
        );
        $progress->execute([
            'user_id' => $userId,
            'card_id' => $cardId,
            'correct_count' => $wasCorrect ? 1 : 0,
            'wrong_count' => $wasCorrect ? 0 : 1,
            'mastery_level' => $mastery,
            'interval_days' => $intervalDays,
        ]);
    }

    private function streakDays(int $userId, int $dailyGoal): int
    {
        $statement = $this->connection->prepare(
            'SELECT progress_date, answered_count
             FROM user_daily_progress
             WHERE user_id = :user_id
             ORDER BY progress_date DESC
             LIMIT 90'
        );
        $statement->execute(['user_id' => $userId]);

        $rows = $statement->fetchAll();
        $byDate = [];
        foreach ($rows as $row) {
            $byDate[(string) $row['progress_date']] = (int) $row['answered_count'];
        }

        $date = new \DateTimeImmutable('today');
        if (($byDate[$date->format('Y-m-d')] ?? 0) < $dailyGoal) {
            $date = $date->modify('-1 day');
        }

        $streak = 0;
        while (($byDate[$date->format('Y-m-d')] ?? 0) >= $dailyGoal) {
            $streak++;
            $date = $date->modify('-1 day');
        }

        return $streak;
    }

    private function levelFromXp(int $totalXp): array
    {
        $level = 1;
        $previousThreshold = 0;
        $requirement = 100;
        $nextThreshold = $requirement;

        while ($totalXp >= $nextThreshold) {
            $level++;
            $previousThreshold = $nextThreshold;
            $requirement = (int) ceil($requirement * 1.33);
            $nextThreshold += $requirement;
        }

        $span = max(1, $nextThreshold - $previousThreshold);
        $current = max(0, $totalXp - $previousThreshold);

        return [
            'level' => $level,
            'currentXp' => $current,
            'nextXp' => $span,
            'progress' => min(100, (int) round(($current / $span) * 100)),
        ];
    }

    private function periodProgress(int $userId, string $condition, string $masteredCondition, string $sessionCondition): array
    {
        $statement = $this->connection->prepare(
            'SELECT
                COALESCE(SUM(answered_count), 0) AS answered,
                COALESCE(SUM(correct_count), 0) AS correct,
                COALESCE(SUM(xp_earned), 0) AS xp
             FROM user_daily_progress
             WHERE user_id = :user_id AND ' . $condition
        );
        $statement->execute(['user_id' => $userId]);

        $row = $statement->fetch();
        if ($row === false) {
            $row = ['answered' => 0, 'correct' => 0, 'xp' => 0];
        }

        $mastered = $this->connection->prepare(
            'SELECT COUNT(*)
             FROM card_progress cp
             INNER JOIN cards c ON c.id = cp.card_id
             INNER JOIN decks d ON d.id = c.deck_id
             WHERE cp.user_id = :user_id
               AND COALESCE(cp.mastery_level, 0) >= 4
               AND ' . $masteredCondition
        );
        $mastered->execute(['user_id' => $userId]);
        $row['mastered'] = (int) $mastered->fetchColumn();
        $duration = $this->connection->prepare(
            'SELECT COALESCE(SUM(duration_seconds), 0)
             FROM study_sessions
             WHERE user_id = :user_id AND ' . $sessionCondition
        );
        $duration->execute(['user_id' => $userId]);
        $row['study_seconds'] = (int) $duration->fetchColumn();

        return $row;
    }

    private function formatOverviewPeriod(array $row): array
    {
        $answered = (int) ($row['answered'] ?? 0);
        $correct = (int) ($row['correct'] ?? 0);
        $accuracy = $answered === 0 ? 0 : (int) round(($correct / $answered) * 100);
        $studySeconds = (int) ($row['study_seconds'] ?? 0);

        return [
            'mastered' => (int) ($row['mastered'] ?? 0),
            'studyTime' => $this->formatStudyTime($studySeconds),
            'accuracy' => $accuracy . '%',
            'xp' => (int) ($row['xp'] ?? 0),
        ];
    }

    private function globalRank(int $userId, int $totalXp): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*)
             FROM users u
             LEFT JOIN user_daily_progress udp ON udp.user_id = u.id
             WHERE u.id <> :user_id
             GROUP BY u.id
             HAVING COALESCE(SUM(udp.xp_earned), 0) > :total_xp'
        );
        $statement->execute([
            'user_id' => $userId,
            'total_xp' => $totalXp,
        ]);

        return count($statement->fetchAll()) + 1;
    }

    private function formatStudyTime(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0m';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = (int) round(($seconds % 3600) / 60);

        if ($hours <= 0) {
            return max(1, $minutes) . 'm';
        }

        return $hours . 'h ' . $minutes . 'm';
    }

    private function weeklyPerformance(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT progress_date, xp_earned
             FROM user_daily_progress
             WHERE user_id = :user_id
               AND progress_date >= CURRENT_DATE - INTERVAL \'6 days\'
             ORDER BY progress_date ASC'
        );
        $statement->execute(['user_id' => $userId]);

        $byDate = [];
        foreach ($statement->fetchAll() as $row) {
            $byDate[(string) $row['progress_date']] = (int) $row['xp_earned'];
        }

        $items = [];
        $maxXp = max(1, ...array_values($byDate ?: [0]));
        $today = new \DateTimeImmutable('today');

        for ($i = 6; $i >= 0; $i--) {
            $date = $today->modify('-' . $i . ' days');
            $key = $date->format('Y-m-d');
            $xp = (int) ($byDate[$key] ?? 0);
            $items[] = [
                'day' => strtoupper($date->format('D')),
                'xp' => $xp,
                'height' => max(8, (int) round(($xp / $maxXp) * 100)),
                'activeClass' => $i === 0 ? 'is-today' : '',
            ];
        }

        return $items;
    }

    private function activityHeatmap(int $userId): array
    {
        $today = new \DateTimeImmutable('today');
        $rangeStart = $today->modify('-364 days');
        $gridStart = $rangeStart->modify('monday this week');

        $statement = $this->connection->prepare(
            'SELECT progress_date, xp_earned
             FROM user_daily_progress
             WHERE user_id = :user_id
               AND progress_date >= :start_date
               AND progress_date <= CURRENT_DATE
             ORDER BY progress_date ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'start_date' => $gridStart->format('Y-m-d'),
        ]);

        $byDate = [];
        foreach ($statement->fetchAll() as $row) {
            $byDate[(string) $row['progress_date']] = (int) $row['xp_earned'];
        }

        $maxXp = max(1, ...array_values($byDate ?: [0]));
        $days = [];
        $months = [];
        $seenMonths = [];
        $cursor = $gridStart;
        $column = 1;
        $rangeStartKey = $rangeStart->format('Y-m-d');

        while ($cursor <= $today) {
            $key = $cursor->format('Y-m-d');
            $row = (int) $cursor->format('N');
            $xp = (int) ($byDate[$key] ?? 0);
            $inRange = $key >= $rangeStartKey;
            $monthKey = $cursor->format('Y-m');

            if ($inRange && !isset($seenMonths[$monthKey])) {
                $months[] = [
                    'label' => $cursor->format('M'),
                    'gridColumn' => $column,
                ];
                $seenMonths[$monthKey] = true;
            }

            $level = 'level-0';
            if ($inRange && $xp > 0) {
                $level = 'level-' . min(5, max(1, (int) ceil(($xp / $maxXp) * 5)));
            }

            $days[] = [
                'level' => $level,
                'mutedClass' => $inRange ? '' : 'is-muted',
                'gridColumn' => $column,
                'gridRow' => $row,
                'tooltip' => ($inRange ? $xp : 0) . ' XP on ' . $cursor->format('M j, Y'),
            ];

            if ($row === 7) {
                $column++;
            }

            $cursor = $cursor->modify('+1 day');
        }

        return [
            'days' => $days,
            'months' => $months,
            'columns' => $column,
        ];
    }

    private function fetchValue(string $sql, array $parameters = []): mixed
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }

    private function ensureSchema(): void
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS study_sessions (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                deck_id INT NOT NULL REFERENCES decks(id) ON DELETE CASCADE,
                total_cards INT NOT NULL DEFAULT 0,
                correct_cards INT NOT NULL DEFAULT 0,
                wrong_cards INT NOT NULL DEFAULT 0,
                xp_earned INT NOT NULL DEFAULT 0,
                duration_seconds INT NOT NULL DEFAULT 0,
                created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS study_session_answers (
                id SERIAL PRIMARY KEY,
                session_id INT NOT NULL REFERENCES study_sessions(id) ON DELETE CASCADE,
                card_id INT NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
                was_correct BOOLEAN NOT NULL,
                user_answer TEXT,
                created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS card_progress (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                card_id INT NOT NULL REFERENCES cards(id) ON DELETE CASCADE,
                attempts INT NOT NULL DEFAULT 0,
                correct_count INT NOT NULL DEFAULT 0,
                wrong_count INT NOT NULL DEFAULT 0,
                mastery_level INT NOT NULL DEFAULT 0,
                last_answered_at TIMESTAMPTZ,
                next_review_at TIMESTAMPTZ,
                CONSTRAINT card_progress_user_card_unique UNIQUE (user_id, card_id)
            );

            CREATE TABLE IF NOT EXISTS user_daily_progress (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                progress_date DATE NOT NULL,
                answered_count INT NOT NULL DEFAULT 0,
                correct_count INT NOT NULL DEFAULT 0,
                xp_earned INT NOT NULL DEFAULT 0,
                CONSTRAINT user_daily_progress_user_date_unique UNIQUE (user_id, progress_date)
            );'
        );
        $this->connection->exec('ALTER TABLE study_sessions ADD COLUMN IF NOT EXISTS duration_seconds INT NOT NULL DEFAULT 0');
        $this->connection->exec('ALTER TABLE decks ADD COLUMN IF NOT EXISTS background_url TEXT');
    }
}
