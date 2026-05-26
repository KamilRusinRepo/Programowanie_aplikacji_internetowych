<?php

declare(strict_types=1);

namespace FlashMind\Service;

use FlashMind\Repository\RoleRepository;
use FlashMind\Repository\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
    ) {
    }

    public function register(array $input): array
    {
        $username = trim((string) ($input['username'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? '');
        $errors = [];

        if ($username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
            $errors['username'] = 'Username must be between 3 and 50 characters.';
        }

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email is not valid.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (mb_strlen($password) < 8) {
            $errors['password'] = 'Password must have at least 8 characters.';
        }

        if ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        if ($username !== '' && $this->users->findByUsername($username) !== null) {
            $errors['username'] = 'Username is already taken.';
        }

        if ($email !== '' && $this->users->findByEmail($email) !== null) {
            $errors['email'] = 'Email is already taken.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userId = $this->users->create($username, $email, $passwordHash);
        $roleName = $this->users->countAll() === 1 ? 'ADMIN' : 'USER';
        $role = $this->roles->findByName($roleName);

        if ($role === null) {
            return [
                'success' => false,
                'errors' => ['general' => 'Role configuration is missing.'],
            ];
        }

        $this->users->assignRole($userId, $role->id);

        $user = $this->users->findById($userId);

        return [
            'success' => true,
            'errors' => [],
            'user' => $user,
        ];
    }

    public function login(array $input): array
    {
        $login = trim((string) ($input['login'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $errors = [];

        if ($login === '') {
            $errors['login'] = 'Email or username is required.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $user = $this->users->findByLogin($login);

        if ($user === null) {
            return [
                'success' => false,
                'errors' => ['login' => 'Invalid credentials.'],
            ];
        }

        if (!$user->isEnabled) {
            return [
                'success' => false,
                'errors' => ['login' => 'Account is blocked.'],
            ];
        }

        if (!password_verify($password, $user->passwordHash)) {
            return [
                'success' => false,
                'errors' => ['password' => 'Invalid credentials.'],
            ];
        }

        return [
            'success' => true,
            'errors' => [],
            'user' => $user,
        ];
    }
}