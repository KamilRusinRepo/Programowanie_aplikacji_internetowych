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

        $this->render('dashboard/index', [
            'title' => 'Dashboard',
            'user' => $userModel === null ? [] : [
                'username' => $userModel->username,
                'email' => $userModel->email,
                'roleName' => $userModel->roleName ?? 'USER',
            ],
        ]);
    }
}