<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\UserService;

class UserController extends BaseController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function register(Request $request): Response
    {
        return $this->executeWithAuth(function() use ($request) {
            return $this->handleServiceResult($this->userService->register($request->getData()));
        }, false);
    }

    public function login(Request $request): Response
    {
        return $this->executeWithAuth(function() use ($request) {
            return $this->handleServiceResult($this->userService->login($request->getData()));
        }, false);
    }

    public function getCurrentUser(): Response
    {
        return $this->executeWithAuth(function() {
            $userId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->userService->getCurrentUser($userId));
        });
    }

    public function get(Request $request): Response
    {
        return $this->executeWithAuth(function() use ($request) {
            $userId = $request->routeParams['id'] ?? $this->getCurrentUserId();
            return $this->handleServiceResult($this->userService->get($userId));
        });
    }

    public function list(Request $request): Response
    {
        return $this->executeWithAuth(function() {
            return $this->handleServiceResult($this->userService->list());
        });
    }

    public function update(Request $request): Response
    {
        return $this->executeWithAuth(function() use ($request) {
            $userId = $request->routeParams['id'] ?? $this->getCurrentUserId();
            $currentUserId = $this->getCurrentUserId();
            $data = $request->getData();
            return $this->handleServiceResult($this->userService->update($userId, $data, $currentUserId));
        });
    }

    public function changePassword(Request $request): Response
    {
        return $this->executeWithAuth(function() use ($request) {
            $userId = $this->getCurrentUserId();
            $data = $request->getData();
            return $this->handleServiceResult($this->userService->changeUserPassword($userId, $data));
        });
    }

    public function logout(): Response
    {
        return $this->executeWithAuth(function() {
            return $this->handleServiceResult($this->userService->logout());
        }, false);
    }

    public function publicPasswordReset(Request $request): Response
    {
        return $this->executeWithAuth(function() use ($request) {
            return $this->handleServiceResult($this->userService->publicPasswordReset($request->getData()));
        }, false);
    }

    public function getUserStats(Request $request): Response
    {
        return $this->executeWithAuth(function() use ($request) {
            $userId = $request->routeParams['id'] ?? $this->getCurrentUserId();
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->userService->getUserStatsWithAuth($userId, $currentUserId));
        });
    }

    public function delete(Request $request): Response
    {
        return $this->executeWithAuth(function() use ($request) {
            $userId = $request->routeParams['id'] ?? null;
            $currentUserId = $this->getCurrentUserId();
            return $this->handleServiceResult($this->userService->delete($userId, $currentUserId));
        }, true, true);
    }

    public function hello(): Response
    {
        return $this->executeWithAuth(function() {
            return $this->handleServiceResult(['success' => true, 'message' => 'Hello from UserController!']);
        }, false);
    }
}
