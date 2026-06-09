<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Http\Request;
use FlashMind\Repository\CardRepository;
use FlashMind\Repository\DeckRepository;
use FlashMind\Repository\UserRepository;

final class ExploreController extends BaseController
{
    public function __construct(
        private readonly DeckRepository $decks,
        private readonly CardRepository $cards,
        private readonly UserRepository $users,
    ) {
    }

    public function index(Request $request): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $search = trim((string) $request->input('q', ''));
        $category = trim((string) $request->input('category', ''));
        $sourceLanguage = trim((string) $request->input('source_language', ''));
        $deckType = $this->normalizeDeckType((string) $request->input('deck_type', ''));
        $targetLanguage = $deckType === 'language' ? trim((string) $request->input('target_language', '')) : '';
        $sort = $this->normalizeSort((string) $request->input('sort', 'followers'));
        $isGuest = $this->isGuestUser($user);
        $profile = $this->profileData((int) $user['id'], $user);
        $decks = $this->decks->publicDecks((int) $user['id'], $search, $category, $sourceLanguage, $sort, $deckType, $targetLanguage);
        $query = http_build_query(array_filter([
            'q' => $search,
            'category' => $category,
            'source_language' => $sourceLanguage,
            'deck_type' => $deckType,
            'target_language' => $targetLanguage,
            'sort' => $sort === 'followers' ? '' : $sort,
        ], static fn (string $value): bool => $value !== ''));
        $returnTo = '/explore' . ($query === '' ? '' : '?' . $query);

