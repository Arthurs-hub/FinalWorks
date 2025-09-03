<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\IUserService;
use App\Core\AuthMiddleware;

class AuthController extends BaseController
{
    private IUserService $userService;

    public function __construct(IUserService $userService)
    {
        $this->userService = $userService;
    }

    public function login(Request $request): Response
    {
        $data = $request->getData();

        if (!$data) {
            return new Response(['success' => false, 'error' => 'Некорректные данные'], 400);
        }

        $result = $this->userService->login($data);

        if ($result['success']) {
            if (!empty($result['requires_2fa_verification'])) {
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['2fa_pending'] = true;
                $_SESSION['user_id_for_2fa'] = $result['user']['id'];
            } else {
                AuthMiddleware::login($result['user']['id']);
            }
        }

        return new Response($result, $result['success'] ? 200 : 401);
    }

    public function logout(Request $request): Response
    {
        AuthMiddleware::logout();
        return new Response(['success' => true, 'message' => 'Успешный выход']);
    }

    public function register(Request $request): Response
    {
        $data = $request->getData();

        if (!$data) {
            return new Response(['success' => false, 'error' => 'Некорректные данные'], 400);
        }

        $result = $this->userService->register($data);
        return new Response($result, $result['success'] ? 201 : 400);
    }

    public function resetPassword(Request $request): Response
    {
        $data = $request->getData();

        if (!$data || !isset($data['email'])) {
            return new Response(['success' => false, 'error' => 'Некорректные данные'], 400);
        }

        $result = $this->userService->requestPasswordReset($data);
        return new Response($result, $result['success'] ? 200 : 400);
    }

    public function twoFactorAuth(Request $request): Response
    {
        $data = $request->getData();

        if (!$data || !isset($data['code'])) {
            return new Response(['success' => false, 'error' => 'Некорректные данные'], 400);
        }

        $isValid = $this->userService->validateTwoFactorCode($data['code']);

        if ($isValid) {
            unset($_SESSION['pending_2fa_secret']);
            return new Response(['success' => true, 'message' => 'Двухфакторная аутентификация пройдена']);
        } else {
            return new Response(['success' => false, 'error' => 'Неверный код двухфакторной аутентификации'], 401);
        }
    }
}
