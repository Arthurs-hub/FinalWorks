<?php

namespace App\Core;

use Exception;

abstract class BaseController
{
    protected function handleError(string $method, Exception $e, string $userMessage): Response
    {
        Logger::error(static::class . "::{$method} error", [
            'error' => $e->getMessage(),
            'admin_id' => AuthMiddleware::getCurrentUserId(),
        ]);

        return new Response([
            'success' => false,
            'error' => $userMessage,
        ], 500);
    }

    protected function successResponse(array $data = []): Response
    {
        return new Response(array_merge(['success' => true], $data));
    }

    protected function errorResponse(string $message, int $code = 400): Response
    {
        return new Response(['success' => false, 'error' => $message], $code);
    }
}