        $this->render('explore/index', [
            'title' => 'Explore Decks',
            'displayName' => $profile['displayName'],
            'userInitials' => $profile['initials'],
            'nav' => $this->nav('explore'),
            'filters' => [
                'q' => $search,
                'categories' => $this->categoryOptions($category),
                'deckTypes' => $this->deckTypeOptions($deckType),
                'sourceLanguages' => $this->languageOptions($sourceLanguage),
                'targetLanguages' => $this->languageOptions($targetLanguage),
                'targetClass' => $deckType === 'language' ? '' : 'is-hidden',
                'formClass' => $deckType === 'language' ? 'has-target-language' : '',
                'sortOptions' => $this->sortOptions($sort),
            ],
            'explore' => [
                'empty' => $decks === [] ? 'No public decks match your filters yet.' : '',
                'emptyClass' => $decks === [] ? '' : 'is-hidden',
                'returnTo' => $returnTo,
            ],
            'decks' => $this->prepareMarketplaceDecks($decks, (int) $user['id'], $isGuest),
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=26"><link rel="stylesheet" href="/styles/explore.css?v=19">',
                'extraJs' => '<script defer src="/scripts/explore-filters.js?v=2"></script>',
            ],
        ], 'layout/dashboard');
    }

    public function show(Request $request, string $deckId): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $deckId = (int) $deckId;
        $deck = $this->decks->findPublicById($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/explore');
        }

        $profile = $this->profileData((int) $user['id'], $user);
        $tab = (string) $request->input('tab', 'cards');
        $tab = $tab === 'reviews' ? 'reviews' : 'cards';
        $isOwner = (int) $deck['user_id'] === (int) $user['id'];
        $isAdmin = ($user['role'] ?? 'USER') === 'ADMIN';
        $isGuest = $this->isGuestUser($user);
        $cards = $this->cards->findByDeckId($deckId);
        $reviews = $this->decks->reviewsForDeck($deckId);

        $this->render('explore/show', [
            'title' => 'Explore Deck',
            'displayName' => $profile['displayName'],
            'userInitials' => $profile['initials'],
            'nav' => $this->nav('explore'),
            'deck' => $this->prepareDeckHeader($deck, (int) $user['id'], $isGuest),
            'tabs' => [
                'cardsActive' => $tab === 'cards' ? 'is-active' : '',
                'reviewsActive' => $tab === 'reviews' ? 'is-active' : '',
                'cardsHidden' => $tab === 'cards' ? '' : 'is-hidden',
                'reviewsHidden' => $tab === 'reviews' ? '' : 'is-hidden',
            ],
            'cardList' => [
                'empty' => $cards === [] ? 'This deck does not have cards yet.' : '',
                'emptyClass' => $cards === [] ? '' : 'is-hidden',
            ],
            'cards' => $this->prepareCards($cards),
            'reviewList' => [
                'empty' => $reviews === [] ? 'No reviews yet. Be the first to rate this deck.' : '',
                'emptyClass' => $reviews === [] ? '' : 'is-hidden',
            ],
            'reviews' => $this->prepareReviews($reviews, (int) $user['id'], $isAdmin),
            'reviewForm' => (!$isOwner && !$isGuest) ? [[
                'ratings' => $this->ratingOptions(),
            ]] : [],
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=26"><link rel="stylesheet" href="/styles/explore.css?v=19">',
            ],
        ], 'layout/dashboard');
    }

    public function follow(Request $request, string $deckId): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $deckId = (int) $deckId;
        $deck = $this->decks->findPublicById($deckId, (int) $user['id']);
        if ($deck !== null && (int) $deck['user_id'] !== (int) $user['id']) {
            if ($this->isGuestUser($user)) {
                $followed = is_array($_SESSION['guest_followed_decks'] ?? null) ? $_SESSION['guest_followed_decks'] : [];
                $followed[] = $deckId;
                $_SESSION['guest_followed_decks'] = array_values(array_unique(array_map('intval', $followed)));
            } else {
                $this->decks->followDeck((int) $user['id'], $deckId);
            }
        }

        $returnTo = (string) $request->input('return_to', '/explore');
        $this->redirect($returnTo === '' || !str_starts_with($returnTo, '/') ? '/explore' : $returnTo);
    }

    public function unfollow(Request $request, string $deckId): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $deckId = (int) $deckId;
        if ($this->isGuestUser($user)) {
            $followed = is_array($_SESSION['guest_followed_decks'] ?? null) ? array_map('intval', $_SESSION['guest_followed_decks']) : [];
            $_SESSION['guest_followed_decks'] = array_values(array_filter(
                $followed,
                static fn (int $followedDeckId): bool => $followedDeckId !== $deckId
            ));
        } else {
            $this->decks->unfollowDeck((int) $user['id'], $deckId);
        }

        $returnTo = (string) $request->input('return_to', '/explore');
        $this->redirect($returnTo === '' || !str_starts_with($returnTo, '/') ? '/explore' : $returnTo);
    }

    public function review(Request $request, string $deckId): void
    {
        $this->requireAccount();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $deckId = (int) $deckId;
        $deck = $this->decks->findPublicById($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/explore');
        }

        if ((int) $deck['user_id'] === (int) $user['id']) {
            $this->redirect('/explore/decks/' . $deckId . '?tab=reviews');
        }

        $rating = max(1, min(5, (int) $request->input('rating', 5)));
        $comment = trim((string) $request->input('comment', ''));
        $this->decks->upsertReview((int) $user['id'], $deckId, $rating, $comment);

        $this->redirect('/explore/decks/' . $deckId . '?tab=reviews');
    }

    public function deleteReview(Request $request, string $deckId, string $reviewId): void
    {
        $this->requireAccount();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $deckId = (int) $deckId;
        $deck = $this->decks->findPublicById($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/explore');
        }

        $this->decks->deleteReview($deckId, (int) $reviewId, (int) $user['id'], ($user['role'] ?? 'USER') === 'ADMIN');
        $this->redirect('/explore/decks/' . $deckId . '?tab=reviews');
    }

    private function prepareMarketplaceDecks(array $decks, int $userId, bool $isGuest = false): array
    {
        return array_map(fn (array $deck): array => $this->prepareDeckHeader($deck, $userId, $isGuest), $decks);
    }

    private function prepareDeckHeader(array $deck, int $userId, bool $isGuest = false): array
    {
        $name = (string) $deck['name'];
        $category = (string) ($deck['category'] ?? '');
        $deckType = (string) ($deck['deck_type'] ?? 'general');
        $sourceLang = (string) ($deck['source_language'] ?? '');
        $targetLang = (string) ($deck['target_language'] ?? '');
        $backgroundUrl = (string) ($deck['background_url'] ?? '');
        $categoryKey = strtolower(preg_replace('/\s+/', '-', $category !== '' ? $category : 'general'));
        $isOwner = (int) ($deck['user_id'] ?? 0) === $userId;
        $guestFollowed = is_array($_SESSION['guest_followed_decks'] ?? null) ? array_map('intval', $_SESSION['guest_followed_decks']) : [];
        $isFollowing = in_array((string) ($deck['is_following'] ?? ''), ['1', 't', 'true'], true)
            || ($deck['is_following'] ?? false) === true
            || ($isGuest && in_array((int) ($deck['id'] ?? 0), $guestFollowed, true));

        return [
            'id' => (int) $deck['id'],
            'name' => $name,
            'description' => (string) ($deck['description'] ?? ''),
            'author' => (string) ($deck['owner_username'] ?? 'Unknown author'),
            'initial' => strtoupper(substr($name, 0, 1)),
            'typeLabel' => $deckType === 'language' ? 'Language' : 'General',
            'categoryLabel' => $category !== '' ? $category : 'Uncategorized',
            'categoryKey' => $categoryKey,
            'backgroundClass' => $backgroundUrl !== '' ? 'has-background' : '',
            'backgroundStyle' => $backgroundUrl !== '' ? 'background-image: url(' . $backgroundUrl . ');' : '',
            'badge' => $deckType === 'language' && $targetLang !== '' ? $sourceLang . ' -> ' . $targetLang : ($sourceLang !== '' ? $sourceLang : 'General'),
            'cardCount' => (int) ($deck['card_count'] ?? 0),
            'learnerCount' => $this->compactNumber((int) ($deck['learner_count'] ?? 0)),
            'rating' => number_format((float) ($deck['average_rating'] ?? 0), 1),
            'reviewCount' => (int) ($deck['review_count'] ?? 0),
            'followAction' => $isFollowing ? '/explore/decks/' . (int) $deck['id'] . '/unfollow' : '/explore/decks/' . (int) $deck['id'] . '/follow',
            'followLabel' => $isOwner ? 'Your Deck' : ($isFollowing ? 'Unfollow Deck' : 'Follow Deck'),
            'followDisabled' => $isOwner ? 'disabled' : '',
            'followClass' => $isOwner ? 'is-following' : ($isFollowing ? 'is-unfollow' : ''),
            'followIconClass' => $isOwner ? 'is-hidden' : '',
            'studyActions' => (!$isGuest && ($isOwner || $isFollowing)) ? [[
                'url' => '/decks/' . (int) $deck['id'] . '/study',
            ]] : [],
        ];
    }

    private function prepareCards(array $cards): array
    {
        return array_map(static fn (array $card): array => [
            'front' => (string) $card['front_question'],
            'answer' => (string) $card['answer'],
            'example' => (string) ($card['example_sentence'] ?? ''),
            'translated' => (string) ($card['translated_example'] ?? ''),
        ], $cards);
    }

    private function prepareReviews(array $reviews, int $userId, bool $isAdmin): array
    {
        return array_map(function (array $review) use ($userId, $isAdmin): array {
            $rating = max(1, min(5, (int) $review['rating']));
            $isAuthor = (int) ($review['user_id'] ?? 0) === $userId;

            return array_merge([
                'id' => (int) ($review['id'] ?? 0),
                'username' => (string) ($review['username'] ?? 'User'),
                'comment' => (string) ($review['comment'] ?? ''),
                'date' => date('Y-m-d', strtotime((string) $review['updated_at'])),
                'rating' => $rating,
                'authorActions' => $isAuthor ? [[
                    'ratings' => $this->ratingOptions($rating),
                ]] : [],
                'deleteActions' => ($isAuthor || $isAdmin) ? [[
                    'visible' => '1',
                ]] : [],
            ], $this->reviewStars($rating));
        }, $reviews);
    }

    private function reviewStars(int $rating): array
    {
        $stars = [];
        for ($i = 1; $i <= 5; $i++) {
            $stars['star' . $i] = $i <= $rating ? 'is-filled' : '';
        }

        return $stars;
    }

    private function categoryOptions(string $selected): array
    {
        $options = ['', 'Academic', 'Language', 'History', 'Science', 'Personal', 'Tech'];

        return array_map(static fn (string $value): array => [
            'value' => $value,
            'label' => $value === '' ? 'All Categories' : $value,
            'selected' => $selected === $value ? 'selected' : '',
        ], $options);
    }

    private function languageOptions(string $selected): array
    {
        $options = ['', 'English', 'Polish', 'German', 'Spanish', 'French', 'Japanese'];

        return array_map(static fn (string $value): array => [
            'value' => $value,
            'label' => $value === '' ? 'Any Language' : $value,
            'selected' => $selected === $value ? 'selected' : '',
        ], $options);
    }

    private function deckTypeOptions(string $selected): array
    {
        $options = [
            '' => 'All Types',
            'general' => 'General',
            'language' => 'Language',
        ];

        return array_map(static fn (string $value, string $label): array => [
            'value' => $value,
            'label' => $label,
            'selected' => $selected === $value ? 'selected' : '',
        ], array_keys($options), $options);
    }

    private function sortOptions(string $selected): array
    {
        $options = [
            'followers' => 'Most Followed',
            'cards' => 'Most Cards',
            'rating' => 'Highest Rated',
            'reviews' => 'Most Reviewed',
            'newest' => 'Newest',
        ];

        return array_map(static fn (string $value, string $label): array => [
            'value' => $value,
            'label' => $label,
            'selected' => $selected === $value ? 'selected' : '',
        ], array_keys($options), $options);
    }

    private function normalizeSort(string $sort): string
    {
        return in_array($sort, ['followers', 'cards', 'rating', 'reviews', 'newest'], true) ? $sort : 'followers';
    }

    private function normalizeDeckType(string $deckType): string
    {
        return in_array($deckType, ['general', 'language'], true) ? $deckType : '';
    }

    private function ratingOptions(int $selected = 5): array
    {
        return array_map(static fn (int $value): array => [
            'value' => $value,
            'label' => str_repeat('★', $value),
            'selected' => $value === $selected ? 'selected' : '',
        ], [5, 4, 3, 2, 1]);
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

    private function compactNumber(int $value): string
    {
        if ($value >= 1000) {
            return rtrim(rtrim(number_format($value / 1000, 1), '0'), '.') . 'k';
        }

        return (string) $value;
    }

    private function nav(string $active): array
    {
        return [
            'dashboard' => $active === 'dashboard' ? 'is-active' : '',
            'decks' => $active === 'decks' ? 'is-active' : '',
            'explore' => $active === 'explore' ? 'is-active' : '',
            'stats' => $active === 'stats' ? 'is-active' : '',
            'admin' => $active === 'admin' ? 'is-active' : '',
            'settings' => $active === 'settings' ? 'is-active' : '',
        ];
    }
}
