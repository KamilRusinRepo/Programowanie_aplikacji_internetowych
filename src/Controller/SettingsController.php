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
        $this->requireAuth();

        $user = $this->currentUser();
        $userModel = $user !== null ? $this->users->findById((int) $user['id']) : null;
        $username = $userModel?->username ?? ($user['username'] ?? 'Alex');
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
                'username' => $userModel->username,
                'email' => $userModel->email,
                'roleName' => $userModel->roleName ?? 'USER',
            ],
        ], 'layout/dashboard');
    }
}
