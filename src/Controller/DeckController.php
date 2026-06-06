<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Http\Request;
use FlashMind\Repository\CardRepository;
use FlashMind\Repository\DeckRepository;
use FlashMind\Repository\LearningRepository;
use FlashMind\Repository\UserRepository;

final class DeckController extends BaseController
{
    public function __construct(
        private readonly DeckRepository $decks,
        private readonly CardRepository $cards,
        private readonly UserRepository $users,
        private readonly LearningRepository $learning,
    ) {
    }

    public function create(Request $request): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        $userModel = $user !== null ? $this->users->findById((int) $user['id']) : null;
        $username = $userModel?->username ?? ($user['username'] ?? 'Alex');
        $displayName = trim((string) preg_replace('/\s+/', ' ', $username));
        $displayName = $displayName === '' ? 'Alex' : $displayName;
        $initials = strtoupper(substr($displayName, 0, 1));

        $this->render('decks/create', [
            'title' => 'Create Deck',
            'displayName' => $displayName,
            'userInitials' => $initials,
            'nav' => [
                'dashboard' => '',
                'decks' => 'is-active',
                'explore' => '',
                'stats' => '',
                'settings' => '',
            ],
            'errors' => [],
            'form' => $this->deckFormMeta('create'),
            'old' => $this->prepareDeckFormValues([]),
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=19">',
                'extraJs' => '<script defer src="/scripts/deck-create.js?v=2"></script>',
            ],
        ], 'layout/dashboard');
    }

    public function edit(Request $request, string $deckId): void
    {
        $deckId = (int) $deckId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            $deck = $this->guestDeck($deckId);
            if ($deck === null) {
                $this->redirect('/decks');
            }

            $profile = $this->profileData($user);
            $this->render('decks/create', [
                'title' => 'Edit Deck',
                'displayName' => $profile['displayName'],
                'userInitials' => $profile['initials'],
                'nav' => [
                    'dashboard' => '',
                    'decks' => 'is-active',
                    'explore' => '',
                    'stats' => '',
                    'settings' => '',
                ],
                'errors' => [],
                'form' => $this->deckFormMeta('edit', $deckId),
                'old' => $this->prepareDeckFormValues($deck),
                'raw' => [
                    'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=19">',
                    'extraJs' => '<script defer src="/scripts/deck-create.js?v=2"></script>',
                ],
            ], 'layout/dashboard');
            return;
        }

        $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/decks');
        }

        $userModel = $this->users->findById((int) $user['id']);
        $username = $userModel?->username ?? ($user['username'] ?? 'Alex');
        $displayName = trim((string) preg_replace('/\s+/', ' ', $username));
        $displayName = $displayName === '' ? 'Alex' : $displayName;

        $this->render('decks/create', [
            'title' => 'Edit Deck',
            'displayName' => $displayName,
            'userInitials' => strtoupper(substr($displayName, 0, 1)),
            'nav' => [
                'dashboard' => '',
                'decks' => 'is-active',
                'explore' => '',
                'stats' => '',
                'settings' => '',
            ],
            'errors' => [],
            'form' => $this->deckFormMeta('edit', $deckId),
            'old' => $this->prepareDeckFormValues($deck),
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=19">',
                'extraJs' => '<script defer src="/scripts/deck-create.js?v=2"></script>',
            ],
        ], 'layout/dashboard');
    }

    public function index(Request $request): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            $this->renderDeckIndex($user, $this->prepareDeckCards($this->guestDecks()), $this->prepareDeckCards($this->guestFollowedDecks(), true));
            return;
        }

        $decks = $this->learning->deckStatistics((int) $user['id']);
        $deckCards = $this->prepareDeckCards($decks);
        $followingDecks = $this->decks->followedDecks((int) $user['id']);

        $this->renderDeckIndex($user, $deckCards, $this->prepareDeckCards($followingDecks, true));
    }

    private function renderDeckIndex(array $user, array $deckCards, array $followedDeckCards): void
    {
        $profile = $this->profileData($user);

        $this->render('decks/index', [
            'title' => 'My Decks',
            'displayName' => $profile['displayName'],
            'userInitials' => $profile['initials'],
            'nav' => [
                'dashboard' => '',
                'decks' => 'is-active',
                'explore' => '',
                'stats' => '',
                'settings' => '',
            ],
            'deckList' => [
                'empty' => $deckCards === [] ? 'No decks yet. Create your first deck.' : '',
                'emptyClass' => $deckCards === [] ? '' : 'is-hidden',
            ],
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=19">',
                'extraJs' => '<script defer src="/scripts/deck-show.js?v=1"></script>',
            ],
            'decks' => $deckCards,
            'followingDecks' => [
                'empty' => $followedDeckCards === [] ? 'No followed decks yet. Explore public decks to follow one.' : '',
                'emptyClass' => $followedDeckCards === [] ? '' : 'is-hidden',
            ],
            'followedDecks' => $followedDeckCards,
        ], 'layout/dashboard');
    }

    public function show(Request $request, string $deckId): void
    {
        $deckId = (int) $deckId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            $deck = $this->guestDeck($deckId);
            if ($deck === null) {
                $this->redirect('/decks');
            }

            $profile = $this->profileData($user);
            $cardRows = $this->prepareCardRows($deck['cards'] ?? []);
            $this->render('decks/show', [
                'title' => 'Deck: ' . ($deck['name'] ?? 'Deck'),
                'displayName' => $profile['displayName'],
                'userInitials' => $profile['initials'],
                'nav' => ['dashboard' => '', 'decks' => 'is-active', 'explore' => '', 'stats' => '', 'settings' => ''],
                'deck' => [
                    'id' => $deck['id'],
                    'name' => $deck['name'] ?? 'Deck',
                    'description' => $deck['description'] ?? '',
                    'cardsEmpty' => $cardRows === [] ? 'No cards yet. Add your first card.' : '',
                    'cardsEmptyClass' => $cardRows === [] ? '' : 'is-hidden',
                ],
                'deckTabs' => [],
                'panels' => ['cardsHidden' => '', 'reviewsHidden' => 'is-hidden'],
                'publicReviews' => [],
                'reviews' => [],
                'raw' => [
                    'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=19">',
                    'extraJs' => '<script defer src="/scripts/deck-show.js?v=1"></script>',
                ],
                'cards' => $cardRows,
            ], 'layout/dashboard');
            return;
        }

        $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/decks');
        }

        $userModel = $this->users->findById((int) $user['id']);
        $username = $userModel?->username ?? ($user['username'] ?? 'Alex');
        $displayName = trim((string) preg_replace('/\s+/', ' ', $username));
        $displayName = $displayName === '' ? 'Alex' : $displayName;
        $initials = strtoupper(substr($displayName, 0, 1));

        $cards = $this->cards->findByDeckId($deckId);
        $cardRows = $this->prepareCardRows($cards);
        $isPublicDeck = $this->isTruthy($deck['is_public'] ?? false);
        $tab = (string) $request->input('tab', 'cards');
        $tab = $tab === 'reviews' && $isPublicDeck ? 'reviews' : 'cards';
        $reviews = $isPublicDeck ? $this->decks->reviewsForDeck($deckId) : [];
        $reviewCount = count($reviews);
        $averageRating = $reviewCount === 0
            ? '0.0'
            : number_format(array_sum(array_map(static fn (array $review): int => (int) $review['rating'], $reviews)) / $reviewCount, 1);

        $this->render('decks/show', [
            'title' => 'Deck: ' . ($deck['name'] ?? 'Deck'),
            'displayName' => $displayName,
            'userInitials' => $initials,
            'nav' => [
                'dashboard' => '',
                'decks' => 'is-active',
                'explore' => '',
                'stats' => '',
                'settings' => '',
            ],
            'deck' => [
                'id' => $deck['id'],
                'name' => $deck['name'] ?? 'Deck',
                'description' => $deck['description'] ?? '',
                'cardsEmpty' => $cardRows === [] ? 'No cards yet. Add your first card.' : '',
                'cardsEmptyClass' => $cardRows === [] ? '' : 'is-hidden',
            ],
            'deckTabs' => $isPublicDeck ? [[
                'cardsActive' => $tab === 'cards' ? 'is-active' : '',
                'reviewsActive' => $tab === 'reviews' ? 'is-active' : '',
            ]] : [],
            'panels' => [
                'cardsHidden' => $tab === 'cards' ? '' : 'is-hidden',
                'reviewsHidden' => $tab === 'reviews' ? '' : 'is-hidden',
            ],
            'publicReviews' => $isPublicDeck ? [[
                'averageRating' => $averageRating,
                'reviewCount' => $reviewCount,
                'empty' => $reviews === [] ? 'No reviews yet. Reviews will appear here when learners rate this public deck.' : '',
                'emptyClass' => $reviews === [] ? '' : 'is-hidden',
            ]] : [],
            'reviews' => $this->prepareDeckReviews($reviews),
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=19">',
                'extraJs' => '<script defer src="/scripts/deck-show.js?v=1"></script>',
            ],
            'cards' => $cardRows,
        ], 'layout/dashboard');
    }

    public function study(Request $request, string $deckId): void
    {
        $deckId = (int) $deckId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            $deck = $this->guestDeck($deckId);
            if ($deck === null) {
                $this->redirect('/decks');
            }
            $cards = array_slice($deck['cards'] ?? [], 0, 10);
            $this->renderStudy($user, $deck, $cards);
            return;
        }

        $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/decks');
        }

        $userModel = $this->users->findById((int) $user['id']);
        $username = $userModel?->username ?? ($user['username'] ?? 'Alex');
        $displayName = trim((string) preg_replace('/\s+/', ' ', $username));
        $displayName = $displayName === '' ? 'Alex' : $displayName;
        $initials = strtoupper(substr($displayName, 0, 1));

        $cards = $this->cards->findForStudy($deckId, (int) $user['id'], 10);
        $_SESSION[$this->studyTimerKey((int) $user['id'], $deckId)] = time();
        $this->renderStudy($user, $deck, $cards);
    }

    private function renderStudy(array $user, array $deck, array $cards): void
    {
        $profile = $this->profileData($user);
        $cardsJson = json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        $this->render('decks/study', [
            'title' => 'Study: ' . ($deck['name'] ?? 'Deck'),
            'displayName' => $profile['displayName'],
            'userInitials' => $profile['initials'],
            'bodyClass' => 'is-study-session',
            'nav' => [
                'dashboard' => '',
                'decks' => 'is-active',
                'explore' => '',
                'stats' => '',
                'settings' => '',
            ],
            'deck' => [
                'id' => $deck['id'],
                'name' => $deck['name'] ?? 'Deck',
                'description' => $deck['description'] ?? '',
            ],
            'study' => [
                'cardsJson' => $cardsJson,
            ],
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/study.css?v=5">',
                'extraJs' => '<script defer src="/scripts/study-session.js?v=3"></script>',
            ],
        ], 'layout/dashboard');
    }

    public function completeStudy(Request $request, string $deckId): void
    {
        $deckId = (int) $deckId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ($this->isGuestUser($user)) {
            $answers = json_decode((string) $request->input('answers', '[]'), true);
            $answers = is_array($answers) ? $answers : [];
            $answers = array_values(array_filter($answers, static fn (mixed $answer): bool => is_array($answer)));
            $correct = count(array_filter($answers, static fn (array $answer): bool => ($answer['correct'] ?? false) === true));
            $wrong = max(0, count($answers) - $correct);
            $xp = array_reduce($answers, static fn (int $carry, array $answer): int => $carry + ((($answer['correct'] ?? false) === true) ? (($answer['usedHint'] ?? false) ? 5 : 10) : 0), 0);
            $sessionId = time();
            $_SESSION['guest_study_summaries'][$deckId][$sessionId] = [
                'deck_name' => $this->guestDeck($deckId)['name'] ?? 'Deck',
                'total_cards' => count($answers),
                'correct_cards' => $correct,
                'wrong_cards' => $wrong,
                'xp_earned' => $xp,
            ];
            $this->json(['success' => true, 'redirect' => '/decks/' . $deckId . '/study/summary/' . $sessionId]);
        }

        $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->json(['success' => false, 'message' => 'Deck not found'], 404);
        }

        $answersJson = (string) $request->input('answers', '[]');
        $answers = json_decode($answersJson, true);
        if (!is_array($answers)) {
            $this->json(['success' => false, 'message' => 'Invalid answers payload'], 422);
        }

        $timerKey = $this->studyTimerKey((int) $user['id'], $deckId);
        $startedAt = (int) ($_SESSION[$timerKey] ?? 0);
        $fallbackDuration = (int) $request->input('durationSeconds', 0);
        $durationSeconds = $startedAt > 0 ? time() - $startedAt : $fallbackDuration;
        $durationSeconds = max(0, min(86400, $durationSeconds));
        unset($_SESSION[$timerKey]);

        $summary = $this->learning->recordStudySession((int) $user['id'], $deckId, $answers, $durationSeconds);

        $this->json([
            'success' => true,
            'summary' => $summary,
            'redirect' => '/decks/' . $deckId . '/study/summary/' . (int) $summary['sessionId'],
        ]);
    }

    public function studySummary(Request $request, string $deckId, string $sessionId): void
    {
        $deckId = (int) $deckId;
        $sessionId = (int) $sessionId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            $session = $_SESSION['guest_study_summaries'][$deckId][$sessionId] ?? null;
            if (!is_array($session)) {
                $this->redirect('/decks/' . $deckId);
            }
            $this->renderStudySummary($user, $deckId, $session);
            return;
        }

        $session = $this->learning->findStudySessionForUser((int) $user['id'], $deckId, $sessionId);
        if ($session === null) {
            $this->redirect('/decks/' . $deckId);
        }

        $dashboardStats = $this->learning->dashboardStats((int) $user['id']);
        $this->renderStudySummary($user, $deckId, $session, 'Level ' . $dashboardStats['level'] . ' • ' . $dashboardStats['xp']);
    }

    private function renderStudySummary(array $user, int $deckId, array $session, string $levelText = 'Guest mode • progress not saved'): void
    {
        $profile = $this->profileData($user);
        $total = (int) $session['total_cards'];
        $correct = (int) $session['correct_cards'];
        $wrong = (int) $session['wrong_cards'];
        $correctPercent = $total === 0 ? 0 : (int) round(($correct / $total) * 100);
        $wrongPercent = $total === 0 ? 0 : (int) round(($wrong / $total) * 100);

        $this->render('decks/study_summary', [
            'title' => 'Session Summary',
            'displayName' => $profile['displayName'],
            'userInitials' => $profile['initials'],
            'bodyClass' => 'is-study-session',
            'nav' => [
                'dashboard' => '',
                'decks' => 'is-active',
                'explore' => '',
                'stats' => '',
                'admin' => '',
                'settings' => '',
            ],
            'deck' => [
                'id' => $deckId,
                'name' => $session['deck_name'] ?? 'Deck',
            ],
            'summary' => [
                'correct' => $correct,
                'wrong' => $wrong,
                'xp' => (int) $session['xp_earned'],
                'correctPercent' => $correctPercent,
                'wrongPercent' => $wrongPercent,
                'levelText' => $levelText,
            ],
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/study.css?v=5">',
            ],
        ], 'layout/dashboard');
    }

    public function exportCsv(Request $request, string $deckId): void
    {
        $deckId = (int) $deckId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            $deck = $this->guestDeck($deckId);
            if ($deck === null) {
                $this->redirect('/decks');
            }
            $cards = is_array($deck['cards'] ?? null) ? $deck['cards'] : [];
            $deckName = (string) ($deck['name'] ?? 'deck');
        } else {
            $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
            if ($deck === null) {
                $this->redirect('/decks');
            }
            $cards = $this->cards->findByDeckId($deckId);
            $deckName = (string) ($deck['name'] ?? 'deck');
        }

        $filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($deckName)) ?: 'deck';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '-cards.csv"');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            exit;
        }

        fputcsv($output, ['front_question', 'answer', 'example_sentence', 'image_url', 'translated_example']);
        foreach ($cards as $card) {
            fputcsv($output, [
                (string) ($card['front_question'] ?? ''),
                (string) ($card['answer'] ?? ''),
                (string) ($card['example_sentence'] ?? ''),
                (string) ($card['image_url'] ?? ''),
                (string) ($card['translated_example'] ?? ''),
            ]);
        }

        fclose($output);
        exit;
    }

    public function importCsv(Request $request, string $deckId): void
    {
        $deckId = (int) $deckId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            if ($this->guestDeck($deckId) === null) {
                $this->redirect('/decks');
            }

            foreach ($this->csvImportRows() as $row) {
                $this->addGuestCardFromData($deckId, $row);
            }

            $this->redirect('/decks/' . $deckId);
        }

        $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/decks');
        }

        foreach ($this->csvImportRows() as $row) {
            $this->cards->create([
                'deck_id' => $deckId,
                'front_question' => $row['front_question'],
                'example_sentence' => $row['example_sentence'] === '' ? null : $row['example_sentence'],
                'image_url' => $row['image_url'] === '' ? null : $row['image_url'],
                'answer' => $row['answer'],
                'translated_example' => $row['translated_example'] === '' ? null : $row['translated_example'],
            ]);
        }

        $this->redirect('/decks/' . $deckId);
    }

    public function store(Request $request): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        [$data, $errors] = $this->deckPayload($request);

        if ($errors !== []) {
            if ($request->expectsJson()) {
                $this->json([
                    'success' => false,
                    'errors' => $errors,
                ], 422);
            }

            $this->render('decks/create', [
                'title' => 'Create Deck',
                'displayName' => $user['username'] ?? 'Alex',
                'userInitials' => strtoupper(substr($user['username'] ?? 'A', 0, 1)),
                'nav' => [
                    'dashboard' => '',
                    'decks' => 'is-active',
                    'explore' => '',
                    'stats' => '',
                    'settings' => '',
                ],
                'errors' => $errors,
                'form' => $this->deckFormMeta('create'),
                'old' => $this->prepareDeckFormValues($data),
                'raw' => [
                    'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=19">',
                    'extraJs' => '<script defer src="/scripts/deck-create.js?v=2"></script>',
                ],
            ], 'layout/dashboard');

            return;
        }

        if ($this->isGuestUser($user)) {
            $deckId = $this->createGuestDeck($data);
            $this->redirect('/decks/' . $deckId);
        }

        $deckId = $this->decks->create([
            'user_id' => (int) $user['id'],
            ...$data,
        ]);

        if ($request->expectsJson()) {
            $this->json([
                'success' => true,
                'deckId' => $deckId,
                'redirect' => '/dashboard',
            ]);
        }

        $this->redirect('/dashboard');
    }

    public function update(Request $request, string $deckId): void
    {
        $deckId = (int) $deckId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            [$data, $errors] = $this->deckPayload($request);
            if ($errors !== []) {
                $profile = $this->profileData($user);
                $this->render('decks/create', [
                    'title' => 'Edit Deck',
                    'displayName' => $profile['displayName'],
                    'userInitials' => $profile['initials'],
                    'nav' => [
                        'dashboard' => '',
                        'decks' => 'is-active',
                        'explore' => '',
                        'stats' => '',
                        'settings' => '',
                    ],
                    'errors' => $errors,
                    'form' => $this->deckFormMeta('edit', $deckId),
                    'old' => $this->prepareDeckFormValues($data),
                    'raw' => [
                        'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=19">',
                        'extraJs' => '<script defer src="/scripts/deck-create.js?v=2"></script>',
                    ],
                ], 'layout/dashboard');
                return;
            }

            $this->updateGuestDeck($deckId, $data);
            $this->redirect('/decks/' . $deckId);
        }

        $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/decks');
        }

        [$data, $errors] = $this->deckPayload($request);

        if ($errors !== []) {
            $this->render('decks/create', [
                'title' => 'Edit Deck',
                'displayName' => $user['username'] ?? 'Alex',
                'userInitials' => strtoupper(substr($user['username'] ?? 'A', 0, 1)),
                'nav' => [
                    'dashboard' => '',
                    'decks' => 'is-active',
                    'explore' => '',
                    'stats' => '',
                    'settings' => '',
                ],
                'errors' => $errors,
                'form' => $this->deckFormMeta('edit', $deckId),
                'old' => $this->prepareDeckFormValues($data),
                'raw' => [
                    'extraCss' => '<link rel="stylesheet" href="/styles/decks.css?v=19">',
                    'extraJs' => '<script defer src="/scripts/deck-create.js?v=2"></script>',
                ],
            ], 'layout/dashboard');

            return;
        }

        $this->decks->updateForUser($deckId, (int) $user['id'], $data);
        $this->redirect('/decks/' . $deckId);
    }

    public function addCard(Request $request, string $deckId): void
    {
        $deckId = (int) $deckId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            $this->addGuestCard($deckId, $request);
            $this->redirect('/decks/' . $deckId);
        }

        $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/decks');
        }

        $front = trim((string) $request->input('front_question', ''));
        $answer = trim((string) $request->input('answer', ''));
        $example = trim((string) $request->input('example_sentence', ''));
        $imageUrl = trim((string) $request->input('image_url', ''));
        $translatedExample = trim((string) $request->input('translated_example', ''));

        if ($front === '' || $answer === '') {
            $this->redirect('/decks/' . $deckId);
        }

        $this->cards->create([
            'deck_id' => $deckId,
            'front_question' => $front,
            'example_sentence' => $example === '' ? null : $example,
            'image_url' => $imageUrl === '' ? null : $imageUrl,
            'answer' => $answer,
            'translated_example' => $translatedExample === '' ? null : $translatedExample,
        ]);

        $this->redirect('/decks/' . $deckId);
    }

    public function updateCard(Request $request, string $deckId, string $cardId): void
    {
        $deckId = (int) $deckId;
        $cardId = (int) $cardId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            $this->updateGuestCard($deckId, $cardId, $request);
            $this->redirect('/decks/' . $deckId);
        }

        $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/decks');
        }

        $front = trim((string) $request->input('front_question', ''));
        $answer = trim((string) $request->input('answer', ''));
        $example = trim((string) $request->input('example_sentence', ''));
        $imageUrl = trim((string) $request->input('image_url', ''));
        $translatedExample = trim((string) $request->input('translated_example', ''));

        if ($front === '' || $answer === '') {
            $this->redirect('/decks/' . $deckId);
        }

        $this->cards->update($cardId, $deckId, [
            'front_question' => $front,
            'example_sentence' => $example === '' ? null : $example,
            'image_url' => $imageUrl === '' ? null : $imageUrl,
            'answer' => $answer,
            'translated_example' => $translatedExample === '' ? null : $translatedExample,
        ]);

        $this->redirect('/decks/' . $deckId);
    }

    public function delete(Request $request, string $deckId): void
    {
        $deckId = (int) $deckId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            $decks = array_values(array_filter(
                $this->guestDecks(),
                static fn (array $deck): bool => (int) ($deck['id'] ?? 0) !== $deckId
            ));
            $this->saveGuestDecks($decks);
            $this->redirect('/decks');
        }

        $this->decks->deleteForUser($deckId, (int) $user['id']);
        $this->redirect('/decks');
    }

    public function deleteCard(Request $request, string $deckId, string $cardId): void
    {
        $deckId = (int) $deckId;
        $cardId = (int) $cardId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        if ($this->isGuestUser($user)) {
            $this->deleteGuestCard($deckId, $cardId);
            $this->redirect('/decks/' . $deckId);
        }

        $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/decks');
        }

        $this->cards->delete($cardId, $deckId);
        $this->redirect('/decks/' . $deckId);
    }

    private function profileData(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $userModel = $userId > 0 ? $this->users->findById($userId) : null;
        $username = $userModel?->username ?? ($user['username'] ?? 'Alex');
        $displayName = trim((string) preg_replace('/\s+/', ' ', $username));
        $displayName = $displayName === '' ? 'Alex' : $displayName;

        return [
            'displayName' => $displayName,
            'initials' => strtoupper(substr($displayName, 0, 1)),
        ];
    }

    private function guestDecks(): array
    {
        return is_array($_SESSION['guest_decks'] ?? null) ? $_SESSION['guest_decks'] : [];
    }

    private function saveGuestDecks(array $decks): void
    {
        $_SESSION['guest_decks'] = array_values($decks);
    }

    private function guestDeck(int $deckId): ?array
    {
        foreach ($this->guestDecks() as $deck) {
            if (is_array($deck) && (int) ($deck['id'] ?? 0) === $deckId) {
                return $deck;
            }
        }

        return null;
    }

    private function guestFollowedDecks(): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', is_array($_SESSION['guest_followed_decks'] ?? null) ? $_SESSION['guest_followed_decks'] : []),
            static fn (int $deckId): bool => $deckId > 0
        )));

        if ($ids === []) {
            return [];
        }

        return array_values(array_filter(
            $this->decks->publicDecks(0),
            static fn (array $deck): bool => in_array((int) ($deck['id'] ?? 0), $ids, true)
        ));
    }

    private function createGuestDeck(array $data): int
    {
        $decks = $this->guestDecks();
        $ids = array_map(static fn (array $deck): int => (int) ($deck['id'] ?? 0), $decks);
        $nextId = min(array_merge([0], $ids)) - 1;

        $decks[] = array_merge($data, [
            'id' => $nextId,
            'is_public' => false,
            'card_count' => 0,
            'mastery' => 0,
            'average_rating' => 0,
            'cards' => [],
        ]);

        $this->saveGuestDecks($decks);

        return $nextId;
    }

    private function updateGuestDeck(int $deckId, array $data): void
    {
        $decks = $this->guestDecks();
        foreach ($decks as $index => $deck) {
            if (!is_array($deck) || (int) ($deck['id'] ?? 0) !== $deckId) {
                continue;
            }

            $decks[$index] = array_merge($deck, $data, [
                'id' => $deckId,
                'is_public' => false,
                'card_count' => count($deck['cards'] ?? []),
                'cards' => $deck['cards'] ?? [],
            ]);
            $this->saveGuestDecks($decks);
            return;
        }
    }

    private function addGuestCard(int $deckId, Request $request): void
    {
        $front = trim((string) $request->input('front_question', ''));
        $answer = trim((string) $request->input('answer', ''));
        if ($front === '' || $answer === '') {
            return;
        }

        $this->addGuestCardFromData($deckId, [
            'front_question' => $front,
            'answer' => $answer,
            'example_sentence' => trim((string) $request->input('example_sentence', '')),
            'image_url' => trim((string) $request->input('image_url', '')),
            'translated_example' => trim((string) $request->input('translated_example', '')),
        ]);
    }

    private function addGuestCardFromData(int $deckId, array $data): void
    {
        $front = trim((string) ($data['front_question'] ?? ''));
        $answer = trim((string) ($data['answer'] ?? ''));
        if ($front === '' || $answer === '') {
            return;
        }

        $decks = $this->guestDecks();
        foreach ($decks as $index => $deck) {
            if (!is_array($deck) || (int) ($deck['id'] ?? 0) !== $deckId) {
                continue;
            }

            $cards = is_array($deck['cards'] ?? null) ? $deck['cards'] : [];
            $cardIds = array_map(static fn (array $card): int => (int) ($card['id'] ?? 0), $cards);
            $cards[] = [
                'id' => max(array_merge([0], $cardIds)) + 1,
                'deck_id' => $deckId,
                'front_question' => $front,
                'example_sentence' => trim((string) ($data['example_sentence'] ?? '')) ?: null,
                'image_url' => trim((string) ($data['image_url'] ?? '')) ?: null,
                'answer' => $answer,
                'translated_example' => trim((string) ($data['translated_example'] ?? '')) ?: null,
                'created_at' => date('c'),
            ];

            $decks[$index]['cards'] = $cards;
            $decks[$index]['card_count'] = count($cards);
            $this->saveGuestDecks($decks);
            return;
        }
    }

    private function csvImportRows(): array
    {
        $file = $_FILES['csv_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [];
        }

        $handle = fopen((string) ($file['tmp_name'] ?? ''), 'r');
        if ($handle === false) {
            return [];
        }

        $rows = [];
        $headers = null;
        $firstRow = true;

        while (($csvRow = fgetcsv($handle)) !== false) {
            $csvRow = array_map(static fn (mixed $value): string => trim((string) $value), $csvRow);
            if (implode('', $csvRow) === '') {
                continue;
            }

            if ($firstRow) {
                $firstRow = false;
                $normalized = array_map([$this, 'normalizeCsvHeader'], $csvRow);
                if (in_array('front_question', $normalized, true) || in_array('answer', $normalized, true)) {
                    $headers = $normalized;
                    continue;
                }
            }

            $row = $headers === null
                ? $this->csvRowFromPosition($csvRow)
                : $this->csvRowFromHeaders($headers, $csvRow);

            if ($row['front_question'] !== '' && $row['answer'] !== '') {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    private function csvRowFromHeaders(array $headers, array $values): array
    {
        $row = array_fill_keys(['front_question', 'answer', 'example_sentence', 'image_url', 'translated_example'], '');

        foreach ($headers as $index => $header) {
            if (array_key_exists($header, $row)) {
                $row[$header] = trim((string) ($values[$index] ?? ''));
            }
        }

        return $row;
    }

    private function csvRowFromPosition(array $values): array
    {
        return [
            'front_question' => trim((string) ($values[0] ?? '')),
            'answer' => trim((string) ($values[1] ?? '')),
            'example_sentence' => trim((string) ($values[2] ?? '')),
            'image_url' => trim((string) ($values[3] ?? '')),
            'translated_example' => trim((string) ($values[4] ?? '')),
        ];
    }

    private function normalizeCsvHeader(string $header): string
    {
        $header = strtolower(trim($header, "\xEF\xBB\xBF \t\n\r\0\x0B"));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?: '';
        $header = trim($header, '_');

        return match ($header) {
            'front', 'front_question', 'question', 'prompt' => 'front_question',
            'back', 'back_answer', 'answer' => 'answer',
            'example', 'example_sentence' => 'example_sentence',
            'image', 'image_url' => 'image_url',
            'translated', 'translated_example', 'translated_example_sentence' => 'translated_example',
            default => $header,
        };
    }

    private function updateGuestCard(int $deckId, int $cardId, Request $request): void
    {
        $front = trim((string) $request->input('front_question', ''));
        $answer = trim((string) $request->input('answer', ''));
        if ($front === '' || $answer === '') {
            return;
        }

        $decks = $this->guestDecks();
        foreach ($decks as $deckIndex => $deck) {
            if (!is_array($deck) || (int) ($deck['id'] ?? 0) !== $deckId) {
                continue;
            }

            $cards = is_array($deck['cards'] ?? null) ? $deck['cards'] : [];
            foreach ($cards as $cardIndex => $card) {
                if ((int) ($card['id'] ?? 0) !== $cardId) {
                    continue;
                }

                $cards[$cardIndex] = array_merge($card, [
                    'front_question' => $front,
                    'example_sentence' => trim((string) $request->input('example_sentence', '')) ?: null,
                    'image_url' => trim((string) $request->input('image_url', '')) ?: null,
                    'answer' => $answer,
                    'translated_example' => trim((string) $request->input('translated_example', '')) ?: null,
                ]);
                $decks[$deckIndex]['cards'] = $cards;
                $this->saveGuestDecks($decks);
                return;
            }
        }
    }

    private function deleteGuestCard(int $deckId, int $cardId): void
    {
        $decks = $this->guestDecks();
        foreach ($decks as $index => $deck) {
            if (!is_array($deck) || (int) ($deck['id'] ?? 0) !== $deckId) {
                continue;
            }

            $cards = array_values(array_filter(
                is_array($deck['cards'] ?? null) ? $deck['cards'] : [],
                static fn (array $card): bool => (int) ($card['id'] ?? 0) !== $cardId
            ));
            $decks[$index]['cards'] = $cards;
            $decks[$index]['card_count'] = count($cards);
            $this->saveGuestDecks($decks);
            return;
        }
    }

    private function prepareDeckCards(array $decks, bool $publicLinks = false): array
    {
        return array_map(function (array $deck) use ($publicLinks): array {
            $name = (string) ($deck['name'] ?? '');
            $category = (string) ($deck['category'] ?? '');
            $deckType = (string) ($deck['deck_type'] ?? 'general');
            $sourceLang = (string) ($deck['source_language'] ?? '');
            $targetLang = (string) ($deck['target_language'] ?? '');
            $backgroundUrl = (string) ($deck['background_url'] ?? '');

            $categoryKey = strtolower(preg_replace('/\s+/', '-', (string) ($deck['category'] ?? 'general')));
            if ($categoryKey === '') {
                $categoryKey = 'general';
            }

            $badge = $deckType === 'language' && $targetLang !== ''
                ? $sourceLang . ' -> ' . $targetLang
                : ($sourceLang !== '' ? $sourceLang : 'General');

            return [
                'id' => (int) $deck['id'],
                'url' => $publicLinks ? '/explore/decks/' . (int) $deck['id'] : '/decks/' . (int) $deck['id'],
                'unfollowAction' => '/explore/decks/' . (int) $deck['id'] . '/unfollow',
                'name' => $name,
                'badge' => $badge,
                'typeLabel' => $deckType === 'language' ? 'Language' : 'General',
                'categoryLabel' => $category !== '' ? $category : 'Uncategorized',
                'categoryKey' => $categoryKey,
                'backgroundClass' => $backgroundUrl !== '' ? 'has-background' : '',
                'backgroundStyle' => $backgroundUrl !== '' ? 'background-image: url(' . $backgroundUrl . ');' : '',
                'icon' => strtoupper(substr($name, 0, 1)),
                'cardCount' => (int) ($deck['card_count'] ?? 0),
                'mastery' => (int) ($deck['mastery'] ?? 0),
                'rating' => number_format((float) ($deck['average_rating'] ?? 0), 1),
            ];
        }, $decks);
    }

    private function prepareDeckReviews(array $reviews): array
    {
        return array_map(function (array $review): array {
            $rating = max(1, min(5, (int) ($review['rating'] ?? 1)));

            return array_merge([
                'username' => (string) ($review['username'] ?? 'User'),
                'comment' => (string) ($review['comment'] ?? ''),
                'date' => date('Y-m-d', strtotime((string) ($review['updated_at'] ?? 'now'))),
                'rating' => $rating,
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

    private function isTruthy(mixed $value): bool
    {
        return $value === true || in_array((string) $value, ['1', 't', 'true'], true);
    }

    private function deckPayload(Request $request): array
    {
        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $deckType = (string) $request->input('deck_type', 'general');
        $sourceLanguage = trim((string) $request->input('source_language', ''));
        $targetLanguage = trim((string) $request->input('target_language', ''));
        $category = trim((string) $request->input('category', ''));
        $backgroundUrl = trim((string) $request->input('background_url', ''));
        $isPublic = $request->input('is_public') === 'on';

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Deck name is required.';
        }

        if ($sourceLanguage === '') {
            $errors['source_language'] = 'Source language is required.';
        }

        if ($deckType !== 'general' && $deckType !== 'language') {
            $errors['deck_type'] = 'Deck type is not valid.';
        }

        if ($deckType === 'language' && $targetLanguage === '') {
            $errors['target_language'] = 'Target language is required for language decks.';
        }

        if ($backgroundUrl !== '' && (!filter_var($backgroundUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($backgroundUrl, PHP_URL_SCHEME), ['http', 'https'], true))) {
            $errors['background_url'] = 'Background photo must be a valid http or https URL.';
        }

        return [[
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'deck_type' => $deckType,
            'source_language' => $sourceLanguage,
            'target_language' => $deckType === 'language' ? $targetLanguage : null,
            'category' => $category === '' ? null : $category,
            'background_url' => $backgroundUrl === '' ? null : $backgroundUrl,
            'is_public' => $isPublic,
        ], $errors];
    }

    private function deckFormMeta(string $mode, ?int $deckId = null): array
    {
        $isEdit = $mode === 'edit';

        return [
            'heading' => $isEdit ? 'Edit Deck' : 'Create New Deck',
            'subheading' => $isEdit ? 'Update deck settings and visual details.' : 'Organize your learning with a custom set of flashcards.',
            'action' => $isEdit && $deckId !== null ? '/decks/' . $deckId . '/edit' : '/decks',
            'submitLabel' => $isEdit ? 'Save Changes' : 'Create Deck',
            'cancelUrl' => $isEdit && $deckId !== null ? '/decks/' . $deckId : '/dashboard',
        ];
    }

    private function prepareDeckFormValues(array $data): array
    {
        $deckType = (string) ($data['deck_type'] ?? 'general');
        $sourceLanguage = (string) ($data['source_language'] ?? '');
        $targetLanguage = (string) ($data['target_language'] ?? '');
        $category = (string) ($data['category'] ?? '');

        $values = [
            'name' => (string) ($data['name'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'background_url' => (string) ($data['background_url'] ?? ''),
            'generalChecked' => $deckType === 'language' ? '' : 'checked',
            'languageChecked' => $deckType === 'language' ? 'checked' : '',
            'isPublicChecked' => in_array($data['is_public'] ?? false, [true, 1, '1', 't', 'true'], true) ? 'checked' : '',
        ];

        foreach (['English', 'Polish', 'German', 'Spanish', 'French', 'Japanese'] as $language) {
            $key = preg_replace('/[^A-Za-z0-9]/', '', $language);
            $values['source' . $key . 'Selected'] = $sourceLanguage === $language ? 'selected' : '';
            $values['target' . $key . 'Selected'] = $targetLanguage === $language ? 'selected' : '';
        }

        foreach (['Academic', 'Language', 'History', 'Science', 'Personal', 'Tech'] as $option) {
            $key = preg_replace('/[^A-Za-z0-9]/', '', $option);
            $values['category' . $key . 'Selected'] = $category === $option ? 'selected' : '';
        }

        return $values;
    }

    private function studyTimerKey(int $userId, int $deckId): string
    {
        return 'study_started_at_' . $userId . '_' . $deckId;
    }

    private function prepareCardRows(array $cards): array
    {
        return array_map(static function (array $card): array {
            $example = (string) ($card['example_sentence'] ?? '');
            $translated = (string) ($card['translated_example'] ?? '');
            $imageUrl = (string) ($card['image_url'] ?? '');

            return [
                'id' => (int) $card['id'],
                'front' => (string) ($card['front_question'] ?? ''),
                'answer' => (string) ($card['answer'] ?? ''),
                'example' => $example,
                'exampleClass' => $example === '' ? 'is-hidden' : '',
                'translated' => $translated,
                'translatedClass' => $translated === '' ? 'is-hidden' : '',
                'imageUrl' => $imageUrl,
                'imageSrc' => $imageUrl === '' ? '/icons/deck/empty_photo.svg' : $imageUrl,
                'imageAlt' => $imageUrl === '' ? 'No image' : 'Card image',
            ];
        }, $cards);
    }
}
