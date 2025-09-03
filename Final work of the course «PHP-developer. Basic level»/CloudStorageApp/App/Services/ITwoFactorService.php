<?php

declare(strict_types=1);

namespace App\Services;

interface ITwoFactorService
{
    public function generateSecret(): string;
    public function generateQRCodeUrl(string $secret, string $email, string $issuer = 'Cloud Storage'): string;
    public function generateQRCodeImage(string $qrUrl): string;
    public function generateEmailCode(): string;
    public function verifyTOTP(string $secret, string $code, int $window = 1): bool;
    public function generateBackupCodes(): array;
    public function verifyBackupCode(array $backupCodes, string $code): bool;
    public function removeUsedBackupCode(array $backupCodes, string $usedCode): array;
    public function logAction(int $userId, string $action, string $method, array $details = []): void;
}
