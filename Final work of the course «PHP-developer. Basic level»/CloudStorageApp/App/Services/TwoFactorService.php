<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use Exception;

class TwoFactorService implements ITwoFactorService
{
    private const TOTP_PERIOD = 30; // 30 секунд
    private const TOTP_DIGITS = 6;  // 6 цифр
    private const BACKUP_CODES_COUNT = 10;

    public function generateSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $secret;
    }

    public function generateQRCodeUrl(string $secret, string $email, string $issuer = 'Cloud Storage'): string
    {
        $label = urlencode($issuer . ':' . $email);
        $issuer = urlencode($issuer);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&digits=" . self::TOTP_DIGITS . "&period=" . self::TOTP_PERIOD;
    }

    public function generateTOTP(string $secret, int $timestamp = null): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $timeSlice = intval($timestamp / self::TOTP_PERIOD);
        $secretKey = $this->base32Decode($secret);

        $time = pack('N*', 0) . pack('N*', $timeSlice);

        $hash = hash_hmac('sha1', $time, $secretKey, true);

        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, self::TOTP_DIGITS);

        return str_pad((string) $code, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    public function verifyTOTP(string $secret, string $code, int $window = 1): bool
    {
        $timestamp = time();

        for ($i = -$window; $i <= $window; $i++) {
            $testTime = $timestamp + ($i * self::TOTP_PERIOD);
            $expectedCode = $this->generateTOTP($secret, $testTime);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    public function generateBackupCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::BACKUP_CODES_COUNT; $i++) {
            $codes[] = $this->generateRandomCode(8);
        }

        return $codes;
    }

    public function generateEmailCode(): string
    {
        return $this->generateRandomCode(6);
    }

    public function verifyBackupCode(array $backupCodes, string $code): bool
    {
        return in_array($code, $backupCodes, true);
    }

    public function removeUsedBackupCode(array $backupCodes, string $usedCode): array
    {
        return array_values(array_filter($backupCodes, function ($code) use ($usedCode) {
            return $code !== $usedCode;
        }));
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $map = array_flip(str_split($alphabet));

        $secret = strtoupper(str_replace('=', '', $secret));

        $bits = '';
        foreach (str_split($secret) as $char) {
            if (!isset($map[$char])) {
                return '';
            }
            $bits .= str_pad(decbin($map[$char]), 5, '0', STR_PAD_LEFT);
        }

        $output = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $byte = substr($bits, $i, 8);
            $output .= chr(bindec($byte));
        }

        return $output;
    }

    private function generateRandomCode(int $length): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }

    public function generateQRCodeImage(string $qrUrl): string
    {
        $size = '200x200';
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}&data=" . urlencode($qrUrl);

        return $qrCodeUrl;
    }

    public function isForced2FARequired(): bool
    {
        return false;
    }

    public function logAction(int $userId, string $action, string $method, array $details = []): void
    {
        try {
            Logger::info("2FA Action: {$action}", [
                'user_id' => $userId,
                'method' => $method,
                'action' => $action,
                'details' => $details,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {

        }
    }
}
