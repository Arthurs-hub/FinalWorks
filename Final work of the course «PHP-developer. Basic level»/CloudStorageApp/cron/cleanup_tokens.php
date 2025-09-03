<?php

namespace App\Cron;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../Repositories/PasswordResetRepository.php';

use App\Repositories\PasswordResetRepository;
use App\Core\Logger;
use Exception;

try {
    $repository = new PasswordResetRepository($db);
    $deletedCount = $repository->cleanupExpiredTokens();

    Logger::info("Cleanup expired tokens", [
        'deleted_count' => $deletedCount
    ]);

    echo "Удалено просроченных токенов: $deletedCount\n";
} catch (Exception $e) {
    Logger::error("Token cleanup error", [
        'error' => $e->getMessage()
    ]);

    echo "Ошибка при очистке токенов: " . $e->getMessage() . "\n";
}
