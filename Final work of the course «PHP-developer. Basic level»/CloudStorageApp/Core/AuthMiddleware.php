<?php

namespace App\Core;

use App\Services\UserService;
use App\Core\Logger;

class AuthMiddleware
{
    private static ?UserService $userService = null;

    public static function init(): void
    {
        if (self::$userService === null) {
            self::$userService = new UserService();
        }
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
            self::init();
            $_SESSION['is_admin'] = self::$userService->isAdmin($_SESSION['user_id']);
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
            '/CloudStorageApp/public/',
            '/CloudStorageApp/public/login.html',
            '/CloudStorageApp/public/register.html',
            '/CloudStorageApp/public/password-reset.html'
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
            '/CloudStorageApp/public/admin/',
            '/CloudStorageApp/admin/'
        ];

        foreach ($adminRoutes as $route) {
            if (strpos($path, $route) !== false) {
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

        self::init();
        return self::$userService->findUserById($userId);
    }

    public static function logout(): void
    {
        $userId = self::getCurrentUserId();

        Logger::info("User logged out", ['user_id' => $userId]);

        $_SESSION = array();

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

        $userService = new UserService();
        $_SESSION['is_admin'] = $userService->isAdmin($userId);

        session_regenerate_id(true);
    }
}
