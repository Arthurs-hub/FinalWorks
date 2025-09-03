<?php

declare(strict_types=1);

namespace App\Repositories;

interface ITwoFactorRepository
{
    public function getUserTwoFactorSettings(int $userId): ?array;
    public function updateUserTwoFactorSettings(int $userId, array $settings): bool;
    public function completeTwoFactorSetup(int $userId, array $backupCodes = []): bool;
    public function saveTwoFactorCode(int $userId, string $code, string $type, int $expiresInMinutes = 10): bool;
    public function verifyAndUseTwoFactorCode(int $userId, string $code, string $type): bool;
    public function getBackupCodes(int $userId): array;
    public function updateBackupCodes(int $userId, array $backupCodes): bool;
    public function isForcedTwoFactorEnabled(): bool;
    public function getTwoFactorStats(): array;
    public function updateSystemSetting(string $key, string $value): bool;
    public function logTwoFactorAction(int $userId, string $action, string $method, array $details = []): bool;
}
