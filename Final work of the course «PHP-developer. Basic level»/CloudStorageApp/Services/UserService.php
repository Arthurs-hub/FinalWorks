<?php

namespace App\Services;

use App\Core\Db;
use App\Core\Logger;
use App\Repositories\UserRepository;
use App\Repositories\PasswordResetRepository;
use Exception;
use RuntimeException;
use App\Services\EmailService;

class UserService
{
    private UserRepository $userRepository;
    private PasswordResetRepository $passwordResetRepository;
    private EmailService $emailService;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->passwordResetRepository = new PasswordResetRepository();
        $this->emailService = new EmailService();
    }

    public function findUserByEmail(string $email): ?array
    {
        return $this->userRepository->findUserByEmail($email);
    }

    public function findUserById(int $userId): ?array
    {
        return $this->userRepository->findById($userId);
    }

    public function getUserById(int $userId): ?array
    {
        return $this->userRepository->getUserById($userId);
    }

    public function getUserStats(int $userId): array
    {
        return $this->userRepository->getUserStats($userId);
    }

    public function createUser(array $userData): int
    {
        if (isset($userData['password']) && !password_get_info($userData['password'])['algo']) {
            $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        }

        $userId = $this->userRepository->create($userData);

        if ($userId) {
            Logger::info("User created", [
                'user_id' => $userId,
                'email' => $userData['email'] ?? 'unknown',
            ]);
        }

        return $userId;
    }

    public function createUserWithRootDirectory(array $userData): int
    {
        return $this->userRepository->createUserWithRootDirectory($userData);
    }

    public function updateUser(int $userId, array $data): bool
    {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }

        $result = $this->userRepository->updateUser($userId, $data);

        if ($result) {
            Logger::info("User updated", [
                'user_id' => $userId,
                'updated_fields' => array_keys($data),
            ]);
        }

        return $result;
    }

    public function deleteUser(int $userId): bool
    {
        $result = $this->userRepository->deleteUser($userId);

        if ($result) {
            Logger::info("User deleted", [
                'deleted_user_id' => $userId,
            ]);
        }

        return $result;
    }

    public function getAllUsers(): array
    {
        return $this->userRepository->getAllUsers();
    }

    public function searchUsers(string $query): array
    {
        return $this->userRepository->searchUsers($query);
    }

    public function isAdmin(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        try {
            return $this->userRepository->isAdmin($userId);
        } catch (Exception $e) {
            Logger::error("Error checking admin status", ['user_id' => $userId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function makeAdmin(int $userId): bool
{
    try {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            Logger::error("User not found", ['user_id' => $userId]);
            return false;
        }

        $result1 = $this->userRepository->makeAdmin($userId);
        $result2 = $this->updateUser($userId, ['role' => 'admin']);

        $result = $result1 && $result2;

        if ($result) {
            Logger::info("User promoted to admin", ['user_id' => $userId]);
        }

        return $result;
    } catch (Exception $e) {
        Logger::error("Error promoting user to admin", ['user_id' => $userId, 'error' => $e->getMessage()]);
        return false;
    }
}

public function removeAdmin(int $userId): bool
{
    try {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            Logger::error("User not found", ['user_id' => $userId]);
            return false;
        }

        $result1 = $this->userRepository->removeAdmin($userId);
        $result2 = $this->updateUser($userId, ['role' => 'user']);

        $result = $result1 && $result2;

        if ($result) {
            Logger::info("User demoted from admin", ['user_id' => $userId]);
        }

        return $result;
    } catch (Exception $e) {
        Logger::error("Error removing admin rights", ['user_id' => $userId, 'error' => $e->getMessage()]);
        return false;
    }
}

    public function authenticateUser(string $email, string $password): ?array
    {
        try {

            $user = $this->userRepository->findByEmailWithPassword($email);

            if (! $user) {
                Logger::warning("Authentication failed - user not found", [
                    'email' => $email,
                ]);

                return null;
            }

            if (! password_verify($password, $user['password'])) {
                Logger::warning("Authentication failed - invalid password", [
                    'email' => $email,
                    'user_id' => $user['id'],
                ]);

                return null;
            }

            unset($user['password']);

            $this->userRepository->updateLastLogin($user['id']);

            Logger::info("User authenticated successfully", [
                'user_id' => $user['id'],
                'email' => $email,
            ]);

            return $user;
        } catch (Exception $e) {
            Logger::error("Authentication error", [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function validateUserData(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if (! $isUpdate || isset($data['email'])) {
            $email = $data['email'] ?? '';
            if (empty($email)) {
                $errors[] = 'Email обязателен';
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Некорректный формат email';
            } else {

                $existingUser = $this->userRepository->findByEmail($email);
                if ($existingUser && (! $isUpdate || $existingUser['id'] != ($data['id'] ?? 0))) {
                    $errors[] = 'Пользователь с таким email уже существует';
                }
            }
        }

        if (! $isUpdate || isset($data['password'])) {
            $password = $data['password'] ?? '';
            if (! $isUpdate && empty($password)) {
                $errors[] = 'Пароль обязателен';
            } elseif (! empty($password) && strlen($password) < 6) {
                $errors[] = 'Пароль должен содержать минимум 6 символов';
            }
        }

        if (! $isUpdate || isset($data['first_name'])) {
            $firstName = trim($data['first_name'] ?? '');
            if (empty($firstName)) {
                $errors[] = 'Имя обязательно';
            } elseif (strlen($firstName) < 2) {
                $errors[] = 'Имя должно содержать минимум 2 символа';
            }
        }

        if (! $isUpdate || isset($data['last_name'])) {
            $lastName = trim($data['last_name'] ?? '');
            if (empty($lastName)) {
                $errors[] = 'Фамилия обязательна';
            } elseif (strlen($lastName) < 2) {
                $errors[] = 'Фамилия должна содержать минимум 2 символа';
            }
        }

        if (isset($data['age'])) {
            $age = (int)$data['age'];
            if ($age < 13 || $age > 120) {
                $errors[] = 'Возраст должен быть от 13 до 120 лет';
            }
        }

        if (isset($data['gender'])) {
            $allowedGenders = ['male', 'female', 'other'];
            if (! in_array($data['gender'], $allowedGenders)) {
                $errors[] = 'Некорректное значение пола';
            }
        }

        return $errors;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->userRepository->findByEmail($email);
    }

    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        return $this->userRepository->updatePassword($userId, $hashedPassword);
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->userRepository->findById($userId);
        if (! $user) {
            throw new RuntimeException('Пользователь не найден');
        }

        $userWithPassword = $this->userRepository->findByEmailWithPassword($user['email']);
        if (! $userWithPassword) {
            throw new RuntimeException('Ошибка получения данных пользователя');
        }

        if (! password_verify($currentPassword, $userWithPassword['password'])) {
            Logger::warning("Password change failed - invalid current password", [
                'user_id' => $userId,
            ]);

            throw new RuntimeException('Неверный текущий пароль');
        }

        if (strlen($newPassword) < 6) {
            throw new RuntimeException('Новый пароль должен содержать минимум 6 символов');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if (! $this->userRepository->updatePassword($userId, $hashedPassword)) {
            throw new RuntimeException('Ошибка при обновлении пароля');
        }

        Logger::info("Password changed successfully", [
            'user_id' => $userId,
        ]);
    }

    public function resetPassword(string $email): string
    {
        try {
            $user = $this->userRepository->findByEmail($email);
            if (! $user) {

                Logger::warning("Password reset requested for non-existent email", [
                    'email' => $email,
                ]);

                return 'Ссылка для сброса пароля отправлена на ваш email';
            }

            $resetToken = bin2hex(random_bytes(16));
            $expiresAt = time() + 3600;

            error_log("Generated reset token: $resetToken");

            if (! $this->passwordResetRepository->createResetToken($user['id'], $resetToken, $expiresAt)) {
                throw new RuntimeException('Ошибка при сохранении токена сброса');
            }

            $emailSent = $this->emailService->sendPasswordResetEmail($email, $resetToken, $user['first_name'] . ' ' . $user['last_name']);
            if (! $emailSent) {
                Logger::error("Failed to send password reset email", ['email' => $email]);
                throw new RuntimeException('Ошибка при отправке email');
            }

            Logger::info("Password reset token generated and email sent", [
                'user_id' => $user['id'],
                'email' => $email,
                'token' => $resetToken,
            ]);

            return 'Ссылка для сброса пароля отправлена на ваш email';
        } catch (Exception $e) {
            Logger::error("Error sending password reset email", [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return 'Ссылка для сброса пароля отправлена на ваш email';
        }
    }

    public function generateTempPassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    public function userExists(int $userId): bool
    {
        return $this->userRepository->userExists($userId);
    }

    public function getUsersWithPagination(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        return $this->userRepository->getUsersWithPagination($offset, $perPage);
    }

    public function getActiveUsers(int $days = 30): array
    {
        return $this->userRepository->getActiveUsers($days);
    }

    public function getUsersStatistics(): array
    {
        return [
            'total_users' => $this->userRepository->countUsers(),
            'total_admins' => $this->userRepository->countAdmins(),
            'active_users_30_days' => count($this->userRepository->getActiveUsers(30)),
            'active_users_7_days' => count($this->userRepository->getActiveUsers(7)),
        ];
    }

    public function checkAdminRights(int $userId): bool
    {
        $user = $this->userRepository->findById($userId);

        return $user && ($user['role'] === 'admin' || $user['is_admin'] == 1);
    }

    public function promoteToAdmin(int $userId): array
    {
        if (! $this->userRepository->userExists($userId)) {
            throw new RuntimeException('Пользователь не найден');
        }

        return $this->userRepository->makeAdmin($userId);
    }

    public function demoteFromAdmin(int $userId): array
    {
        if (! $this->userRepository->userExists($userId)) {
            throw new RuntimeException('Пользователь не найден');
        }

        return $this->userRepository->removeAdmin($userId);
    }

    public function getAdminStats(): array
    {
        try {

            $fileRepo = new \App\Repositories\FileRepository();

            $totalUsers = $this->userRepository->countUsers();
            $totalAdmins = $this->userRepository->countAdmins();
            $activeUsers30Days = count($this->userRepository->getActiveUsers(30));
            $activeUsers7Days = count($this->userRepository->getActiveUsers(7));

            $totalFiles = $this->getTotalFilesCount();
            $totalFilesSize = $this->getTotalFilesSize();
            $totalDirectories = $this->getTotalDirectoriesCount();
            $totalShares = $this->getTotalSharesCount();

            $recentUsers = $this->getRecentUsers(5);

            $topUsersByFiles = $this->getTopUsersByFiles(5);

            $memoryUsage = memory_get_usage(true);
            $memoryLimitRaw = ini_get('memory_limit');
            $memoryLimit = $this->convertToBytes($memoryLimitRaw);

            $memoryUsageFormatted = $this->formatFileSize($memoryUsage);
            $memoryUsagePercent = null;
            if ($memoryLimit > 0) {
                $memoryUsagePercent = round(($memoryUsage / $memoryLimit) * 100, 1);
            }

            $systemStats = [
                'php_version' => PHP_VERSION,
                'memory_usage' => $memoryUsage,
                'memory_limit' => $memoryLimit,
                'memory_usage_formatted' => $memoryUsageFormatted,
                'memory_usage_percent' => $memoryUsagePercent,
                'disk_free_space' => disk_free_space(__DIR__),
                'disk_free_space_formatted' => $this->formatFileSize(disk_free_space(__DIR__)),
                'log_file_size' => Logger::getLogFileSize(),
            ];

            return [
                'users' => [
                    'total' => $totalUsers,
                    'admins' => $totalAdmins,
                    'active_30_days' => $activeUsers30Days,
                    'active_7_days' => $activeUsers7Days,
                    'recent' => $recentUsers,
                ],
                'files' => [
                    'total_count' => $totalFiles,
                    'total_size' => $totalFilesSize,
                    'total_size_formatted' => $this->formatFileSize($totalFilesSize),
                    'total_directories' => $totalDirectories,
                    'total_shares' => $totalShares,
                ],
                'top_users' => $topUsersByFiles,
                'system' => $systemStats,
            ];
        } catch (Exception $e) {
            Logger::error("UserService::getAdminStats error", [
                'error' => $e->getMessage(),
            ]);

            return [
                'users' => ['total' => 0, 'admins' => 0, 'active_30_days' => 0, 'active_7_days' => 0, 'recent' => []],
                'files' => ['total_count' => 0, 'total_size' => 0, 'total_size_formatted' => '0 B', 'total_directories' => 0, 'total_shares' => 0],
                'top_users' => [],
                'system' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage' => 0,
                    'memory_limit' => 0,
                    'memory_usage_formatted' => '0 B',
                    'memory_usage_percent' => '0%',
                    'disk_free_space' => 0,
                    'disk_free_space_formatted' => '0 B',
                    'log_file_size' => '0 B',
                ],
            ];
        }
    }

    private function getTotalFilesCount(): int
    {
        return $this->userRepository->getTotalFilesCount();
    }

    private function getTotalFilesSize(): int
    {
        return $this->userRepository->getTotalFilesSize();
    }

    private function getTotalDirectoriesCount(): int
    {
        return $this->userRepository->getTotalDirectoriesCount();
    }


    private function getTotalSharesCount(): int
    {
        return $this->userRepository->getTotalSharesCount();
    }

    private function getRecentUsers(int $limit = 5): array
    {
        return $this->userRepository->getRecentUsers($limit);
    }

    private function getTopUsersByFiles(int $limit = 5): array
    {
        $users = $this->userRepository->getTopUsersByFiles($limit);
        foreach ($users as &$user) {
            $user['total_size_formatted'] = $this->formatFileSize((int)$user['total_size']);
        }
        return $users;
    }

    private function convertToBytes(string $memoryLimitRaw): int
    {
        if ($memoryLimitRaw === '-1') {
            return -1;
        }

        $memoryLimit = trim($memoryLimitRaw);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int)$memoryLimit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getAllUsersWithStats(): array
    {
        return $this->userRepository->getAllUsersWithStats();
    }

    public function getUserForAdmin(int $userId): ?array
    {
        $user = $this->userRepository->findById($userId);
        if (! $user) {
            return null;
        }

        $stats = $this->userRepository->getUserStats($userId);

        return array_merge($user, [
            'files_count' => $stats['files_count'] ?? 0,
            'total_size' => $stats['total_size'] ?? 0,
            'total_size_formatted' => $this->formatFileSize($stats['total_size'] ?? 0),
            'directories_count' => $stats['directories_count'] ?? 0,
            'shared_files_count' => $stats['shared_files_count'] ?? 0,
            'received_shares_count' => $stats['received_shares_count'] ?? 0,
        ]);
    }

    public function updateLastLogin(int $userId): bool
    {
        return $this->userRepository->updateLastLogin($userId);
    }

    public function banUser(int $userId): bool
    {
        try {
            $result = $this->userRepository->banUser($userId);

            if ($result) {
                Logger::info("User banned", ['user_id' => $userId]);
            }

            return $result;
        } catch (Exception $e) {
            Logger::error("Error banning user", ['user_id' => $userId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function unbanUser(int $userId): bool
    {
        try {
            $result = $this->userRepository->unbanUser($userId);

            if ($result) {
                Logger::info("User unbanned", ['user_id' => $userId]);
            }

            return $result;
        } catch (Exception $e) {
            Logger::error("Error unbanning user", ['user_id' => $userId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function isUserBanned(int $userId): bool
    {
        return $this->userRepository->isUserBanned($userId);
    }

    public function getUserActivity(int $userId, int $days = 30): array
    {
        try {
            return $this->userRepository->getUserActivity($userId, $days);
        } catch (Exception $e) {
            Logger::error("Error getting user activity", [
                'user_id' => $userId,
                'days' => $days,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function cleanupInactiveUsers(int $days = 365): int
    {
        try {
            $inactiveUsers = $this->userRepository->getInactiveUsers($days);
            $deletedCount = 0;

            foreach ($inactiveUsers as $user) {
                if ($this->deleteUser($user['id'])) {
                    $deletedCount++;
                }
            }

            Logger::info("Inactive users cleanup completed", [
                'days' => $days,
                'deleted_count' => $deletedCount,
            ]);

            return $deletedCount;
        } catch (Exception $e) {
            Logger::error("Error during inactive users cleanup", [
                'days' => $days,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function exportUserData(int $userId): array
    {
        try {
            $user = $this->userRepository->findById($userId);
            if (! $user) {
                throw new RuntimeException('Пользователь не найден');
            }

            $userData = [
                'user_info' => $user,
                'files' => $this->getUserFiles($userId),
                'directories' => $this->getUserDirectories($userId),
                'shared_files' => $this->getUserSharedFiles($userId),
                'received_shares' => $this->getUserReceivedShares($userId),
                'activity_log' => $this->getUserActivity($userId, 90), // последние 90 дней
            ];

            Logger::info("User data exported", [
                'user_id' => $userId,
                'exported_by' => $_SESSION['user_id'] ?? 'system',
            ]);

            return $userData;
        } catch (Exception $e) {
            Logger::error("Error exporting user data", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function getUserFiles(int $userId): array
    {
        return $this->userRepository->getUserFiles($userId);
    }

    private function getUserDirectories(int $userId): array
    {
        return $this->userRepository->getUserDirectories($userId);
    }

    private function getUserSharedFiles(int $userId): array
    {
        return $this->userRepository->getUserSharedFiles($userId);
    }

    private function getUserReceivedShares(int $userId): array
    {
        return $this->userRepository->getUserReceivedShares($userId);
    }


    public function sendPasswordResetEmail(string $email): bool
    {
        try {
            $user = $this->userRepository->findByEmail($email);
            if (! $user) {

                Logger::warning("Password reset requested for non-existent email", [
                    'email' => $email,
                ]);

                return true;
            }

            $resetToken = bin2hex(random_bytes(16));
            $expiresAt = time() + 3600;

            error_log("Generated reset token: $resetToken");

            if (! $this->passwordResetRepository->createResetToken($user['id'], $resetToken, $expiresAt)) {
                throw new RuntimeException('Ошибка при сохранении токена сброса');
            }

            $emailSent = $this->emailService->sendPasswordResetEmail(
                $email,
                $resetToken,
                $user['first_name'] . ' ' . $user['last_name']
            );

            if (! $emailSent) {
                Logger::error("Failed to send password reset email", ['email' => $email]);
                throw new RuntimeException('Ошибка при отправке email');
            }

            Logger::info("Password reset token generated and email sent", [
                'user_id' => $user['id'],
                'email' => $email,
                'token' => $resetToken,
            ]);

            return true;
        } catch (Exception $e) {
            Logger::error("Error sending password reset email", [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function resetPasswordWithToken(string $token, string $newPassword): array
    {
        if (empty($token) || empty($newPassword)) {
            return ['success' => false, 'error' => 'Токен и новый пароль обязательны'];
        }

        try {
            $tokenData = $this->passwordResetRepository->findValidToken($token);
            if (!$tokenData) {
                return ['success' => false, 'error' => 'Недействительный или просроченный токен'];
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            if (!$this->userRepository->updatePassword($tokenData['user_id'], $hashedPassword)) {
                return ['success' => false, 'error' => 'Ошибка при обновлении пароля'];
            }

            $this->passwordResetRepository->markTokenAsUsed($token);

            return ['success' => true, 'message' => 'Пароль успешно изменен'];
        } catch (Exception $e) {
            Logger::error("UserService::resetPasswordWithToken error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при сбросе пароля'];
        }
    }

    public function validatePasswordResetToken(string $token): bool
    {
        try {
            $user = $this->userRepository->findByPasswordResetToken($token);

            if (! $user) {
                return false;
            }

            $dt = new \DateTime('@' . $user['reset_token_expires']);
            $dt = $dt->setTimezone(new \DateTimeZone('Europe/Moscow'));

            return strtotime($user['reset_token_expires']) >= time();
        } catch (Exception $e) {
            Logger::error("Error validating password reset token", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function cleanupExpiredTokens(): int
    {
        try {
            $deletedCount = $this->userRepository->deleteExpiredPasswordResetTokens();

            if ($deletedCount > 0) {
                Logger::info("Expired password reset tokens cleaned up", [
                    'deleted_count' => $deletedCount,
                ]);
            }

            return $deletedCount;
        } catch (Exception $e) {
            Logger::error("Error cleaning up expired tokens", [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function resetPasswordByAdmin(int $userId): string
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new RuntimeException('Пользователь не найден');
        }

        $tempPassword = $this->generateTempPassword();
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

        if (!$this->userRepository->updatePassword($userId, $hashedPassword)) {
            throw new RuntimeException('Ошибка при обновлении пароля');
        }

        Logger::info("Password reset by admin", [
            'user_id' => $userId,
        ]);

        return $tempPassword;
    }

    public function getUsersCount(): int
    {
        return $this->userRepository->countUsers();
    }

    public function getAdminsCount(): int
    {
        return $this->userRepository->countAdmins();
    }

    public function getUsersByRole(string $role = 'user'): array
    {
        return $this->userRepository->getUsersByRole($role);
    }

    public function bulkDeleteUsers(array $userIds): array
    {
        return $this->userRepository->bulkDeleteUsers($userIds);
    }

    public function bulkUpdateUsers(array $updates): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'total' => count($updates),
        ];

        foreach ($updates as $update) {
            $userId = $update['id'] ?? null;
            $data = $update['data'] ?? [];

            if (! $userId) {
                $results['failed'][] = [
                    'id' => null,
                    'error' => 'ID пользователя не указан',
                ];

                continue;
            }

            try {
                if ($this->updateUser((int)$userId, $data)) {
                    $results['success'][] = $userId;
                } else {
                    $results['failed'][] = [
                        'id' => $userId,
                        'error' => 'Не удалось обновить пользователя',
                    ];
                }
            } catch (Exception $e) {
                $results['failed'][] = [
                    'id' => $userId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Logger::info("Bulk user update completed", [
            'total' => $results['total'],
            'success_count' => count($results['success']),
            'failed_count' => count($results['failed']),
        ]);

        return $results;
    }

    public function getSystemHealth(): array
    {
        try {
            $health = [
                'status' => 'healthy',
                'checks' => [],
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            try {
                $db = new Db();
                $conn = $db->getConnection();
                $stmt = $conn->prepare("SELECT 1");
                $stmt->execute();
                $health['checks']['database'] = [
                    'status' => 'ok',
                    'message' => 'Подключение к базе данных работает',
                ];
            } catch (Exception $e) {
                $health['checks']['database'] = [
                    'status' => 'error',
                    'message' => 'Ошибка подключения к базе данных: ' . $e->getMessage(),
                ];
                $health['status'] = 'unhealthy';
            }

            $uploadsDir = __DIR__ . '/../uploads';
            if (is_dir($uploadsDir) && is_writable($uploadsDir)) {
                $health['checks']['uploads_directory'] = [
                    'status' => 'ok',
                    'message' => 'Директория uploads доступна для записи',
                ];
            } else {
                $health['checks']['uploads_directory'] = [
                    'status' => 'error',
                    'message' => 'Директория uploads недоступна или не доступна для записи',
                ];
                $health['status'] = 'unhealthy';
            }

            $logsDir = __DIR__ . '/../logs';
            if (is_dir($logsDir) && is_writable($logsDir)) {
                $health['checks']['logs_directory'] = [
                    'status' => 'ok',
                    'message' => 'Директория logs доступна для записи',
                ];
            } else {
                $health['checks']['logs_directory'] = [
                    'status' => 'warning',
                    'message' => 'Директория logs недоступна или не доступна для записи',
                ];
            }

            $freeSpace = disk_free_space(__DIR__);
            $minFreeSpace = 100 * 1024 * 1024;

            if ($freeSpace > $minFreeSpace) {
                $health['checks']['disk_space'] = [
                    'status' => 'ok',
                    'message' => 'Достаточно свободного места на диске',
                    'free_space' => $this->formatFileSize($freeSpace),
                ];
            } else {
                $health['checks']['disk_space'] = [
                    'status' => 'warning',
                    'message' => 'Мало свободного места на диске',
                    'free_space' => $this->formatFileSize($freeSpace),
                ];
            }

            $memoryUsage = memory_get_usage(true);
            $memoryLimitRaw = ini_get('memory_limit');
            $memoryLimit = $this->convertToBytes($memoryLimitRaw);

            if ($memoryLimit > 0 && $memoryUsage < ($memoryLimit * 0.8)) {
                $health['checks']['memory_usage'] = [
                    'status' => 'ok',
                    'message' => 'Использование памяти в норме',
                    'usage' => $this->formatFileSize($memoryUsage),
                    'limit' => $memoryLimitRaw,
                ];
            } else {
                $health['checks']['memory_usage'] = [
                    'status' => 'warning',
                    'message' => 'Высокое использование памяти',
                    'usage' => $this->formatFileSize($memoryUsage),
                    'limit' => $memoryLimitRaw,
                ];
            }

            return $health;
        } catch (Exception $e) {
            Logger::error("Error checking system health", [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Ошибка при проверке состояния системы',
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }
    }

    public function getSecurityReport(): array
    {
        try {
            $report = [
                'timestamp' => date('Y-m-d H:i:s'),
                'security_checks' => [],
                'recommendations' => [],
            ];

            $weakPasswordsCount = $this->userRepository->countWeakPasswords();
            if ($weakPasswordsCount > 0) {
                $report['security_checks']['weak_passwords'] = [
                    'status' => 'warning',
                    'count' => $weakPasswordsCount,
                    'message' => "Найдено пользователей со слабыми паролями : $weakPasswordsCount",
                ];
                $report['recommendations'][] = 'Рекомендуется уведомить пользователей о необходимости смены паролей';
            } else {
                $report['security_checks']['weak_passwords'] = [
                    'status' => 'ok',
                    'message' => 'Слабые пароли не обнаружены',
                ];
            }

            $inactiveAdmins = $this->userRepository->getInactiveAdmins(90); // 90 дней
            if (count($inactiveAdmins) > 0) {
                $report['security_checks']['inactive_admins'] = [
                    'status' => 'warning',
                    'count' => count($inactiveAdmins),
                    'message' => 'Найдены неактивные администраторы',
                ];
                $report['recommendations'][] = 'Рассмотрите возможность отзыва прав администратора у неактивных пользователей';
            } else {
                $report['security_checks']['inactive_admins'] = [
                    'status' => 'ok',
                    'message' => 'Все администраторы активны',
                ];
            }

            $suspiciousActivity = $this->getSuspiciousLoginActivity();
            if (count($suspiciousActivity) > 0) {
                $report['security_checks']['suspicious_activity'] = [
                    'status' => 'warning',
                    'count' => count($suspiciousActivity),
                    'message' => 'Обнаружена подозрительная активность входа',
                ];
                $report['recommendations'][] = 'Проверьте логи на предмет попыток взлома';
            } else {
                $report['security_checks']['suspicious_activity'] = [
                    'status' => 'ok',
                    'message' => 'Подозрительная активность не обнаружена',
                ];
            }

            return $report;
        } catch (Exception $e) {
            Logger::error("Error generating security report", [
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Ошибка при генерации отчета безопасности',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }
    }

    private function getSuspiciousLoginActivity(): array
    {
        try {
            $logs = Logger::getRecentLogs(1000);
            $suspiciousIPs = [];

            foreach ($logs as $log) {
                if (
                    isset($log['message']) &&
                    strpos($log['message'], 'Authentication failed') !== false
                ) {
                    $ip = $log['ip'] ?? 'unknown';
                    if (! isset($suspiciousIPs[$ip])) {
                        $suspiciousIPs[$ip] = 0;
                    }
                    $suspiciousIPs[$ip]++;
                }
            }

            return array_filter($suspiciousIPs, function ($count) {
                return $count > 10;
            });
        } catch (Exception $e) {
            return [];
        }
    }

    public function register(array $data): array
    {
        try {
            $errors = $this->validateUserData($data);
            if (!empty($errors)) {
                return ['success' => false, 'error' => implode(', ', $errors)];
            }

            if (isset($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $newUserId = $this->createUserWithRootDirectory($data);
            return ['success' => true, 'message' => 'Регистрация успешна!'];
        } catch (RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            Logger::error("Registration error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при регистрации'];
        }
    }

    public function login(array $data): array
    {
        try {
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($email) || empty($password)) {
                return ['success' => false, 'error' => 'Email и пароль обязательны'];
            }

            $user = $this->authenticateUser($email, $password);

            if ($user) {
                $role = ($user['is_admin'] == 1) ? 'admin' : 'user';

                if (isset($user['role'])) {
                    unset($user['role']);
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $role;
                $_SESSION['is_admin'] = $user['is_admin'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];

                return [
                    'success' => true,
                    'role' => $role,
                    'user' => $user,
                ];
            } else {
                return ['success' => false, 'error' => 'Неверный email или пароль'];
            }
        } catch (Exception $e) {
            Logger::error("UserService::login exception", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка сервера'];
        }
    }

    public function getCurrentUser(?int $userId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'Пользователь не авторизован'];
        }

        try {
            $user = $this->getUserById($userId);

            if ($user) {
                $_SESSION['role'] = $user['role'];

                $responseUser = [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role'] ?? 'user',
                    'is_admin' => (int)($user['is_admin'] ?? 0),
                    'age' => $user['age'] ?? null,
                    'gender' => $user['gender'] ?? null,
                    'created_at' => $user['created_at'] ?? null,
                    'last_login' => $user['last_login'] ?? null,
                ];

                return ['success' => true, 'user' => $responseUser];
            } else {
                return ['success' => false, 'error' => 'Пользователь не найден'];
            }
        } catch (Exception $e) {
            Logger::error("UserService::getCurrentUser exception", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка сервера'];
        }
    }

    public function get(?int $userId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        try {
            $user = $this->getUserById($userId);

            if (!$user) {
                return ['success' => false, 'error' => 'Пользователь не найден'];
            }

            return ['success' => true, 'user' => $user];
        } catch (Exception $e) {
            Logger::error("UserService::get error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при получении данных пользователя'];
        }
    }

    public function list(): array
    {
        try {
            $users = $this->getAllUsers();
            return ['success' => true, 'users' => $users];
        } catch (Exception $e) {
            Logger::error("UserService::list error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при получении списка пользователей'];
        }
    }

    public function update(?int $userId, array $data, ?int $currentUserId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        if ($userId != $currentUserId && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
            return ['success' => false, 'error' => 'Недостаточно прав'];
        }

        try {
            $errors = $this->validateUserData($data, true);
            if (!empty($errors)) {
                return ['success' => false, 'error' => implode(', ', $errors)];
            }

            $success = $this->updateUser($userId, $data);
            if (!$success) {
                return ['success' => false, 'error' => 'Ошибка при обновлении пользователя'];
            }

            return ['success' => true, 'message' => 'Пользователь успешно обновлен'];
        } catch (Exception $e) {
            Logger::error("UserService::update error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при обновлении пользователя'];
        }
    }

    public function changeUserPassword(?int $userId, array $data): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'Пользователь не авторизован'];
        }

        try {
            $currentPassword = $data['current_password'] ?? '';
            $newPassword = $data['new_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword)) {
                return ['success' => false, 'error' => 'Необходимо указать текущий и новый пароль'];
            }

            $this->changePassword($userId, $currentPassword, $newPassword);
            return ['success' => true, 'message' => 'Пароль успешно изменен'];
        } catch (RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            Logger::error("UserService::changeUserPassword error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при изменении пароля'];
        }
    }

    public function logout(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 42000, '/');
        }

        session_destroy();

        return ['success' => true, 'message' => 'Выход выполнен успешно'];
    }

    public function publicPasswordReset(array $data): array
    {
        error_log("UserService::publicPasswordReset called with data: " . json_encode($data));

        try {

            if (isset($data['token']) && isset($data['password'])) {
                $token = $data['token'];
                $newPassword = $data['password'];

                $result = $this->resetPasswordWithToken($token, $newPassword);
                return ['success' => true, 'message' => 'Пароль успешно изменен'];
            }

            if (isset($data['token']) && !isset($data['password'])) {
                return $this->validateResetToken($data['token']);
            }

            return $this->requestPasswordReset($data);
        } catch (RuntimeException $e) {
            error_log("Password reset runtime error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            Logger::error("UserService::publicPasswordReset error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при сбросе пароля'];
        }
    }

    public function getUserStatsWithAuth(?int $userId, ?int $currentUserId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        if ($userId != $currentUserId && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
            return ['success' => false, 'error' => 'Недостаточно прав'];
        }

        try {
            $stats = $this->getUserStats($userId);
            return ['success' => true, 'stats' => $stats];
        } catch (Exception $e) {
            Logger::error("UserService::getUserStatsWithAuth error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при получении статистики'];
        }
    }

    public function delete(?int $userId, ?int $currentUserId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            return ['success' => false, 'error' => 'Недостаточно прав'];
        }

        if ($userId == $currentUserId) {
            return ['success' => false, 'error' => 'Нельзя удалить самого себя'];
        }

        try {
            $success = $this->deleteUser($userId);

            if ($success) {
                return ['success' => true, 'message' => 'Пользователь успешно удален'];
            } else {
                return ['success' => false, 'error' => 'Пользователь не найден'];
            }
        } catch (Exception $e) {
            Logger::error("UserService::delete error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при удалении пользователя'];
        }
    }

    /**
     * Создание первого администратора
     * ТОЛЬКО ДЛЯ ТЕСТИРОВАНИЯ! (например в Postman)
     */
    public function createFirstAdmin(array $data): array
    {
        $email = $data['email'] ?? '';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Некорректный email', 'status_code' => 400];
        }

        $adminCount = $this->userRepository->getAdminCount();
        if ($adminCount > 0) {
            return [
                'success' => false,
                'error' => 'Администратор уже существует.',
                'admin_count' => $adminCount,
                'status_code' => 403
            ];
        }

        $user = $this->userRepository->findUserByEmail($email);
        if (!$user) {
            return [
                'success' => false,
                'error' => 'Пользователь с таким email не найден.',
                'status_code' => 404
            ];
        }

        if ($user['is_admin']) {
            return [
                'success' => false,
                'error' => 'Пользователь уже является администратором',
                'status_code' => 400
            ];
        }

        $success = $this->userRepository->promoteToAdmin($user['id']);
        if ($success) {
            return [
                'success' => true,
                'message' => 'Пользователь успешно назначен администратором',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
                    'role' => 'admin',
                    'is_admin' => true
                ]
            ];
        }

        return ['success' => false, 'error' => 'Ошибка при назначении администратора', 'status_code' => 500];
    }

    public function requestPasswordReset(array $data): array
    {
        $email = trim($data['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Некорректный email'];
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            return ['success' => true, 'message' => 'Если пользователь с таким email существует, на него будет отправлена ссылка для сброса пароля'];
        }

        $resetToken = bin2hex(random_bytes(16));
        $expiresAt = time() + 3600;

        if (!$this->passwordResetRepository->createResetToken($user['id'], $resetToken, $expiresAt)) {
            return ['success' => false, 'error' => 'Ошибка при сохранении токена сброса'];
        }

        if (!$this->emailService->sendPasswordResetEmail($email, $resetToken, $user['first_name'] . ' ' . $user['last_name'])) {
            return ['success' => false, 'error' => 'Ошибка при отправке email'];
        }

        return ['success' => true, 'message' => 'Ссылка для сброса пароля отправлена на ваш email'];
    }

    public function validateResetToken(string $token): array
    {
        try {
            if (empty($token)) {
                return ['success' => false, 'error' => 'Токен не указан'];
            }

            $tokenData = $this->passwordResetRepository->findValidToken($token);

            if (!$tokenData) {
                return ['success' => false, 'error' => 'Недействительный или просроченный токен'];
            }

            $dt = new \DateTime('@' . $tokenData['expires_at']);
            $dt = $dt->setTimezone(new \DateTimeZone('Europe/Moscow'));

            return [
                'success' => true,
                'data' => [
                    'email' => $tokenData['email'],
                    'user_name' => $tokenData['first_name'] . ' ' . $tokenData['last_name'],
                    'expires_at' => $dt->format('Y-m-d H:i:s'),
                ],
            ];
        } catch (Exception $e) {

            return ['success' => false, 'error' => 'Ошибка при проверке токена'];
        }
    }
}
