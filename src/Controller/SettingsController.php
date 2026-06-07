<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Repository\UserRepository;
use FlashMind\Http\Request;

final class SettingsController extends BaseController
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    public function index(Request $request): void
    {
        $this->requireAccount();

        $this->renderSettings();
    }

    public function updateProfile(Request $request): void
    {
        $this->requireAccount();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $userId = (int) $user['id'];
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $errors = $this->validateProfile($username, $email, $userId);

        if ($errors !== []) {
            $this->renderSettings($errors, [], [
                'username' => $username,
                'email' => $email,
            ]);
            return;
        }

        $this->users->updateAdminUser($userId, $username, $email, null);
        $_SESSION['user']['username'] = $username;
        $_SESSION['user']['email'] = $email;

        $this->redirect('/settings');
    }

    public function updatePassword(Request $request): void
    {
        $this->requireAccount();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $userId = (int) $user['id'];
        $userModel = $this->users->findById($userId);
        if ($userModel === null) {
            $this->redirect('/login');
        }

        $currentPassword = (string) $request->input('current_password', '');
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');
        $errors = [];

        if ($currentPassword === '') {
            $errors['current_password'] = 'Obecne hasło jest wymagane.';
        } elseif (!password_verify($currentPassword, $userModel->passwordHash)) {
            $errors['current_password'] = 'Obecne hasło jest niepoprawne.';
        }

        if ($password === '') {
            $errors['password'] = 'Nowe hasło jest wymagane.';
        } elseif (mb_strlen($password) < 8) {
            $errors['password'] = 'Hasło musi mieć co najmniej 8 znaków.';
        }

        if ($passwordConfirmation === '') {
            $errors['password_confirmation'] = 'Powtórz nowe hasło.';
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Hasła nie są takie same.';
        }

        if ($errors !== []) {
            $this->renderSettings([], $errors, [], true);
            return;
        }

        $this->users->updateAdminUser($userId, $userModel->username, $userModel->email, password_hash($password, PASSWORD_DEFAULT));
        $this->redirect('/settings');
    }

    private function renderSettings(
        array $profileErrors = [],
        array $passwordErrors = [],
        array $old = [],
        bool $passwordModalOpen = false
    ): void {
        $user = $this->currentUser();
        $userModel = $user !== null ? $this->users->findById((int) $user['id']) : null;
        $username = (string) ($old['username'] ?? ($userModel?->username ?? ($user['username'] ?? 'Alex')));
        $email = (string) ($old['email'] ?? ($userModel?->email ?? ($user['email'] ?? '')));
        $displayName = trim((string) preg_replace('/\s+/', ' ', $username));
        $displayName = $displayName === '' ? 'Alex' : $displayName;
        $initials = strtoupper(substr($displayName, 0, 1));

        $this->render('settings/index', [
            'title' => 'Settings',
            'displayName' => $displayName,
            'userInitials' => $initials,
            'nav' => [
                'dashboard' => '',
                'decks' => '',
                'explore' => '',
                'stats' => '',
                'settings' => 'is-active',
            ],
            'user' => $userModel === null ? [] : [
                'displayName' => $displayName,
                'username' => $username,
                'email' => $email,
                'roleName' => $userModel->roleName ?? 'USER',
            ],
            'profileErrors' => $profileErrors,
            'passwordErrors' => $passwordErrors,
            'settings' => [
                'passwordModalOpen' => $passwordModalOpen ? 'true' : 'false',
                'passwordChangedText' => $this->passwordChangedText($userModel?->passwordChangedAt ?? $userModel?->createdAt),
            ],
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/settings.css?v=3">',
                'extraJs' => '<script defer src="/scripts/settings.js?v=3"></script>',
            ],
        ], 'layout/dashboard');
    }

    private function validateProfile(string $username, string $email, int $userId): array
    {
        $errors = [];

        if ($username === '') {
            $errors['username'] = 'Nazwa użytkownika jest wymagana.';
        } elseif (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            $errors['username'] = 'Nazwa użytkownika musi mieć od 3 do 50 znaków.';
        } elseif ($this->users->usernameExistsForAnotherUser($username, $userId)) {
            $errors['username'] = 'Ta nazwa użytkownika jest już zajęta.';
        }

        if ($email === '') {
            $errors['email'] = 'Email jest wymagany.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email ma niepoprawny format.';
        } elseif ($this->users->emailExistsForAnotherUser($email, $userId)) {
            $errors['email'] = 'Ten email jest już zajęty.';
        }

        return $errors;
    }

    private function passwordChangedText(?string $date): string
    {
        if ($date === null || trim($date) === '') {
            return 'Last changed date is not available';
        }

        try {
            $changedAt = new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return 'Last changed date is not available';
        }

        $today = new \DateTimeImmutable('today');
        $changedDay = $changedAt->setTime(0, 0);
        $days = (int) $changedDay->diff($today)->format('%r%a');

        if ($days <= 0) {
            return 'Last changed today';
        }

        if ($days === 1) {
            return 'Last changed yesterday';
        }

        if ($days < 31) {
            return 'Last changed ' . $days . ' days ago';
        }

        return 'Last changed ' . $changedAt->format('Y-m-d');
    }
}
