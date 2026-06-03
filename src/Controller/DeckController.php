<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Http\Request;
use FlashMind\Repository\CardRepository;
use FlashMind\Repository\DeckRepository;
use FlashMind\Repository\UserRepository;

final class DeckController extends BaseController
{
    public function __construct(
        private readonly DeckRepository $decks,
        private readonly CardRepository $cards,
        private readonly UserRepository $users,
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
            'old' => [],
        ], 'layout/dashboard');
    }

    public function index(Request $request): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $userModel = $this->users->findById((int) $user['id']);
        $username = $userModel?->username ?? ($user['username'] ?? 'Alex');
        $displayName = trim((string) preg_replace('/\s+/', ' ', $username));
        $displayName = $displayName === '' ? 'Alex' : $displayName;
        $initials = strtoupper(substr($displayName, 0, 1));

        $decks = $this->decks->findByUserId((int) $user['id']);
        $cardsHtml = $this->buildDeckCards($decks);

        $this->render('decks/index', [
            'title' => 'My Decks',
            'displayName' => $displayName,
            'userInitials' => $initials,
            'nav' => [
                'dashboard' => '',
                'decks' => 'is-active',
                'explore' => '',
                'stats' => '',
                'settings' => '',
            ],
            'raw' => [
                'cards' => $cardsHtml,
            ],
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
        $cardsHtml = $this->buildCards($cards, $deckId);

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
            ],
            'raw' => [
                'cards' => $cardsHtml,
            ],
        ], 'layout/dashboard');
    }

    public function store(Request $request): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $deckType = (string) $request->input('deck_type', 'general');
        $sourceLanguage = trim((string) $request->input('source_language', ''));
        $targetLanguage = trim((string) $request->input('target_language', ''));
        $category = trim((string) $request->input('category', ''));
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
                'old' => [
                    'name' => $name,
                    'description' => $description,
                    'deck_type' => $deckType,
                    'source_language' => $sourceLanguage,
                    'target_language' => $targetLanguage,
                    'category' => $category,
                    'is_public' => $isPublic ? 'on' : '',
                ],
            ], 'layout/dashboard');

            return;
        }

        $deckId = $this->decks->create([
            'user_id' => (int) $user['id'],
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'deck_type' => $deckType,
            'source_language' => $sourceLanguage,
            'target_language' => $deckType === 'language' ? $targetLanguage : null,
            'category' => $category === '' ? null : $category,
            'is_public' => $isPublic,
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

    public function addCard(Request $request, string $deckId): void
    {
        $deckId = (int) $deckId;
        $this->requireAuth();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
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

        $deck = $this->decks->findByIdForUser($deckId, (int) $user['id']);
        if ($deck === null) {
            $this->redirect('/decks');
        }

        $this->cards->delete($cardId, $deckId);
        $this->redirect('/decks/' . $deckId);
    }

    private function buildDeckCards(array $decks): string
    {
        if ($decks === []) {
            return '<div class="deck-empty">No decks yet. Create your first deck.</div>';
        }

        $cards = '';

        foreach ($decks as $deck) {
            $name = htmlspecialchars((string) ($deck['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $category = htmlspecialchars((string) ($deck['category'] ?? ''), ENT_QUOTES, 'UTF-8');
            $deckType = (string) ($deck['deck_type'] ?? 'general');
            $sourceLang = htmlspecialchars((string) ($deck['source_language'] ?? ''), ENT_QUOTES, 'UTF-8');
            $targetLang = htmlspecialchars((string) ($deck['target_language'] ?? ''), ENT_QUOTES, 'UTF-8');

            $categoryKey = strtolower(preg_replace('/\s+/', '-', (string) ($deck['category'] ?? 'general')));
            if ($categoryKey === '') {
                $categoryKey = 'general';
            }

            $icon = $this->categoryIcon($categoryKey);
            $badge = $deckType === 'language' && $targetLang !== ''
                ? $sourceLang . ' -> ' . $targetLang
                : ($category !== '' ? $category : 'General');

            $cards .= '<article class="deck-card">'
                . '<a class="deck-card-link" href="/decks/' . (int) $deck['id'] . '">'
                . '<div class="deck-card-icon deck-card-icon-' . $categoryKey . '">' . $icon . '</div>'
                . '<div class="deck-card-body">'
                . '<h3>' . $name . '</h3>'
                . '<p>' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<div class="card-progress"><span style="width: 0%"></span></div>'
                . '<div class="deck-card-footer"><span></span><strong>0%</strong></div>'
                . '</div>'
                . '</a>'
                . '</article>';
        }

        return $cards;
    }

    private function categoryIcon(string $categoryKey): string
    {
        return match ($categoryKey) {
            'language' => 'Aa',
            'science' => 'O',
            'history' => 'H',
            'personal' => 'P',
            'academic' => 'A',
            default => 'D',
        };
    }

    private function buildCards(array $cards, int $deckId): string
    {
        if ($cards === []) {
            return '<div class="deck-cards-empty">No cards yet. Add your first card.</div>';
        }

        $html = '';

        foreach ($cards as $card) {
            $front = htmlspecialchars((string) ($card['front_question'] ?? ''), ENT_QUOTES, 'UTF-8');
            $answer = htmlspecialchars((string) ($card['answer'] ?? ''), ENT_QUOTES, 'UTF-8');
            $example = htmlspecialchars((string) ($card['example_sentence'] ?? ''), ENT_QUOTES, 'UTF-8');
            $translated = htmlspecialchars((string) ($card['translated_example'] ?? ''), ENT_QUOTES, 'UTF-8');
            $imageUrl = htmlspecialchars((string) ($card['image_url'] ?? ''), ENT_QUOTES, 'UTF-8');

            $imageMarkup = $imageUrl !== '' ? '<img src="' . $imageUrl . '" alt="Card image">' : '<img src="/icons/empty_photo.svg" alt="No image">';
            $exampleMarkup = $example !== '' ? '<p class="card-example">' . $example . '</p>' : '';
            $translatedMarkup = $translated !== '' ? '<p class="card-example muted">' . $translated . '</p>' : '';

            $html .= '<article class="deck-card-row">'
                . '<div class="deck-card-col">'
                . '<span class="deck-card-label">Front (Question)</span>'
                . '<h3>' . $front . '</h3>'
                . $exampleMarkup
                . '</div>'
                . '<div class="deck-card-col">'
                . '<span class="deck-card-label">Back (Answer)</span>'
                . '<p>' . $answer . '</p>'
                . $translatedMarkup
                . '</div>'
                . '<div class="deck-card-media">' . $imageMarkup . '</div>'
                . '<div class="deck-card-actions" aria-label="Card actions">'
                . '<button type="button" class="card-icon-btn" title="Edit" aria-label="Edit"'
                . ' data-card-id="' . (int) $card['id'] . '"'
                . ' data-card-front="' . $front . '"'
                . ' data-card-example="' . $example . '"'
                . ' data-card-image="' . $imageUrl . '"'
                . ' data-card-answer="' . $answer . '"'
                . ' data-card-translated="' . $translated . '">' 
                . '<img src="/icons/edit_button.svg" alt="Edit"></button>'
                . '<form method="post" action="/decks/' . (int) $deckId . '/cards/' . (int) $card['id'] . '/delete" class="inline-form" data-confirm-card-delete>'
                . '<button type="submit" class="card-icon-btn danger" title="Delete" aria-label="Delete">'
                . '<img src="/icons/delete_button.svg" alt="Delete"></button>'
                . '</form>'
                . '</div>'
                . '</article>';
        }

        return $html;
    }
}
