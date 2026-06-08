<?php

declare(strict_types=1);

namespace FlashMind\Service;

use FlashMind\Repository\RoleRepository;
use FlashMind\Repository\UserRepository;

final class AuthService
{
    private const MAX_LOGIN_LENGTH = 255;
    private const MAX_EMAIL_LENGTH = 255;
    private const MAX_PASSWORD_LENGTH = 255;
    private const MIN_PASSWORD_LENGTH = 8;
    private const MIN_USERNAME_LENGTH = 3;
    private const MAX_USERNAME_LENGTH = 50;

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
        } elseif (mb_strlen($username) < self::MIN_USERNAME_LENGTH || mb_strlen($username) > self::MAX_USERNAME_LENGTH) {
            $errors['username'] = 'Username must be between 3 and 50 characters.';
        }

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (mb_strlen($email) > self::MAX_EMAIL_LENGTH) {
            $errors['email'] = 'Email must have at most 255 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email is not valid.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'Password must have at least 8 characters.';
        } elseif (mb_strlen($password) > self::MAX_PASSWORD_LENGTH) {
            $errors['password'] = 'Password must have at most 255 characters.';
        } elseif (!$this->isStrongPassword($password)) {
            $errors['password'] = 'Password must contain a lowercase letter, uppercase letter, number, and special character.';
        }

        if (mb_strlen($passwordConfirmation) > self::MAX_PASSWORD_LENGTH) {
            $errors['password_confirmation'] = 'Password confirmation must have at most 255 characters.';
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

    private function isStrongPassword(string $password): bool
    {
        return preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^a-zA-Z\d]/', $password) === 1;
    }

    public function login(array $input): array
    {
        $login = trim((string) ($input['login'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $errors = [];

        if ($login === '') {
            $errors['login'] = 'Email or username is required.';
        } elseif (mb_strlen($login) > self::MAX_LOGIN_LENGTH) {
            $errors['login'] = 'Email or username must have at most 255 characters.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (mb_strlen($password) > self::MAX_PASSWORD_LENGTH) {
            $errors['password'] = 'Password must have at most 255 characters.';
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

    public function usernameExists(string $username): bool
    {
        return $this->users->usernameExistsForAnotherUser($username);
    }

    public function emailExists(string $email): bool
    {
        return $this->users->emailExistsForAnotherUser($email);
    }
}
