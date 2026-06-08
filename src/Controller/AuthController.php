<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Http\Request;
use FlashMind\Repository\CardRepository;
use FlashMind\Repository\DeckRepository;
use FlashMind\Service\AuthService;

final class AuthController extends BaseController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly DeckRepository $decks,
        private readonly CardRepository $cards,
    ) {
    }

    public function showLogin(Request $request): void
    {
        $user = $this->currentUser();
        if ($user !== null && !$this->isGuest($user)) {
            $this->redirect('/dashboard');
        }

        $this->render('auth/login', [
            'title' => 'Login',
            'errors' => [],
            'old' => [],
        ]);
    }

    public function login(Request $request): void
    {
        $result = $this->authService->login($request->post);

        if (!$result['success']) {
            if ($request->expectsJson()) {
                $this->json([
                    'success' => false,
                    'errors' => $result['errors'],
                ], 422);
            }

            $this->render('auth/login', [
                'title' => 'Login',
                'errors' => $result['errors'],
                'old' => $request->post,
            ]);

            return;
        }

        $_SESSION['user'] = [
            'id' => $result['user']->id,
            'username' => $result['user']->username,
            'email' => $result['user']->email,
            'role' => $result['user']->roleName,
        ];

        if ($request->expectsJson()) {
            $this->json([
                'success' => true,
                'redirect' => '/dashboard',
                'user' => $_SESSION['user'],
            ]);
        }

        $this->redirect('/dashboard');
    }

    public function showRegister(Request $request): void
    {
        $user = $this->currentUser();
        if ($user !== null && !$this->isGuest($user)) {
            $this->redirect('/dashboard');
        }

        $this->render('auth/register', [
            'title' => 'Register',
            'errors' => [],
            'old' => [],
        ]);
    }

    public function register(Request $request): void
    {
        $guestDecks = $_SESSION['guest_decks'] ?? [];
        $guestFollows = $_SESSION['guest_followed_decks'] ?? [];
        $result = $this->authService->register($request->post);

        if (!$result['success']) {
            if ($request->expectsJson()) {
                $this->json([
                    'success' => false,
                    'errors' => $result['errors'],
                ], 422);
            }

            $this->render('auth/register', [
                'title' => 'Register',
                'errors' => $result['errors'],
                'old' => $request->post,
            ]);

            return;
        }

        $_SESSION['user'] = [
            'id' => $result['user']->id,
            'username' => $result['user']->username,
            'email' => $result['user']->email,
            'role' => $result['user']->roleName,
        ];

        $this->migrateGuestData((int) $result['user']->id, is_array($guestDecks) ? $guestDecks : [], is_array($guestFollows) ? $guestFollows : []);

        if ($request->expectsJson()) {
            $this->json([
                'success' => true,
                'redirect' => '/dashboard',
                'user' => $_SESSION['user'],
            ]);
        }

        $this->redirect('/dashboard');
    }

    public function validateRegister(Request $request): void
    {
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');
        $errors = [];

        if ($username !== '' && $this->authService->usernameExists($username)) {
            $errors['username'] = 'Username is already taken.';
        }

        if ($email !== '' && $this->authService->emailExists($email)) {
            $errors['email'] = 'Email is already taken.';
        }

        if ($password !== '' && $passwordConfirmation !== '' && $password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        $this->json([
            'success' => $errors === [],
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    public function guest(Request $request): void
    {
        $_SESSION['user'] = [
            'id' => 0,
            'username' => 'guest#' . random_int(1000, 9999),
            'email' => '',
            'role' => 'GUEST',
            'is_guest' => true,
        ];

        $this->redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        $user = $this->currentUser();
        if ($user !== null && $this->isGuest($user)) {
            unset($_SESSION['guest_decks'], $_SESSION['guest_followed_decks'], $_SESSION['guest_study_summaries']);
        }

        unset($_SESSION['user']);
        $this->redirect('/login');
    }

    private function isGuest(array $user): bool
    {
        return ($user['is_guest'] ?? false) === true || ($user['role'] ?? '') === 'GUEST';
    }

    private function migrateGuestData(int $userId, array $guestDecks, array $guestFollows): void
    {
        foreach ($guestDecks as $deck) {
            if (!is_array($deck)) {
                continue;
            }

            $deckId = $this->decks->create([
                'user_id' => $userId,
                'name' => (string) ($deck['name'] ?? 'Guest Deck'),
                'description' => ($deck['description'] ?? '') === '' ? null : (string) $deck['description'],
                'deck_type' => (string) ($deck['deck_type'] ?? 'general'),
                'source_language' => (string) ($deck['source_language'] ?? 'General'),
                'target_language' => ($deck['target_language'] ?? '') === '' ? null : (string) $deck['target_language'],
                'category' => ($deck['category'] ?? '') === '' ? null : (string) $deck['category'],
                'background_url' => ($deck['background_url'] ?? '') === '' ? null : (string) $deck['background_url'],
                'is_public' => false,
            ]);

            foreach (($deck['cards'] ?? []) as $card) {
                if (!is_array($card)) {
                    continue;
                }

                $this->cards->create([
                    'deck_id' => $deckId,
                    'front_question' => (string) ($card['front_question'] ?? ''),
                    'example_sentence' => ($card['example_sentence'] ?? '') === '' ? null : (string) $card['example_sentence'],
                    'image_url' => ($card['image_url'] ?? '') === '' ? null : (string) $card['image_url'],
                    'answer' => (string) ($card['answer'] ?? ''),
                    'translated_example' => ($card['translated_example'] ?? '') === '' ? null : (string) $card['translated_example'],
                ]);
            }
        }

        foreach (array_unique(array_map('intval', $guestFollows)) as $deckId) {
            if ($deckId > 0) {
                $this->decks->followDeck($userId, $deckId);
            }
        }

        unset($_SESSION['guest_decks'], $_SESSION['guest_followed_decks']);
    }
}
