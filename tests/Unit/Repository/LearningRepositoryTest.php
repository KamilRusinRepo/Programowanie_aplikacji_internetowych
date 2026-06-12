<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use FlashMind\Repository\LearningRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class LearningRepositoryTest extends TestCase
{
    public function testCorrectAnswerOnNewCardStartsAtMediumMastery(): void
    {
        self::assertSame(
            ['mastery_level' => 2, 'wrong_streak' => 0, 'interval_days' => 3],
            $this->nextProgressState(true, 0, 0)
        );
    }

    public function testCorrectAnswerIncreasesMasteryAndClearsWrongStreak(): void
    {
        self::assertSame(
            ['mastery_level' => 4, 'wrong_streak' => 0, 'interval_days' => 14],
            $this->nextProgressState(true, 3, 1)
        );
    }

    public function testFirstWrongAnswerDoesNotDecreaseKnownCardMastery(): void
    {
        self::assertSame(
            ['mastery_level' => 4, 'wrong_streak' => 1, 'interval_days' => 1],
            $this->nextProgressState(false, 4, 0)
        );
    }

    public function testSecondWrongAnswerInARowDecreasesMastery(): void
    {
        self::assertSame(
            ['mastery_level' => 3, 'wrong_streak' => 0, 'interval_days' => 1],
            $this->nextProgressState(false, 4, 1)
        );
    }

    public function testWrongAnswerOnNewCardSetsLowestMasteryAndQuickReview(): void
    {
        self::assertSame(
            ['mastery_level' => 1, 'wrong_streak' => 1, 'interval_days' => 1],
            $this->nextProgressState(false, 0, 0)
        );
    }

    public function testMasteryIntervalsMatchSpacedRepetitionSchedule(): void
    {
        self::assertSame(1, $this->intervalDaysForMastery(1));
        self::assertSame(3, $this->intervalDaysForMastery(2));
        self::assertSame(7, $this->intervalDaysForMastery(3));
        self::assertSame(14, $this->intervalDaysForMastery(4));
    }

    private function nextProgressState(bool $wasCorrect, int $mastery, int $wrongStreak): array
    {
        return $this->callPrivate('nextProgressState', $wasCorrect, $mastery, $wrongStreak);
    }

    private function intervalDaysForMastery(int $mastery): int
    {
        return (int) $this->callPrivate('intervalDaysForMastery', $mastery);
    }

    private function callPrivate(string $methodName, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionClass(LearningRepository::class);
        $repository = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($repository, ...$arguments);
    }
}
