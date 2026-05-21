<?php

declare(strict_types=1);

namespace FlashMind\Controller;

use FlashMind\Http\Request;

final class HomeController extends BaseController
{
    public function index(Request $request): void
    {
        if ($this->currentUser() !== null) {
            $this->redirect('/dashboard');
        }

        $this->render('home/index', [
            'title' => 'FlashMind',
        ]);
    }
}