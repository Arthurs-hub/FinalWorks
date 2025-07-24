<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\UserService;
use App\Core\AuthMiddleware;

class AuthController extends BaseController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function login(Request $request): Response
    {
        $data = $request->getData();
        
        if (!$data) {
            return new Response(['success' => false, 'error' => 'Некорректные данные'], 400);
        }

        $result = $this->userService->login($data);
        
        if ($result['success']) {
            AuthMiddleware::login($result['user']['id']);
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

        $result = $this->userService->resetPassword($data['email']);
        return new Response($result, $result['success'] ? 200 : 400);
    }
}
