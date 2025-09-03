<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\IUserService;
use App\Core\Logger;

class AuthMiddleware
{
    private static ?IUserService $userService = null;

    public static function init(): void
    {
        // No-op: user service should be injected via setUserService() during bootstrap
    }

    public static function setUserService(IUserService $userService): void
    {
        self::$userService = $userService;
    }

    public function handle(): array
    {
        if (!self::isAuthenticated()) {
            return [
                'success' => false,
                'error' => 'Пользователь не авторизован'
            ];
        }

        return ['success' => true];
    }

    public static function getCurrentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function isAdmin(): bool
    {
        if (!self::isAuthenticated()) {
            return false;
        }

        if (!isset($_SESSION['is_admin'])) {
            if (self::$userService !== null) {
                $_SESSION['is_admin'] = self::$userService->isAdmin($_SESSION['user_id']);
            } else {
                $_SESSION['is_admin'] = false;
            }
        }

        return !empty($_SESSION['is_admin']);
    }

    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Пользователь не авторизован']);
            exit;
        }
    }

    public function isPublicRoute(string $path): bool
    {
        $publicRoutes = [
            '/login',
            '/register',
            '/password-reset-public',
            '/login.html',
            '/register.html',
            '/reset-password.html',
        ];

        foreach ($publicRoutes as $route) {
            if (strpos($path, $route) === 0) {
                return true;
            }
        }

        return false;
    }

    public function isAdminRoute(string $path): bool
    {
        $adminRoutes = [
            '/admin/',
        ];

        foreach ($adminRoutes as $route) {
            if (strpos($path, $route) === 0) {
                return true;
            }
        }

        return false;
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();

        if (!self::isAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Недостаточно прав']);
            exit;
        }
    }

    public static function getCurrentUser(): ?array
    {
        $userId = self::getCurrentUserId();
        if (!$userId) {
            return null;
        }

        if (self::$userService === null) {
            return null;
        }

        return self::$userService->findUserById($userId);
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = self::getCurrentUserId();

        Logger::info("User logged out", ['user_id' => $userId]);

        $_SESSION = [];

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }

        session_destroy();
    }

    public static function login(int $userId): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $userId;

        if (self::$userService !== null) {
            $_SESSION['is_admin'] = self::$userService->isAdmin($userId);
        }

        session_regenerate_id(true);
    }
}
