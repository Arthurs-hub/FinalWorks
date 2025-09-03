<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Logger;
use App\Repositories\IAdminRepository;
use App\Repositories\IDirectoryRepository;
use App\Repositories\IPasswordResetRepository;
use App\Repositories\ITwoFactorRepository;
use App\Repositories\IUserRepository;
use Exception;
use RuntimeException;

class UserService implements IUserService
{
    private IUserRepository $userRepository;
    private IPasswordResetRepository $passwordResetRepository;
    private IEmailService $emailService;
    private ITwoFactorRepository $twoFactorRepository;
    private ITwoFactorService $twoFactorService;
    private IAdminRepository $adminRepository;
    private IDirectoryRepository $directoryRepository;
    private Db $db;
    private array $config;

    public function __construct(
        IUserRepository $userRepository,
        IPasswordResetRepository $passwordResetRepository,
        IEmailService $emailService,
        ITwoFactorRepository $twoFactorRepository,
        ITwoFactorService $twoFactorService,
        IAdminRepository $adminRepository,
        IDirectoryRepository $directoryRepository,
        Db $db,
        array $config
    ) {
        $this->userRepository = $userRepository;
        $this->passwordResetRepository = $passwordResetRepository;
        $this->emailService = $emailService;
        $this->twoFactorRepository = $twoFactorRepository;
        $this->twoFactorService = $twoFactorService;
        $this->adminRepository = $adminRepository;
        $this->directoryRepository = $directoryRepository;
        $this->db = $db;
        $this->config = $config;
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
            Logger::info("User created", ['user_id' => $userId, 'email' => $userData['email'] ?? 'unknown']);
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
            Logger::info("User updated", ['user_id' => $userId, 'updated_fields' => array_keys($data)]);
        }

