<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Http\Request;
use FlashMind\Repository\DeckRepository;
use FlashMind\Repository\UserRepository;

final class DeckController extends BaseController
{
    public function __construct(
        private readonly DeckRepository $decks,
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
                . '<div class="deck-card-icon deck-card-icon-' . $categoryKey . '">' . $icon . '</div>'
                . '<div class="deck-card-body">'
                . '<h3>' . $name . '</h3>'
                . '<p>' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<div class="card-progress"><span style="width: 0%"></span></div>'
                . '<div class="deck-card-footer"><span></span><strong>0%</strong></div>'
                . '</div>'
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
}
