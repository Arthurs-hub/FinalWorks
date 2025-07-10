<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct(AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function login(Request $request): Response
    {
        return $this->executeWithAuth(function() use ($request) {
            return $this->handleServiceResult($this->authService->login($request->getData()));
        }, false);
    }

    public function logout(): Response
    {
        return $this->executeWithAuth(function() {
            return $this->handleServiceResult($this->authService->logout());
        }, false);
    }
}
