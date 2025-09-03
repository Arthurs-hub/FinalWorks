<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AuthMiddleware;
use App\Core\Logger;
use App\Validators\AuthValidator;
use Exception;

class AuthService
{
    private IUserService $userService;
    private AuthValidator $validator;

    public function __construct(IUserService $userService, AuthValidator $validator)
    {
        $this->userService = $userService;
        $this->validator = $validator;
    }

    public function authenticate(string $email, string $password): array
    {
        $user = $this->userService->authenticateUser($email, $password);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Неверный email или пароль',
            ];
        }

        if ($user['is_banned']) {
            return [
                'success' => false,
                'message' => 'Аккаунт заблокирован',
            ];
        }

        AuthMiddleware::login($user['id']);

        return [
            'success' => true,
            'user' => $user,
        ];
    }

    public function logout(): array
    {
        try {
            $userId = AuthMiddleware::getCurrentUserId();
            AuthMiddleware::logout();

            if ($userId) {
                Logger::info('User logged out', ['user_id' => $userId]);
            }

            return ['success' => true];
        } catch (Exception $e) {
            Logger::error('Logout error', [
                'error' => $e->getMessage(),
                'user_id' => AuthMiddleware::getCurrentUserId(),
            ]);

            return ['success' => false, 'error' => 'Ошибка при выходе из системы'];
        }
    }

    public function login(array $data): array
    {
        try {
            $validationResult = $this->validator->validateLoginData($data);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'error' => $validationResult['message'],
                ];
            }

            $email = $data['email'];
            $password = $data['password'];

            $authResult = $this->authenticate($email, $password);

            if (!$authResult['success']) {
                return [
                    'success' => false,
                    'error' => $authResult['message'],
                ];
            }

            Logger::info('User logged in', [
                'user_id' => $authResult['user']['id'],
                'email' => $email,
            ]);

            return [
                'success' => true,
                'user' => $authResult['user'],
            ];
        } catch (Exception $e) {
            Logger::error('Login error', [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? 'unknown',
            ]);

            return [
                'success' => false,
                'error' => 'Ошибка при входе в систему',
            ];
        }
    }
}
