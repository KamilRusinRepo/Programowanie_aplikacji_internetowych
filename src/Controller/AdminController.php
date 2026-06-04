<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Http\Request;
use FlashMind\Repository\UserRepository;

final class AdminController extends BaseController
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    public function index(Request $request): void
    {
        $this->requireAdmin();

        $search = trim((string) $request->input('q', ''));
        $role = trim((string) $request->input('role', ''));
        $status = trim((string) $request->input('status', ''));

        $this->renderIndex($search, $role, $status);
    }

    public function createUser(Request $request): void
    {
        $this->requireAdmin();

        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $role = strtoupper(trim((string) $request->input('role', 'USER')));

        $errors = $this->validateUserInput($username, $email, $password, 0, true);

        if ($errors !== []) {
            $this->renderIndex('', '', '', $errors, [
                'mode' => 'add',
                'username' => $username,
                'email' => $email,
                'role' => $role === 'ADMIN' ? 'ADMIN' : 'USER',
                'errors' => $errors,
            ]);
            return;
        }

        $userId = $this->users->create($username, $email, password_hash($password, PASSWORD_DEFAULT));
        $this->users->setRole($userId, $role === 'ADMIN' ? 'ADMIN' : 'USER');

        $this->redirect('/admin');
    }

    public function toggleUser(Request $request, string $userId): void
    {
        $this->requireAdmin();

        $current = $this->currentUser();
        $targetId = (int) $userId;
        if ($current !== null && $targetId !== (int) $current['id']) {
            $enabled = $request->input('enabled') === '1';
            $this->users->setEnabled($targetId, $enabled);
        }

        $this->redirect('/admin');
    }

    public function updateUser(Request $request, string $userId): void
    {
        $this->requireAdmin();

        $targetId = (int) $userId;
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $role = strtoupper((string) $request->input('role', 'USER'));

        $errors = $this->validateUserInput($username, $email, $password, $targetId, false);

        if ($targetId <= 0 || $errors !== []) {
            if ($targetId <= 0 && $errors === []) {
                $errors['general'] = 'Nie można edytować tego użytkownika.';
            }

            $this->renderIndex('', '', '', $errors, [
                'mode' => 'edit',
                'id' => $targetId,
                'username' => $username,
                'email' => $email,
                'role' => $role === 'ADMIN' ? 'ADMIN' : 'USER',
                'errors' => $errors,
            ]);
            return;
        }

        $passwordHash = $password === '' ? null : password_hash($password, PASSWORD_DEFAULT);
        $this->users->updateAdminUser($targetId, $username, $email, $passwordHash);
        $this->users->setRole($targetId, $role === 'ADMIN' ? 'ADMIN' : 'USER');

        $this->redirect('/admin');
    }

    public function validateUser(Request $request): void
    {
        $this->requireAdmin();

        $targetId = (int) $request->input('id', 0);
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $errors = [];

        if ($username !== '' && $this->users->usernameExistsForAnotherUser($username, $targetId)) {
            $errors['username'] = 'Ta nazwa użytkownika jest już zajęta.';
        }

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Email ma niepoprawny format.';
            } elseif ($this->users->emailExistsForAnotherUser($email, $targetId)) {
                $errors['email'] = 'Ten email jest już zajęty.';
            }
        }

        $this->json([
            'success' => $errors === [],
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    public function deleteUser(Request $request, string $userId): void
    {
        $this->requireAdmin();

        $current = $this->currentUser();
        $targetId = (int) $userId;
        if ($current !== null && $targetId > 0 && $targetId !== (int) $current['id']) {
            $this->users->deleteById($targetId);
        }

        $this->redirect('/admin');
    }

    public function changeRole(Request $request, string $userId): void
    {
        $this->requireAdmin();

        $role = strtoupper((string) $request->input('role', 'USER'));
        $this->users->setRole((int) $userId, $role === 'ADMIN' ? 'ADMIN' : 'USER');

        $this->redirect('/admin');
    }

    private function renderIndex(
        string $search,
        string $role,
        string $status,
        array $adminErrors = [],
        array $modal = []
    ): void
    {
        $user = $this->currentUser();
        $profile = $this->profileData((int) $user['id'], $user);
        $users = $this->prepareUsers($this->users->findForAdmin($search, $role, $status));

        $this->render('admin/index', [
            'title' => 'Admin Panel',
            'displayName' => $profile['displayName'],
            'userInitials' => $profile['initials'],
            'nav' => [
                'dashboard' => '',
                'decks' => '',
                'explore' => '',
                'stats' => '',
                'admin' => 'is-active',
                'settings' => '',
            ],
            'filters' => [
                'q' => $search,
                'role' => $role,
                'status' => $status,
            ],
            'admin' => [
                'error' => (string) ($adminErrors['general'] ?? ''),
                'empty' => $users === [] ? 'No users match the current filters.' : '',
                'emptyRowClass' => $users === [] ? '' : 'is-hidden',
                'modalStateJson' => $modal === [] ? 'null' : (json_encode($modal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'null'),
            ],
            'raw' => [
                'extraCss' => '<link rel="stylesheet" href="/styles/admin.css?v=3">',
                'extraJs' => '<script defer src="/scripts/admin.js?v=1"></script>',
            ],
            'users' => $users,
        ], 'layout/dashboard');
    }

    private function prepareUsers(array $users): array
    {
        return array_map(static function (array $user): array {
            $id = (int) $user['id'];
            $username = (string) $user['username'];
            $email = (string) $user['email'];
            $role = (string) $user['role_name'];
            $enabled = (bool) $user['is_enabled'];
            $lastActivity = $user['last_activity'] === null ? 'No sessions yet' : date('Y-m-d H:i', strtotime((string) $user['last_activity']));

            return [
                'id' => $id,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'initials' => strtoupper(substr($username, 0, 2)),
                'rowClass' => $enabled ? '' : 'is-disabled',
                'statusClass' => $enabled ? 'is-enabled' : 'is-off',
                'statusLabel' => $enabled ? 'Aktywny' : 'Zablokowany',
                'toggleValue' => $enabled ? '0' : '1',
                'toggleTitle' => $enabled ? 'Block user' : 'Unblock user',
                'toggleIcon' => $enabled ? 'block.svg' : 'unblock.svg',
                'lastActivity' => $lastActivity,
                'xp' => (int) $user['xp_total'],
            ];
        }, $users);
    }

    private function validateUserInput(string $username, string $email, string $password, int $excludedUserId, bool $passwordRequired): array
    {
        $errors = [];

        if ($username === '') {
            $errors['username'] = 'Nazwa użytkownika jest wymagana.';
        } elseif (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            $errors['username'] = 'Nazwa użytkownika musi mieć od 3 do 50 znaków.';
        } elseif ($this->users->usernameExistsForAnotherUser($username, $excludedUserId)) {
            $errors['username'] = 'Ta nazwa użytkownika jest już zajęta.';
        }

        if ($email === '') {
            $errors['email'] = 'Email jest wymagany.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email ma niepoprawny format.';
        } elseif ($this->users->emailExistsForAnotherUser($email, $excludedUserId)) {
            $errors['email'] = 'Ten email jest już zajęty.';
        }

        if ($passwordRequired && $password === '') {
            $errors['password'] = 'Hasło jest wymagane.';
        } elseif ($password !== '' && mb_strlen($password) < 8) {
            $errors['password'] = 'Hasło musi mieć co najmniej 8 znaków.';
        }

        return $errors;
    }

    private function requireAdmin(): void
    {
        $this->requireAuth();

        $user = $this->currentUser();
        $profile = $user === null ? null : $this->users->findById((int) $user['id']);

        if (($profile?->roleName ?? 'USER') !== 'ADMIN') {
            $this->redirect('/dashboard');
        }
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
}
