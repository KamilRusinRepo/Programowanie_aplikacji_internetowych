<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Repository\UserRepository;
use FlashMind\Http\Request;

final class DashboardController extends BaseController
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
        $displayName = $displayName === '' ? 'Alex' : explode(' ', $displayName)[0];
        $initials = strtoupper(substr($displayName, 0, 1));

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'displayName' => $displayName,
            'userInitials' => $initials,
            'user' => $userModel === null ? [] : [
                'displayName' => $displayName,
                'username' => $userModel->username,
                'email' => $userModel->email,
                'roleName' => $userModel->roleName ?? 'USER',
            ],
            'stats' => [
                'streak' => 5,
                'dueCards' => '12/20 cards',
                'level' => 4,
                'xp' => '850/1000 XP',
                'progress' => 85,
            ],
        ], 'layout/dashboard');
    }
}