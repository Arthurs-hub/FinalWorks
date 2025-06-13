<?php

namespace App\Services;

use RuntimeException;

class AuthService
{
    /**
     * Получает ID текущего авторизованного пользователя
     * @throws RuntimeException если пользователь не авторизован
     */
    public function getCurrentUserId(): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            throw new RuntimeException('Пользователь не авторизован');
        }

        return (int)$_SESSION['user_id'];
    }
}
