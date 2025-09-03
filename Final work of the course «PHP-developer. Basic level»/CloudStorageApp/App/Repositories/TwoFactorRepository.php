<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;
use Exception;

class TwoFactorRepository extends Repository implements ITwofactorRepository
{
    public function getUserTwoFactorSettings(int $userId): ?array
    {
        return $this->fetchOne(
            "SELECT two_factor_enabled, two_factor_method, two_factor_secret, 
                    two_factor_backup_codes, two_factor_setup_completed 
             FROM users WHERE id = ?",
            [$userId]
        );
    }

    public function updateUserTwoFactorSettings(int $userId, array $settings): bool
    {
        $fields = [];
        $values = [];

        foreach ($settings as $field => $value) {
            $fields[] = "{$field} = ?";
            $values[] = $value;
        }

        $values[] = $userId;

        return $this->execute(
            "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );
    }

    public function enableTwoFactor(int $userId, string $method, string $secret = null): bool
    {
        return $this->execute(
            "UPDATE users SET 
                two_factor_enabled = 1, 
                two_factor_method = ?, 
                two_factor_secret = ?,
                two_factor_setup_completed = 0
             WHERE id = ?",
            [$method, $secret, $userId]
        );
    }

    public function completeTwoFactorSetup(int $userId, array $backupCodes = []): bool
    {
        return $this->execute(
            "UPDATE users SET 
                two_factor_setup_completed = 1,
                two_factor_backup_codes = ?
             WHERE id = ?",
            [json_encode($backupCodes), $userId]
        );
    }

    public function disableTwoFactor(int $userId): bool
    {
        return $this->execute(
            "UPDATE users SET 
                two_factor_enabled = 0, 
                two_factor_method = 'email',
                two_factor_secret = NULL,
                two_factor_backup_codes = NULL,
                two_factor_setup_completed = 0
             WHERE id = ?",
            [$userId]
        );
    }

    /**
     * Сохраняет временный код 2FA
     */
    public function saveTwoFactorCode(int $userId, string $code, string $type, int $expiresInMinutes = 10): bool
    {
        $minutes = max(1, (int) $expiresInMinutes);

        $sql = "INSERT INTO two_factor_codes (user_id, code, type, expires_at, used, created_at)
                VALUES (:user_id, :code, :type, DATE_ADD(NOW(), INTERVAL $minutes MINUTE), 0, NOW())";

        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'code' => $code,
            'type' => $type,
        ]);
    }

    public function verifyAndUseTwoFactorCode(int $userId, string $code, string $type): bool
    {
        try {
            $codeData = $this->fetchOne(
                "SELECT id FROM two_factor_codes
             WHERE user_id = ? AND code = ? AND type = ?
               AND expires_at > NOW() AND used = 0",
                [$userId, $code, $type]
            );

            if (!$codeData) {
                return false;
            }

            $this->execute(
                "UPDATE two_factor_codes SET used = 1 WHERE id = ?",
                [$codeData['id']]
            );

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    public function cleanupExpiredCodes(): int
    {
        $stmt = $this->db->getConnection()->prepare("DELETE FROM two_factor_codes WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function getSystemSetting(string $key): ?string
    {
        $result = $this->fetchOne(
            "SELECT setting_value FROM system_settings WHERE setting_key = ?",
            [$key]
        );

        return $result ? $result['setting_value'] : null;
    }

    public function updateSystemSetting(string $key, string $value): bool
    {
        return $this->execute(
            "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?",
            [$value, $key]
        );
    }

    public function isForcedTwoFactorEnabled(): bool
    {
        $setting = $this->getSystemSetting('force_two_factor_auth');
        return $setting === '1';
    }

    public function logTwoFactorAction(int $userId, string $action, string $method, array $details = []): bool
    {
        return $this->execute(
            "INSERT INTO two_factor_logs (user_id, action, method, ip_address, user_agent, details) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $action,
                $method,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                json_encode($details)
            ]
        );
    }

    public function getTwoFactorStats(): array
    {
        $stats = [];

        $result = $this->fetchOne("SELECT COUNT(*) as count FROM users WHERE two_factor_enabled = 1");
        $stats['enabled_users'] = $result['count'] ?? 0;

        $methods = $this->fetchAll(
            "SELECT two_factor_method, COUNT(*) as count 
             FROM users WHERE two_factor_enabled = 1 
             GROUP BY two_factor_method"
        );

        $stats['methods'] = [];
        foreach ($methods as $method) {
            $stats['methods'][$method['two_factor_method']] = $method['count'];
        }

        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM two_factor_logs 
             WHERE action = 'login_attempt' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stats['login_attempts_24h'] = $result['count'] ?? 0;

        return $stats;
    }

    public function updateBackupCodes(int $userId, array $backupCodes): bool
    {
        return $this->execute(
            "UPDATE users SET two_factor_backup_codes = ? WHERE id = ?",
            [json_encode($backupCodes), $userId]
        );
    }

    public function getBackupCodes(int $userId): array
    {
        $result = $this->fetchOne(
            "SELECT two_factor_backup_codes FROM users WHERE id = ?",
            [$userId]
        );

        if (!$result || !$result['two_factor_backup_codes']) {
            return [];
        }

        return json_decode($result['two_factor_backup_codes'], true) ?: [];
    }
}
