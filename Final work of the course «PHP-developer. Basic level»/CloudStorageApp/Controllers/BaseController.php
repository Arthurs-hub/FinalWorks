<?php

namespace App\Controllers;

use App\Core\AuthMiddleware;
use App\Core\Logger;
use App\Core\Response;
use Exception;

abstract class BaseController
{
    protected function executeWithAuth(callable $action, bool $requireAuth = true, bool $requireAdmin = false): Response
    {
        try {
            if ($requireAuth && !isset($_SESSION['user_id'])) {
                return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
            }

            if ($requireAdmin) {
                AuthMiddleware::requireAdmin();
            }

            return $action();
        } catch (Exception $e) {
            Logger::error(get_class($this) . ' error', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null,
            ]);

            return new Response([
                'success' => false,
                'error' => 'Ошибка сервера',
            ], 500);
        }
    }

    protected function handleServiceResult(array $result): Response
    {
        $statusCode = $result['success'] ? 200 : ($result['status_code'] ?? 400);
        return new Response($result, $statusCode);
    }

    protected function getCurrentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }
}
