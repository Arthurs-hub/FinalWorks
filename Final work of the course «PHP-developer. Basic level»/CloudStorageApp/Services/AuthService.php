<?php

namespace App\Services;

use App\Core\AuthMiddleware;

class AuthService
{
    private UserService $userService;

    public function __construct(UserService $userService = null)
    {
        $this->userService = $userService ?? new UserService();
    }

    public function authenticate(string $email, string $password): array
    {
        $user = $this->userService->authenticateUser($email, $password);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Неверный email или пароль'
            ];
        }

        if ($user['is_banned']) {
            return [
                'success' => false,
                'message' => 'Аккаунт заблокирован'
            ];
        }

        AuthMiddleware::login($user['id']);

        return [
            'success' => true,
            'user' => $user
        ];
    }

    public function logout(): void
    {
        AuthMiddleware::logout();
    }
}