        return $result;
    }

    public function deleteUser(int $userId): bool
    {
        $result = $this->userRepository->deleteUser($userId);

        if ($result) {
            Logger::info("User deleted", ['deleted_user_id' => $userId]);
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
        if (!$userId) {
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

            Logger::info("User promoted to admin", ['user_id' => $userId]);
            return $this->userRepository->makeAdmin($userId);
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

            Logger::info("User demoted from admin", ['user_id' => $userId]);
            return $this->userRepository->removeAdmin($userId);
        } catch (Exception $e) {
            Logger::error("Error removing admin rights", ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function authenticateUser(string $email, string $password): ?array
    {
        try {
            $user = $this->userRepository->findByEmailWithPassword($email);

            if (!$user) {
                Logger::warning("Authentication failed - user not found", ['email' => $email]);
                return null;
            }

            if (!password_verify($password, $user['password'])) {
                Logger::warning("Authentication failed - invalid password", ['email' => $email, 'user_id' => $user['id']]);
                return null;
            }

            unset($user['password']);
            $this->userRepository->updateLastLogin($user['id']);
            Logger::info("User authenticated successfully", ['user_id' => $user['id'], 'email' => $email]);

            return $user;
        } catch (Exception $e) {
            Logger::error("Authentication error", ['email' => $email, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function validateUserData(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if (!$isUpdate || isset($data['email'])) {
            $email = $data['email'] ?? '';
            if (empty($email)) {
                $errors[] = 'Email обязателен';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Некорректный формат email';
            } else {
                $existingUser = $this->userRepository->findByEmail($email);
                if ($existingUser && (!$isUpdate || $existingUser['id'] != ($data['id'] ?? 0))) {
                    $errors[] = 'Пользователь с таким email уже существует';
                }
            }
        }

        if (!$isUpdate || isset($data['password'])) {
            $password = $data['password'] ?? '';
            if (!$isUpdate && empty($password)) {
                $errors[] = 'Пароль обязателен';
            } elseif (!empty($password) && strlen($password) < 6) {
                $errors[] = 'Пароль должен содержать минимум 6 символов';
            }
        }

        if (!$isUpdate || isset($data['first_name'])) {
            $firstName = trim($data['first_name'] ?? '');
            if (empty($firstName)) {
                $errors[] = 'Имя обязательно';
            } elseif (strlen($firstName) < 2) {
                $errors[] = 'Имя должно содержать минимум 2 символа';
            }
        }

        if (!$isUpdate || isset($data['last_name'])) {
            $lastName = trim($data['last_name'] ?? '');
            if (empty($lastName)) {
                $errors[] = 'Фамилия обязательна';
            } elseif (strlen($lastName) < 2) {
                $errors[] = 'Фамилия должна содержать минимум 2 символа';
            }
        }

        if (isset($data['age'])) {
            $age = (int) $data['age'];
            if ($age < 13 || $age > 120) {
                $errors[] = 'Возраст должен быть от 13 до 120 лет';
            }
        }

        if (isset($data['gender'])) {
            $allowedGenders = ['male', 'female', 'other'];
            if (!in_array($data['gender'], $allowedGenders)) {
                $errors[] = 'Некорректное значение пола';
            }
        }

        return $errors;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->userRepository->findByEmail($email);
    }

    private function generateTempPassword(): string
    {
        $base = bin2hex(random_bytes(8));
        $symbols = str_shuffle('!@#$%^&*');
        return substr($base, 0, 12) . substr($symbols, 0, 2);
    }

    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        return $this->userRepository->updatePassword($userId, $hashedPassword);
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new RuntimeException('Пользователь не найден');
        }
        $userWithPassword = $this->userRepository->findByEmailWithPassword($user['email']);
        if (!$userWithPassword) {
            throw new RuntimeException('Ошибка получения данных пользователя');
        }

        if (!password_verify($currentPassword, $userWithPassword['password'])) {
            Logger::warning("Password change failed - invalid current password", ['user_id' => $userId]);
            throw new RuntimeException('Неверный текущий пароль');
        }

        if (strlen($newPassword) < 6) {
            throw new RuntimeException('Новый пароль должен содержать минимум 6 символов');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!$this->userRepository->updatePassword($userId, $hashedPassword)) {
            throw new RuntimeException('Ошибка при обновлении пароля');
        }

        Logger::info("Password changed successfully", ['user_id' => $userId]);
    }

    public function resetPassword(string $email): string
    {
        try {
            $user = $this->userRepository->findByEmail($email);
            if (!$user) {
                Logger::warning("Password reset requested for non-existent email", ['email' => $email]);
                return 'Ссылка для сброса пароля отправлена на ваш email';
            }

            $resetToken = $this->passwordResetRepository->createToken($email);
            if (!$resetToken) {
                throw new RuntimeException('Ошибка при сохранении токена сброса');
            }

            $emailSent = $this->emailService->sendPasswordResetEmail($email, $resetToken, $user['first_name'] . ' ' . $user['last_name']);
            if (!$emailSent) {
                Logger::error("Failed to send password reset email", ['email' => $email]);
                throw new RuntimeException('Ошибка при отправке email');
            }

            Logger::info("Password reset token generated and email sent", ['user_id' => $user['id'], 'email' => $email]);

            return 'Ссылка для сброса пароля отправлена на ваш email';
        } catch (Exception $e) {
            Logger::error("Error sending password reset email", ['email' => $email, 'error' => $e->getMessage()]);
            return 'Ссылка для сброса пароля отправлена на ваш email';
        }
    }

    public function getActiveUsers(int $days = 30): array
    {
        return $this->userRepository->getActiveUsers($days);
    }

    public function promoteToAdmin(int $userId): bool
    {
        if (!$this->userRepository->userExists($userId)) {
            throw new RuntimeException('Пользователь не найден');
        }
        return $this->makeAdmin($userId);
    }

    public function demoteFromAdmin(int $userId): bool
    {
        if (!$this->userRepository->userExists($userId)) {
            throw new RuntimeException('Пользователь не найден');
        }
        return $this->removeAdmin($userId);
    }

    private function convertToBytes(string $memoryLimitRaw): int
    {
        if ($memoryLimitRaw === '-1') {
            return -1;
        }
        $memoryLimit = trim($memoryLimitRaw);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;
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

    public function getUserActivity(int $userId, int $days = 30): array
    {
        try {
            return $this->userRepository->getUserActivity($userId, $days);
        } catch (Exception $e) {
            Logger::error("Error getting user activity", ['user_id' => $userId, 'days' => $days, 'error' => $e->getMessage()]);
            return [];
        }
    }

    public function exportUserData(int $userId): array
    {
        try {
            $user = $this->userRepository->findById($userId);
            if (!$user) {
                throw new RuntimeException('Пользователь не найден');
            }

            $userData = [
                'user_info' => $user,
                'files' => $this->userRepository->getUserFiles($userId),
                'directories' => $this->userRepository->getUserDirectories($userId),
                'shared_files' => $this->userRepository->getUserSharedFiles($userId),
                'received_shares' => $this->userRepository->getUserReceivedShares($userId),
                'activity_log' => $this->getUserActivity($userId, 90),
            ];

            Logger::info("User data exported", ['user_id' => $userId, 'exported_by' => $_SESSION['user_id'] ?? 'system']);

            return $userData;
        } catch (Exception $e) {
            Logger::error("Error exporting user data", ['user_id' => $userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function resetPasswordWithToken(string $token, string $newPassword, string $passwordConfirmation): array
    {
        if (empty($token)) {
            return ['success' => false, 'error' => 'Токен не предоставлен.'];
        }

        if (mb_strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'Пароль должен быть не менее 6 символов.'];
        }

        if ($newPassword !== $passwordConfirmation) {
            return ['success' => false, 'error' => 'Пароли не совпадают.'];
        }

        $user = $this->passwordResetRepository->findUserByToken($token);

        if (!$user) {
            return ['success' => false, 'error' => 'Неверный или истекший токен сброса пароля.'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateSuccess = $this->userRepository->updatePassword($user['id'], $hashedPassword);

        if ($updateSuccess) {

            $this->passwordResetRepository->deleteToken($token);
            Logger::info("Password reset successfully for user", ['user_id' => $user['id']]);
            return ['success' => true, 'message' => 'Пароль успешно изменен.'];
        } else {
            Logger::error("Failed to update password for user", ['user_id' => $user['id']]);
            return ['success' => false, 'error' => 'Не удалось обновить пароль.'];
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

        Logger::info("Password reset by admin", ['user_id' => $userId]);

        return $tempPassword;
    }

    public function bulkDeleteUsers(array $userIds): array
    {
        return $this->userRepository->bulkDeleteUsers($userIds);
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
                $stmt = $this->db->getConnection()->prepare("SELECT 1");
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

            $uploadsDir = $this->config['app']['upload_path'] ?? __DIR__ . '/../../uploads';
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

            $logsDir = $this->config['app']['log_path'] ?? __DIR__ . '/../../logs';
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

            $freeSpaceValue = @disk_free_space($uploadsDir);
            $freeSpace = is_int($freeSpaceValue) ? $freeSpaceValue : 0;

            $minFreeSpace = 100 * 1024 * 1024;

            if ($freeSpace > $minFreeSpace) {
                $health['checks']['disk_space'] = [
                    'status' => 'ok',
                    'message' => 'Достаточно свободного места на диске',
                    'free_space' => $this->formatFileSize($freeSpace),
                ];
            } else {
                $health['checks']['disk_space'] = [
                    'status' => $freeSpace === 0 ? 'error' : 'warning',
                    'message' => $freeSpace === 0 ? 'Не удалось определить свободное место на диске' : 'Мало свободного места на диске',
                    'free_space' => $this->formatFileSize($freeSpace),
                ];
                if ($freeSpace === 0) {
                    $health['status'] = 'unhealthy';
                }
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
            Logger::error("Error checking system health", ['error' => $e->getMessage()]);
            return ['status' => 'error', 'message' => 'Ошибка при проверке состояния системы', 'error' => $e->getMessage(), 'timestamp' => date('Y-m-d H:i:s')];
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

            $inactiveAdmins = $this->userRepository->getInactiveAdmins(90);
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
            Logger::error("Error generating security report", ['error' => $e->getMessage()]);
            return ['error' => 'Ошибка при генерации отчета безопасности', 'timestamp' => date('Y-m-d H:i:s')];
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
                    $ip = $log['context']['ip'] ?? 'unknown';
                    if (!isset($suspiciousIPs[$ip])) {
                        $suspiciousIPs[$ip] = 0;
                    }
                    $suspiciousIPs[$ip]++;
                }
            }
            return array_filter($suspiciousIPs, fn($count) => $count > 10);
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

            $enableTwoFactor = isset($data['enable_two_factor']) && $data['enable_two_factor'];
            if ($enableTwoFactor) {
                $data['two_factor_enabled'] = 1;
                $data['two_factor_method'] = 'email';
                $data['two_factor_setup_completed'] = 0;
            }

            unset($data['enable_two_factor']);

            $newUserId = $this->createUserWithRootDirectory($data);

            Logger::info("User registered", ['user_id' => $newUserId, 'email' => $data['email'] ?? 'unknown', 'two_factor_enabled' => $enableTwoFactor]);

            try {
                $userName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
                $this->emailService->sendWelcomeEmail($data['email'], $userName);
                Logger::info("Welcome email sent successfully", ['user_id' => $newUserId, 'email' => $data['email']]);
            } catch (Exception $e) {
                Logger::error("Failed to send welcome email", [
                    'user_id' => $newUserId,
                    'email' => $data['email'],
                    'error' => $e->getMessage()
                ]);
            }

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
                $twoFactorSettings = $this->twoFactorRepository->getUserTwoFactorSettings($user['id']);
                $forcedTwoFactor = $this->twoFactorRepository->isForcedTwoFactorEnabled();

                $userHas2FA = (bool) ($twoFactorSettings['two_factor_enabled'] ?? false);
                $setupCompleted = (bool) ($twoFactorSettings['two_factor_setup_completed'] ?? false);

                $requires2FA = $userHas2FA || ($forcedTwoFactor && !$userHas2FA);

                $role = ($user['is_admin'] == 1) ? 'admin' : 'user';
                if (isset($user['role'])) {
                    unset($user['role']);
                }

                if (!$requires2FA || ($userHas2FA && !$setupCompleted)) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $role;
                    $_SESSION['is_admin'] = $user['is_admin'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];

                    if ($userHas2FA && !$setupCompleted) {
                        return [
                            'success' => true,
                            'role' => $role,
                            'user' => $user,
                            'requires_2fa_setup' => true,
                            'redirect' => '2fa-setup.html?email=' . urlencode($user['email'])
                        ];
                    }

                    if ($forcedTwoFactor && !$userHas2FA) {
                        return [
                            'success' => true,
                            'role' => $role,
                            'user' => $user,
                            'requires_2fa_setup' => true,
                            'forced_setup' => true,
                            'redirect' => '2fa-setup.html?email=' . urlencode($user['email']) . '&forced=1'
                        ];
                    }

                    return [
                        'success' => true,
                        'role' => $role,
                        'user' => $user,
                    ];
                } else {
                    $_SESSION['temp_user_data'] = [
                        'user' => $user,
                        'role' => $role,
                        'authenticated_at' => time()
                    ];

                    return [
                        'success' => true,
                        'requires_2fa_verification' => true,
                        'two_factor_method' => $twoFactorSettings['two_factor_method'],
                        'user_email' => $user['email']
                    ];
                }
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
                    'is_admin' => (int) ($user['is_admin'] ?? 0),
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
            $oldPassword = $data['old_password'] ?? null;
            $newPassword = $data['new_password'] ?? null;
            $confirmNewPassword = $data['confirm_new_password'] ?? null;

            $passwordFieldsFilled = (!empty($oldPassword) || !empty($newPassword) || !empty($confirmNewPassword));

            if ($passwordFieldsFilled) {
                if (empty($oldPassword) || empty($newPassword) || empty($confirmNewPassword)) {
                    $errors[] = 'Для изменения пароля необходимо заполнить все поля: old_password, new_password, confirm_new_password';
                } elseif ($newPassword !== $confirmNewPassword) {
                    $errors[] = 'Новый пароль и подтверждение не совпадают';
                } elseif (strlen($newPassword) < 6) {
                    $errors[] = 'Новый пароль должен содержать минимум 6 символов';
                } else {
                    $user = $this->userRepository->findById($userId);
                    if (!$user) {
                        return ['success' => false, 'error' => 'Пользователь не найден'];
                    }
                    $userWithPassword = $this->userRepository->findByEmailWithPassword($user['email']);
                    if (!$userWithPassword || !password_verify($oldPassword, $userWithPassword['password'])) {
                        $errors[] = 'Неверный текущий пароль';
                    }
                }
            }

            if (!empty($errors)) {
                return ['success' => false, 'error' => implode(', ', $errors)];
            }
            unset($data['old_password'], $data['new_password'], $data['confirm_new_password']);

            $success = $this->updateUser($userId, $data);

            if ($passwordFieldsFilled && $success) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $passwordUpdated = $this->userRepository->updatePassword($userId, $hashedPassword);
                if (!$passwordUpdated) {
                    return ['success' => false, 'error' => 'Ошибка при обновлении пароля'];
                }
            }

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
        try {
            if (isset($data['token']) && isset($data['password'])) {
                $token = $data['token'];
                $newPassword = $data['password'];
                $passwordConfirmation = $data['password_confirmation'] ?? '';
                return $this->resetPasswordWithToken($token, $newPassword, $passwordConfirmation);
            }

            if (isset($data['token']) && !isset($data['password'])) {
                return $this->validateResetToken($data['token']);
            }

            return $this->requestPasswordReset($data);
        } catch (RuntimeException $e) {
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

    private function promoteToAdminByEmail(array $data): array
    {
        $email = $data['email'] ?? '';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Некорректный email', 'status_code' => 400];
        }

        $adminCount = $this->userRepository->countAdmins();
        if ($adminCount > 0) {
            return ['success' => false, 'error' => 'Администратор уже существует.', 'admin_count' => $adminCount, 'status_code' => 403];
        }

        $user = $this->userRepository->findUserByEmail($email);
        if (!$user) {
            return ['success' => false, 'error' => 'Пользователь с таким email не найден.', 'status_code' => 404];
        }

        if ($user['is_admin']) {
            return ['success' => false, 'error' => 'Пользователь уже является администратором', 'status_code' => 400];
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

    public function createFirstAdmin(array $data): array
    {
        return $this->promoteToAdminByEmail($data);
    }

    public function requestPasswordReset(array $data): array
    {
        $email = $data['email'] ?? null;
        if (!$email) {
            return ['success' => false, 'error' => 'Email не указан'];
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            return ['success' => true, 'message' => 'Если пользователь с таким email существует, ему будет отправлена ссылка для сброса пароля.'];
        }

        $token = $this->passwordResetRepository->createToken($user['email']);
        if (!$token) {
            Logger::error("Failed to create password reset token", ['email' => $email]);
            return ['success' => false, 'error' => 'Не удалось создать токен для сброса пароля.'];
        }

        $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $this->emailService->sendPasswordResetEmail($user['email'], $token, $userName);

        return ['success' => true, 'message' => 'Если пользователь с таким email существует, ему будет отправлена ссылка для сброса пароля.'];
    }

    public function validateTwoFactorCode(string $code): bool
    {
        if (!isset($_SESSION['temp_user_data']['user']['id'])) {
            return false;
        }

        $userId = $_SESSION['temp_user_data']['user']['id'];
        $twoFactorSettings = $this->twoFactorRepository->getUserTwoFactorSettings($userId);

        if (!$twoFactorSettings || empty($twoFactorSettings['two_factor_method'])) {
            return false;
        }

        $method = $twoFactorSettings['two_factor_method'];

        if ($method === 'totp') {
            $secret = $twoFactorSettings['two_factor_secret'] ?? '';
            if (empty($secret)) {
                return false;
            }
            return $this->twoFactorService->verifyTOTP($secret, $code);
        } elseif ($method === 'email') {
            return $this->twoFactorRepository->verifyAndUseTwoFactorCode($userId, $code, 'email');
        } elseif ($method === 'backup') {
            $backupCodes = json_decode($twoFactorSettings['two_factor_backup_codes'] ?? '[]', true);
            return $this->twoFactorService->verifyBackupCode($backupCodes, $code);
        }

        return false;
    }

    public function validateResetToken(string $token): array
    {
        try {
            if (empty($token)) {
                return ['success' => false, 'error' => 'Токен не указан'];
            }

            $tokenData = $this->passwordResetRepository->findUserByToken($token);

            if (!$tokenData) {
                return ['success' => false, 'error' => 'Недействительный или просроченный токен'];
            }

            $tz = new \DateTimeZone($this->config['app']['timezone'] ?? 'UTC');

            $expiresRaw = $tokenData['expires_at'] ?? null;
            if ($expiresRaw === null) {
                throw new RuntimeException('Expires at not provided');
            }
            if (is_numeric($expiresRaw)) {
                $dt = new \DateTime('@' . (int) $expiresRaw);
            } else {
                $dt = new \DateTime($expiresRaw, new \DateTimeZone('UTC'));
            }
            $dt->setTimezone($tz);

            return [
                'success' => true,
                'data' => [
                    'email' => $tokenData['email'],
                    'user_name' => trim(($tokenData['first_name'] ?? '') . ' ' . ($tokenData['last_name'] ?? '')),
                    'expires_at' => $dt->format('Y-m-d H:i:s'),
                ],
            ];
        } catch (Exception $e) {
            Logger::error("Error validating password reset token", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при проверке токена'];
        }
    }
}
