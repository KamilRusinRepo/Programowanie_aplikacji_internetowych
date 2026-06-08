<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Repository\DeckRepository;
use FlashMind\Repository\LearningRepository;
use FlashMind\Repository\UserRepository;
use FlashMind\Http\Request;

final class DashboardController extends BaseController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly LearningRepository $learning,
        private readonly DeckRepository $decks,
    ) {
    }

    public function index(Request $request): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        $userModel = $user !== null ? $this->users->findById((int) $user['id']) : null;
        $username = $userModel?->username ?? ($user['username'] ?? 'Alex');
        $displayName = trim((string) preg_replace('/\s+/', ' ', $username));
        $displayName = $displayName === '' ? 'Alex' : explode(' ', $displayName)[0];
        $initials = strtoupper(substr($displayName, 0, 1));

        $isGuest = $this->isGuestUser($user);
        $learningStats = $this->learning->dashboardStats((int) $user['id']);
        $continueDecks = $isGuest ? $this->guestContinueDecks() : $this->decks->findContinueLearning((int) $user['id']);
        $myDecks = $isGuest ? $this->guestDecks() : $this->learning->deckStatistics((int) $user['id']);
        $discoverDecks = $this->decks->publicDecks((int) $user['id'], '', '', '', 'followers', '', '', !$isGuest, $isGuest ? 0 : 3);
        if ($isGuest) {
            $followed = is_array($_SESSION['guest_followed_decks'] ?? null) ? array_map('intval', $_SESSION['guest_followed_decks']) : [];
            $discoverDecks = array_values(array_filter(
                $discoverDecks,
                static fn (array $deck): bool => !in_array((int) ($deck['id'] ?? 0), $followed, true)
            ));
            $discoverDecks = array_slice($discoverDecks, 0, 3);
        }

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'displayName' => $displayName,
            'userInitials' => $initials,
            'nav' => [
                'dashboard' => 'is-active',
                'decks' => '',
                'explore' => '',
                'stats' => '',
                'admin' => '',
                'settings' => '',
            ],
            'user' => $userModel === null ? [] : [
                'displayName' => $displayName,
                'username' => $userModel->username,
                'email' => $userModel->email,
                'roleName' => $userModel->roleName ?? 'USER',
            ],
            'stats' => $learningStats,
            'dailyGoal' => $this->dailyGoalData($learningStats),
            'continueLearning' => [
                'empty' => $continueDecks === [] ? 'No cards due yet. Start any deck to build your review queue.' : '',
                'emptyClass' => $continueDecks === [] ? '' : 'is-hidden',
            ],
            'continueDecks' => $this->prepareContinueDecks($continueDecks),
            'dashboardDecks' => [
                'empty' => $myDecks === [] ? 'No decks yet. Create your first deck.' : '',
                'emptyClass' => $myDecks === [] ? '' : 'is-hidden',
            ],
            'myDecks' => $this->prepareDashboardDecks($myDecks),
            'discoverDecks' => [
                'empty' => $discoverDecks === [] ? 'No new decks to discover right now. You already follow everything available.' : '',
                'emptyClass' => $discoverDecks === [] ? '' : 'is-hidden',
            ],
            'exploreDecks' => $this->prepareExploreDecks($discoverDecks),
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=19"><link rel="stylesheet" href="/styles/explore.css?v=15">',
            ],
        ], 'layout/dashboard');
    }

    private function dailyGoalData(array $stats): array
    {
        $todayAnswered = (int) ($stats['todayAnswered'] ?? 0);
        $dailyGoal = max(1, (int) ($stats['dailyGoal'] ?? 20));
        $isComplete = $todayAnswered >= $dailyGoal;
        $displayAnswered = min($todayAnswered, $dailyGoal);

        return [
            'iconClass' => $isComplete ? '' : 'is-hidden',
            'text' => $displayAnswered . '/' . $dailyGoal . ' cards',
        ];
    }

    private function prepareContinueDecks(array $decks): array
    {
        return array_map(static function (array $deck): array {
            $name = (string) $deck['name'];
            $category = (string) ($deck['category'] ?? '');
            $categoryKey = strtolower(preg_replace('/\s+/', '-', $category !== '' ? $category : 'general'));

            return [
                'id' => (int) $deck['id'],
                'name' => $name,
                'initial' => strtoupper(substr($name, 0, 1)),
                'categoryKey' => $categoryKey,
                'due' => (int) $deck['due_cards'],
                'mastery' => (int) $deck['mastery'],
            ];
        }, $decks);
    }

    private function guestContinueDecks(): array
    {
        return array_values(array_filter(array_map(static function (array $deck): array {
            $cards = is_array($deck['cards'] ?? null) ? $deck['cards'] : [];

            return [
                'id' => (int) ($deck['id'] ?? 0),
                'name' => (string) ($deck['name'] ?? 'Guest Deck'),
                'category' => (string) ($deck['category'] ?? 'General'),
                'due_cards' => count($cards),
                'mastery' => 0,
            ];
        }, $this->guestDecks()), static fn (array $deck): bool => (int) $deck['due_cards'] > 0));
    }

    private function guestDecks(): array
    {
        return array_map(static function (array $deck): array {
            $cards = is_array($deck['cards'] ?? null) ? $deck['cards'] : [];

            return array_merge($deck, [
                'card_count' => count($cards),
                'mastery' => 0,
                'average_rating' => 0,
            ]);
        }, is_array($_SESSION['guest_decks'] ?? null) ? $_SESSION['guest_decks'] : []);
    }

    private function prepareDashboardDecks(array $decks): array
    {
        return array_map(static function (array $deck): array {
            $name = (string) $deck['name'];
            $category = (string) ($deck['category'] ?? '');
            $deckType = (string) ($deck['deck_type'] ?? 'general');
            $backgroundUrl = (string) ($deck['background_url'] ?? '');
            $categoryKey = strtolower(preg_replace('/\s+/', '-', $category !== '' ? $category : 'general'));

            return [
                'id' => (int) $deck['id'],
                'name' => $name,
                'initial' => strtoupper(substr($name, 0, 1)),
                'typeLabel' => $deckType === 'language' ? 'Language' : 'General',
                'categoryLabel' => $category !== '' ? $category : 'Uncategorized',
                'categoryKey' => $categoryKey,
                'backgroundClass' => $backgroundUrl !== '' ? 'has-background' : '',
                'backgroundStyle' => $backgroundUrl !== '' ? 'background-image: url(' . $backgroundUrl . ');' : '',
                'cardCount' => (int) $deck['card_count'],
                'mastery' => (int) $deck['mastery'],
            ];
        }, array_slice($decks, 0, 3));
    }

    private function prepareExploreDecks(array $decks): array
    {
        return array_map(static function (array $deck): array {
            $name = (string) ($deck['name'] ?? '');
            $category = (string) ($deck['category'] ?? '');
            $deckType = (string) ($deck['deck_type'] ?? 'general');
            $backgroundUrl = (string) ($deck['background_url'] ?? '');
            $categoryKey = strtolower(preg_replace('/\s+/', '-', $category !== '' ? $category : 'general'));

            return [
                'id' => (int) $deck['id'],
                'name' => $name,
                'description' => (string) ($deck['description'] ?? ''),
                'initial' => strtoupper(substr($name, 0, 1)),
                'typeLabel' => $deckType === 'language' ? 'Language' : 'General',
                'categoryLabel' => $category !== '' ? $category : 'Uncategorized',
                'categoryKey' => $categoryKey,
                'backgroundClass' => $backgroundUrl !== '' ? 'has-background' : '',
                'backgroundStyle' => $backgroundUrl !== '' ? 'background-image: url(' . $backgroundUrl . ');' : '',
                'cardCount' => (int) ($deck['card_count'] ?? 0),
                'learnerCount' => self::compactNumber((int) ($deck['learner_count'] ?? 0)),
                'rating' => number_format((float) ($deck['average_rating'] ?? 0), 1),
            ];
        }, $decks);
    }

    private static function compactNumber(int $value): string
    {
        if ($value >= 1000) {
            return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . 'k';
        }

        return (string) $value;
    }
}
