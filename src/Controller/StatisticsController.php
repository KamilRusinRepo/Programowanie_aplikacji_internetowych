<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Http\Request;
use FlashMind\Repository\DeckRepository;
use FlashMind\Repository\LearningRepository;
use FlashMind\Repository\UserRepository;

final class StatisticsController extends BaseController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DeckRepository $decks,
        private readonly LearningRepository $learning,
    ) {
    }

    public function index(Request $request): void
    {
        $this->requireAccount();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $profile = $this->profileData((int) $user['id'], $user);
        $decks = $this->learning->studyableDeckStatistics((int) $user['id']);
        $overview = $this->learning->statisticsOverview((int) $user['id']);
        $heatmap = $overview['activityHeatmap'];

        $this->render('statistics/index', [
            'title' => 'Statistics Dashboard',
            'displayName' => $profile['displayName'],
            'userInitials' => $profile['initials'],
            'nav' => $this->nav('stats'),
            'statsProfile' => [
                'name' => $profile['displayName'],
                'level' => $overview['level'],
                'streak' => $overview['streak'],
                'totalXp' => number_format((int) $overview['totalXp']),
                'globalRank' => number_format((int) $overview['globalRank']),
            ],
            'overview' => [
                'dailyMastered' => $overview['daily']['mastered'],
                'dailyStudyTime' => $overview['daily']['studyTime'],
                'dailyAccuracy' => $overview['daily']['accuracy'],
                'weeklyMastered' => $overview['weekly']['mastered'],
                'weeklyStudyTime' => $overview['weekly']['studyTime'],
                'weeklyAccuracy' => $overview['weekly']['accuracy'],
                'allMastered' => $overview['all']['mastered'],
                'allStudyTime' => $overview['all']['studyTime'],
                'allAccuracy' => $overview['all']['accuracy'],
            ],
            'performance' => $overview['weeklyPerformance'],
            'masteryDecks' => $this->prepareMasteryDecks($decks),
            'heatmap' => [
                'columns' => (int) ($heatmap['columns'] ?? 53),
            ],
            'heatmapMonths' => $heatmap['months'] ?? [],
            'heatmapDays' => $heatmap['days'] ?? [],
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=18"><link rel="stylesheet" href="/styles/statistics.css?v=11">',
                'extraJs' => '<script defer src="/scripts/statistics-overview.js?v=2"></script>',
            ],
        ], 'layout/dashboard');
    }

    public function decks(Request $request): void
    {
        $this->requireAccount();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $profile = $this->profileData((int) $user['id'], $user);
        $decks = $this->learning->studyableDeckStatistics((int) $user['id']);

        $this->render('statistics/decks', [
            'title' => 'Statistics',
            'displayName' => $profile['displayName'],
            'userInitials' => $profile['initials'],
            'nav' => $this->nav('stats'),
            'statistics' => [
                'empty' => $decks === [] ? 'No statistics yet. Create a deck and finish a study session.' : '',
                'emptyClass' => $decks === [] ? '' : 'is-hidden',
            ],
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=18"><link rel="stylesheet" href="/styles/statistics.css?v=11">',
            ],
            'decks' => $this->prepareDeckCards($decks),
        ], 'layout/dashboard');
    }

    public function show(Request $request, string $deckId): void
    {
        $this->requireAccount();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $deckId = (int) $deckId;
        $deck = $this->decks->findStudyableById($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/statistics');
        }

        $profile = $this->profileData((int) $user['id'], $user);
        $cards = $this->learning->cardsForDeckStatistics((int) $user['id'], $deckId);
        $sort = (string) $request->input('sort', 'weakest');
        $stars = (int) $request->input('stars', 0);
        $cards = $this->filterAndSortCards($cards, $stars, $sort);
        $buckets = $this->learning->deckMasteryBuckets((int) $user['id'], $deckId);

        $this->render('statistics/show', [
            'title' => 'Deck Statistics',
            'displayName' => $profile['displayName'],
            'userInitials' => $profile['initials'],
            'nav' => $this->nav('stats'),
            'deck' => [
                'id' => $deck['id'],
                'name' => $deck['name'] ?? 'Deck',
                'description' => $deck['description'] ?? '',
                'cardCount' => count($cards),
                'cardsEmpty' => $cards === [] ? 'No cards in this deck yet.' : '',
                'cardsEmptyClass' => $cards === [] ? '' : 'is-hidden',
            ],
            'filters' => [
                'sortOptions' => $this->sortOptions($sort),
                'starOptions' => $this->starOptions($stars),
            ],
            'buckets' => $this->prepareBuckets($buckets),
            'cards' => $this->prepareCardRows($cards),
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=18"><link rel="stylesheet" href="/styles/statistics.css?v=11">',
            ],
        ], 'layout/dashboard');
    }

    private function prepareMasteryDecks(array $decks): array
    {
        usort($decks, static function (array $a, array $b): int {
            $left = strtotime((string) ($a['last_activity'] ?? '')) ?: 0;
            $right = strtotime((string) ($b['last_activity'] ?? '')) ?: 0;

            if ($left === $right) {
                $leftCreated = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
                $rightCreated = strtotime((string) ($b['created_at'] ?? '')) ?: 0;

                return $rightCreated <=> $leftCreated;
            }

            return $right <=> $left;
        });
        $decks = array_slice($decks, 0, 4);

        return array_map(static function (array $deck): array {
            $mastery = (int) ($deck['mastery'] ?? 0);
            return [
                'id' => (int) $deck['id'],
                'name' => (string) $deck['name'],
                'mastered' => (int) ($deck['mastered_count'] ?? 0),
                'mastery' => $mastery,
                'barClass' => $mastery >= 100 ? 'is-complete' : '',
            ];
        }, $decks);
    }

    private function prepareDeckCards(array $decks): array
    {
        return array_map(static function (array $deck): array {
            $name = (string) $deck['name'];
            return [
                'id' => (int) $deck['id'],
                'name' => $name,
                'initial' => strtoupper(substr($name, 0, 1)),
                'cardCount' => (int) $deck['card_count'],
                'correct' => (int) $deck['correct_count'],
                'wrong' => (int) $deck['wrong_count'],
                'mastery' => (int) $deck['mastery'],
            ];
        }, $decks);
    }

    private function prepareBuckets(array $buckets): array
    {
        $labels = [
            1 => ['Źle', 'is-bad', 1, 'bad.svg'],
            2 => ['Średnio', 'is-mid', 2, 'average.svg'],
            3 => ['Dobrze', 'is-good', 3, 'good.svg'],
            4 => ['Bardzo dobrze', 'is-great', 4, 'very_good.svg'],
        ];
        $items = [];

        foreach ($labels as $level => [$label, $class, $stars, $icon]) {
            $items[] = [
                'label' => $label,
                'class' => $class,
                'count' => (int) ($buckets[$level] ?? 0),
                'icon' => $icon,
                ...$this->starClasses($stars, $class),
            ];
        }

        return $items;
    }

    private function prepareCardRows(array $cards): array
    {
        return array_map(function (array $card): array {
            $level = max(1, min(4, (int) $card['mastery_level']));
            $status = $this->masteryLabel($level);
            $last = $this->relativeDate($card['last_answered_at'] ?? null);
            $next = $card['next_review_at'] === null ? 'Ready now' : date('Y-m-d', strtotime((string) $card['next_review_at']));

            return [
                'front' => (string) $card['front_question'],
                'answer' => (string) $card['answer'],
                'last' => $last,
                'next' => $next,
                'statusClass' => $status['class'],
                'statusLabel' => $status['label'],
                'statusIcon' => $status['icon'],
                ...$this->starClasses($level, $status['class']),
            ];
        }, $cards);
    }

    private function sortOptions(string $sort): array
    {
        $sortOptions = [
            'weakest' => 'Weakest first',
            'strongest' => 'Best known first',
        ];

        return array_map(static fn (string $value, string $label): array => [
            'value' => $value,
            'label' => $label,
            'selected' => $sort === $value ? 'selected' : '',
        ], array_keys($sortOptions), $sortOptions);
    }

    private function starOptions(int $stars): array
    {
        $starOptions = [
            0 => 'All stars',
            1 => '1 star',
            2 => '2 stars',
            3 => '3 stars',
            4 => '4 stars',
        ];

        return array_map(static fn (int $value, string $label): array => [
            'value' => $value,
            'label' => $label,
            'selected' => $stars === $value ? 'selected' : '',
        ], array_keys($starOptions), $starOptions);
    }

    private function filterAndSortCards(array $cards, int $stars, string $sort): array
    {
        if ($stars >= 1 && $stars <= 4) {
            $cards = array_values(array_filter($cards, static function (array $card) use ($stars): bool {
                $level = max(1, min(4, (int) $card['mastery_level']));

                return $level === $stars;
            }));
        }

        usort($cards, static function (array $a, array $b) use ($sort): int {
            $left = max(1, min(4, (int) $a['mastery_level']));
            $right = max(1, min(4, (int) $b['mastery_level']));

            if ($left === $right) {
                return strcmp((string) ($a['front_question'] ?? ''), (string) ($b['front_question'] ?? ''));
            }

            return $sort === 'strongest' ? $right <=> $left : $left <=> $right;
        });

        return $cards;
    }


    private function starClasses(int $level, string $class): array
    {
        $stars = [];
        for ($i = 1; $i <= 4; $i++) {
            $stars['star' . $i] = $i <= $level ? $class : 'is-empty';
        }

        return $stars;
    }

    private function masteryLabel(int $level): array
    {
        return match ($level) {
            1 => ['label' => 'Źle', 'class' => 'is-bad', 'icon' => 'bad.svg'],
            2 => ['label' => 'Średnio', 'class' => 'is-mid', 'icon' => 'average.svg'],
            3 => ['label' => 'Dobrze', 'class' => 'is-good', 'icon' => 'good.svg'],
            default => ['label' => 'Bardzo dobrze', 'class' => 'is-great', 'icon' => 'very_good.svg'],
        };
    }

    private function relativeDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return 'Never';
        }

        $reviewed = new \DateTimeImmutable((string) $date);
        $today = new \DateTimeImmutable('today');
        $days = (int) $today->diff($reviewed)->format('%r%a');
        $daysAgo = abs($days);

        if ($daysAgo === 0) {
            return 'Today';
        }

        if ($daysAgo === 1) {
            return 'Yesterday';
        }

        if ($daysAgo < 7) {
            return $daysAgo . ' days ago';
        }

        if ($daysAgo < 14) {
            return '1 week ago';
        }

        return date('Y-m-d', strtotime((string) $date));
    }

    private function profileData(int $userId, array $fallback): array
    {
        $user = $this->users->findById($userId);
        $username = $user?->username ?? ($fallback['username'] ?? 'Alex');
        $displayName = trim((string) preg_replace('/\s+/', ' ', $username));
        $displayName = $displayName === '' ? 'Alex' : $displayName;

        return [
            'displayName' => $displayName,
            'initials' => strtoupper(substr($displayName, 0, 1)),
        ];
    }

    private function nav(string $active): array
    {
        return [
            'dashboard' => $active === 'dashboard' ? 'is-active' : '',
            'decks' => $active === 'decks' ? 'is-active' : '',
            'explore' => '',
            'stats' => $active === 'stats' ? 'is-active' : '',
            'admin' => $active === 'admin' ? 'is-active' : '',
            'settings' => $active === 'settings' ? 'is-active' : '',
        ];
    }
}
