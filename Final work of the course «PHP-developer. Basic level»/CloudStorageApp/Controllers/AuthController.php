<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\AuthMiddleware;
use App\Core\Logger;
use App\Services\AuthService;
use App\Validators\AuthValidator;
use Exception;

class AuthController
{
    private AuthService $authService;
    private AuthValidator $validator;

    public function __construct(
        AuthService $authService = null,
        AuthValidator $validator = null
    ) {
        $this->authService = $authService ?? new AuthService();
        $this->validator = $validator ?? new AuthValidator();
    }

    public function login(Request $request): Response
    {
        try {
            $data = $request->getData();

            $validationResult = $this->validator->validateLoginData($data);
            if (!$validationResult['valid']) {
                return new Response([
                    'success' => false,
                    'error' => $validationResult['message']
                ], 400);
            }

            $email = $data['email'];
            $password = $data['password'];

            $authResult = $this->authService->authenticate($email, $password);

            if (!$authResult['success']) {
                return new Response([
                    'success' => false,
                    'error' => $authResult['message']
                ], 401);
            }

            Logger::info('User logged in', [
                'user_id' => $authResult['user']['id'],
                'email' => $email
            ]);

            return new Response([
                'success' => true,
                'user' => $authResult['user']
            ]);
        } catch (Exception $e) {
            Logger::error('Login error', [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? 'unknown'
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при входе в систему'
            ], 500);
        }
    }

    public function logout(): Response
    {
        try {
            $userId = AuthMiddleware::getCurrentUserId();

            $this->authService->logout();

            if ($userId) {
                Logger::info('User logged out', ['user_id' => $userId]);
            }

            return new Response(['success' => true]);
        } catch (Exception $e) {
            Logger::error('Logout error', [
                'error' => $e->getMessage(),
                'user_id' => AuthMiddleware::getCurrentUserId()
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка при выходе из системы'
            ], 500);
        }
    }
}
