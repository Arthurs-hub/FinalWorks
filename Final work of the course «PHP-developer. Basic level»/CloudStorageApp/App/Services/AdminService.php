<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Db;
use App\Core\Logger;
use App\Core\Response;
use App\Repositories\IAdminRepository;
use App\Repositories\IUserRepository;
use Exception;
use PDO;

class AdminService
{
    private IUserRepository $userRepository;
    private IAdminRepository $adminRepository;
    private IUserService $userService;
    private Db $db;
    private array $config;

    public function __construct(
        IUserRepository $userRepository,
        IAdminRepository $adminRepository,
        IUserService $userService,
        Db $db,
        array $config
    )
    {
        $this->userRepository = $userRepository;
        $this->adminRepository = $adminRepository;
        $this->userService = $userService;
        $this->db = $db;
        $this->config = $config;
    }

    public function getAdminStats(): array
    {
        try {
            return $this->adminRepository->fetchAdminStats();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getDashboard(): array
    {
        try {
            $stats = $this->adminRepository->fetchAdminStats();
            return ['success' => true, 'stats' => $stats];
        } catch (Exception $e) {
            Logger::error("AdminService::getDashboard error", [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Ошибка при загрузке статистики'];
        }
    }

    public function getStats(): array
    {
        try {
            return [
                'success' => true,
                'stats' => $this->adminRepository->fetchAdminStats(),
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::getStats error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при загрузке статистики'];
        }
    }

    public function exportUsersToCSV(): array
    {
        try {

            $users = $this->userRepository->getAllUsersWithStats();

            if (empty($users)) {
                return [
                    'success' => false,
                    'error' => 'Нет пользователей для экспорта',
                ];
            }

            $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
            $tempFile = tempnam(sys_get_temp_dir(), 'users_export_');

            $handle = fopen($tempFile, 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            $headers = [
                'ID',
                'Email',
                'Имя',
                'Фамилия',
                'Отчество',
                'Возраст',
                'Пол',
                'Роль',
                'Дата регистрации',
                'Последний вход',
                'Количество файлов',
                'Общий размер файлов',
                'Количество папок',
                'Расшаренных файлов',
                'Получено расшариваний',
            ];

            fputcsv($handle, $headers, ';');

            foreach ($users as $user) {
                $row = [
                    $user['id'],
                    $user['email'],
                    $user['first_name'] ?? '',
                    $user['last_name'] ?? '',
                    $user['middle_name'] ?? '',
                    $user['age'] ?? '',
                    $this->formatGender($user['gender'] ?? ''),
                    $user['is_admin'] ? 'Администратор' : 'Пользователь',
                    $user['created_at'] ? date('d.m.Y H:i', strtotime($user['created_at'])) : '',
                    $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Никогда',
                    $user['files_count'] ?? 0,
                    $this->formatFileSize((int)($user['total_size'] ?? 0)),
                    $user['directories_count'] ?? 0,
                    $user['shared_files_count'] ?? 0,
                    $user['received_shares_count'] ?? 0,
                ];

                fputcsv($handle, $row, ';');
            }

            fclose($handle);

            return [
                'success' => true,
                'file_path' => $tempFile,
                'filename' => $filename,
                'users_count' => count($users),
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::exportUsersToCSV error", ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Ошибка при экспорте: ' . $e->getMessage(),
            ];
        }
    }

    public function cleanupOrphanedFiles(): array
    {
        try {
            return $this->adminRepository->cleanupOrphanedFiles();
        } catch (Exception $e) {
            Logger::error("AdminService::cleanupOrphanedFiles error", ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    public function getUsersExportFile(): array
    {
        return $this->userRepository->exportUsersToCSV();
    }

    private function formatFileSize(int|float $bytes): string
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

    private function formatGender(string $gender): string
    {
        switch ($gender) {
            case 'male':
                return 'Мужской';
            case 'female':
                return 'Женский';
            default:
                return 'Не указан';
        }
    }

    public function getDiskUsage(): array
    {
        return $this->getDiskUsageInfo();
    }

    private function getDiskUsageInfo(): array
    {
        try {
            $uploadsDir = $this->config['app']['upload_path'];
            $totalSize = 0;
            $fileCount = 0;

            if (is_dir($uploadsDir)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($uploadsDir)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $totalSize += $file->getSize();
                        $fileCount++;
                    }
                }
            }

            $freeSpace = disk_free_space($uploadsDir);
            $totalSpace = disk_total_space($uploadsDir);

            return [
                'used_space' => $totalSize,
                'used_space_formatted' => $this->formatFileSize($totalSize),
                'free_space' => $freeSpace,
                'free_space_formatted' => $this->formatFileSize($freeSpace),
                'total_space' => $totalSpace,
                'total_space_formatted' => $this->formatFileSize($totalSpace),
                'file_count' => $fileCount,
                'usage_percentage' => $totalSpace > 0 ? round(($totalSpace - $freeSpace) / $totalSpace * 100, 2) : 0,
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::getDiskUsageInfo error", [
                'error' => $e->getMessage(),
            ]);

            return [
                'used_space' => 0,
                'used_space_formatted' => '0 B',
                'free_space' => 0,
                'free_space_formatted' => '0 B',
                'total_space' => 0,
                'total_space_formatted' => '0 B',
                'file_count' => 0,
                'usage_percentage' => 0,
            ];
        }
    }

    public function getSystemHealth(): array
    {
        try {

            $systemData = $this->userService->getSystemHealth();

            $health = [
                'status' => 'healthy',
                'checks' => [],
                'timestamp' => date('Y-m-d H:i:s')
            ];

            try {
                $conn = $this->db->getConnection();
                $stmt = $conn->query("SELECT 1");
                $health['checks']['database'] = [
                    'status' => 'ok',
                    'message' => 'Подключение к базе данных работает'
                ];
            } catch (Exception $e) {
                $health['status'] = 'unhealthy';
                $health['checks']['database'] = [
                    'status' => 'error',
                    'message' => 'Ошибка подключения к базе данных: ' . $e->getMessage()
                ];
            }

            $configUploadDir = $this->config['app']['upload_path'] ?? '';
            $appUploadDir = dirname(__DIR__) . '/uploads/'; 

            $candidates = [];
            if (is_string($configUploadDir) && $configUploadDir !== '') {
                $candidates[] = rtrim($configUploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
            $candidates[] = rtrim($appUploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            $uploadsOk = false;
            $checkedPaths = [];
            foreach ($candidates as $dir) {
                $checkedPaths[] = $dir;
                if (is_dir($dir) && is_writable($dir)) {
                    $uploadsOk = true;
                    break;
                }
            }

            if ($uploadsOk) {
                $health['checks']['uploads_directory'] = [
                    'status' => 'ok',
                    'message' => 'Директория uploads доступна для записи'
                ];
            } else {
                $health['status'] = 'unhealthy';
                $health['checks']['uploads_directory'] = [
                    'status' => 'error',
                    'message' => 'Директория uploads недоступна или не доступна для записи'
                ];
            }

            $logsDir = __DIR__ . '/../logs/';
            if (is_dir($logsDir) && is_writable($logsDir)) {
                $health['checks']['logs_directory'] = [
                    'status' => 'ok',
                    'message' => 'Директория logs доступна для записи'
                ];
            } else {
                $health['checks']['logs_directory'] = [
                    'status' => 'warning',
                    'message' => 'Директория logs недоступна или не доступна для записи'
                ];
            }

            $freeSpace = disk_free_space(__DIR__);
            $minFreeSpace = 100 * 1024 * 1024;

            if ($freeSpace > $minFreeSpace) {
                $health['checks']['disk_space'] = [
                    'status' => 'ok',
                    'message' => 'Достаточно свободного места на диске',
                    'free_space' => $this->formatFileSize($freeSpace)
                ];
            } else {
                $health['status'] = 'warning';
                $health['checks']['disk_space'] = [
                    'status' => 'warning',
                    'message' => 'Мало свободного места на диске',
                    'free_space' => $this->formatFileSize($freeSpace)
                ];
            }

            if (isset($systemData['system'])) {
                $system = $systemData['system'];

                if (isset($system['memory_usage_percent']) && $system['memory_usage_percent'] !== null) {
                    if ($system['memory_usage_percent'] < 80) {
                        $health['checks']['memory_usage'] = [
                            'status' => 'ok',
                            'message' => 'Использование памяти в норме',
                            'usage' => $system['memory_usage_formatted'] ?? 'Н/Д',
                            'percent' => $system['memory_usage_percent'] . '%'
                        ];
                    } else {
                        $health['status'] = 'warning';
                        $health['checks']['memory_usage'] = [
                            'status' => 'warning',
                            'message' => 'Высокое использование памяти',
                            'usage' => $system['memory_usage_formatted'] ?? 'Н/Д',
                            'percent' => $system['memory_usage_percent'] . '%'
                        ];
                    }
                }
            }

            return ['success' => true, 'health' => $health];
        } catch (Exception $e) {
            Logger::error("AdminService::getSystemHealth error", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Ошибка при проверке состояния системы',
                'health' => [
                    'status' => 'error',
                    'message' => 'Ошибка при проверке состояния системы: ' . $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
        }
    }

    public function createUser(array $data): array
    {
        return $this->adminRepository->createUserWithValidation($data);
    }

    public function validateUserData(array $data): array
    {
        return $this->userService->validateUserData($data);
    }

    public function getAllFiles(): array
    {
        try {

            $files = $this->adminRepository->fetchAllFiles();

            foreach ($files as &$file) {
                $file['size_formatted'] = $this->formatFileSize((int)$file['size']);
            }

            return $files;
        } catch (Exception $e) {
            Logger::error("AdminService::getAllFiles error", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getUsers(): array
    {
        try {
            $users = $this->adminRepository->fetchUsers();
            return ['success' => true, 'users' => $users];
        } catch (Exception $e) {
            Logger::error("AdminService::getUsers error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при загрузке пользователей'];
        }
    }

    public function getUser(int $userId): array
    {
        try {
            $user = $this->adminRepository->fetchUserById($userId);
            return ['success' => true, 'user' => $user];
        } catch (Exception $e) {
            Logger::error("AdminService::getUser error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при загрузке пользователя'];
        }
    }

    public function getCurrentUser(): array
    {
        $currentUser = $this->adminRepository->fetchCurrentUser();
        return ['success' => true, 'user' => $currentUser];
    }

    public function getSystemStats(): array
    {
        try {

            $stats = $this->adminRepository->fetchSystemStats();

            return $stats;
        } catch (Exception $e) {
            Logger::error("AdminService::getSystemStats error", [
                'error' => $e->getMessage(),
            ]);

            return [
                'users' => ['total' => 0, 'admins' => 0],
                'files' => ['total_count' => 0, 'total_size' => 0, 'total_size_formatted' => '0 B'],
                'directories' => ['total' => 0],
                'shares' => ['total' => 0],
            ];
        }
    }

    public function getTopUsers(int $limit = 10): array
    {
        try {

            $users = $this->adminRepository->fetchTopUsers($limit);

            foreach ($users as &$user) {
                $user['total_size_formatted'] = $this->formatFileSize((int)$user['total_size']);
            }

            return $users;
        } catch (Exception $e) {
            Logger::error("AdminService::getTopUsers error", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getRecentActivity(int $limit = 50): array
    {
        try {

            $activity = $this->adminRepository->fetchRecentActivity($limit);

            return $activity;
        } catch (Exception $e) {
            Logger::error("AdminService::getRecentActivity error", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getFileTypeStats(): array
    {
        try {

            $stats = $this->adminRepository->fetchFileTypeStats();

            foreach ($stats as &$stat) {
                $stat['total_size_formatted'] = $this->formatFileSize((int)$stat['total_size']);
            }

            return $stats;
        } catch (Exception $e) {
            Logger::error("AdminService::getFileTypeStats error", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getSharesStats(): array
    {
        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("
                SELECT 
                    item_type,
                    COUNT(*) as count
                FROM shared_items
                GROUP BY item_type
            ");
            $stmt->execute();
            $typeStats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmt = $conn->prepare("
                SELECT 
                    u.email,
                    u.first_name,
                    u.last_name,
                    COUNT(si.id) as shares_count
                FROM users u
                JOIN shared_items si ON u.id = si.shared_by_user_id
                GROUP BY u.id, u.email, u.first_name, u.last_name
                ORDER BY shares_count DESC
                LIMIT 10
            ");
            $stmt->execute();
            $topSharers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return [
                'by_type' => $typeStats,
                'top_sharers' => $topSharers,
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::getSharesStats error", [
                'error' => $e->getMessage(),
            ]);

            return [
                'by_type' => [],
                'top_sharers' => [],
            ];
        }
    }

    public function performMaintenance(): array
    {
        $results = [];

        try {

            $cleanupResult = $this->cleanupOrphanedFiles();
            $results['cleanup_files'] = $cleanupResult;

            $results['cleanup_sessions'] = $this->cleanupOldSessions();

            $results['cleanup_logs'] = $this->cleanupOldLogs();

            $results['optimize_database'] = $this->optimizeDatabase();

            Logger::info("Maintenance performed", [
                'results' => $results,
            ]);

            return $results;
        } catch (Exception $e) {
            Logger::error("AdminService::performMaintenance error", [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    private function cleanupOldSessions(): array
    {
        try {

            return $this->adminRepository->cleanupOldSessions();
        } catch (Exception $e) {
            Logger::error("AdminService::cleanupOldSessions error", [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    private function cleanupOldLogs(): array
    {
        try {
            $logsDir = __DIR__ . '/../logs/';
            $deletedCount = 0;

            if (is_dir($logsDir)) {
                $files = glob($logsDir . '*.log');
                $cutoffTime = time() - (30 * 24 * 60 * 60);

                foreach ($files as $file) {
                    if (filemtime($file) < $cutoffTime) {
                        unlink($file);
                        $deletedCount++;
                    }
                }
            }

            return ['deleted_count' => $deletedCount];
        } catch (Exception $e) {
            Logger::error("AdminService::cleanupOldLogs error", [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    private function optimizeDatabase(): array
    {
        try {

            return $this->adminRepository->optimizeDatabase();
        } catch (Exception $e) {
            Logger::error("AdminService::optimizeDatabase error", [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    public function backupDatabase(): array
    {
        try {
            $dbConfig = $this->config['database'];

            $host = $dbConfig['host'];
            $dbname = $dbConfig['dbname'];
            $username = $dbConfig['username'];
            $password = $dbConfig['password'];

            $backupDir = __DIR__ . '/../backups/';
            if (! is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $backupFile = $backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';

            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s %s > %s',
                escapeshellarg($host),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($dbname),
                escapeshellarg($backupFile)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($backupFile)) {
                $fileSize = filesize($backupFile);

                Logger::info("Database backup created", [
                    'backup_file' => $backupFile,
                    'file_size' => $fileSize,
                ]);

                return [
                    'success' => true,
                    'backup_file' => basename($backupFile),
                    'file_size' => $this->formatFileSize($fileSize),
                ];
            } else {
                throw new Exception('Ошибка при создании резервной копии');
            }
        } catch (Exception $e) {
            Logger::error("AdminService::backupDatabase error", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function updateUser(?int $userId, array $data): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        $data = is_string($data) ? json_decode($data, true) : $data;

        if (!is_array($data)) {
            return ['success' => false, 'error' => 'Неверный формат данных'];
        }

        try {
            $result = $this->adminRepository->updateUserData($userId, $data);
            return [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Данные успешно обновлены' : null,
                'error' => $result['success'] ? null : ($result['error'] ?? 'Ошибка при обновлении пользователя'),
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::updateUser error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при обновлении пользователя'];
        }
    }

    public function deleteUser(?int $userId, ?int $currentUserId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        if ($userId == $currentUserId) {
            return ['success' => false, 'error' => 'Нельзя удалить самого себя'];
        }

        try {
            $success = $this->userService->deleteUser($userId);
            if ($success) {
                Logger::info("User deleted by admin", ['deleted_user_id' => $userId, 'admin_id' => $currentUserId]);
                return ['success' => true, 'message' => 'Пользователь успешно удален'];
            }
            return ['success' => false, 'error' => 'Пользователь не найден'];
        } catch (Exception $e) {
            Logger::error("AdminService::deleteUser error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при удалении пользователя'];
        }
    }

    public function banUser(?int $userId, ?int $currentUserId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        if ($userId == $currentUserId) {
            return ['success' => false, 'error' => 'Нельзя заблокировать самого себя'];
        }

        try {
            $success = $this->userService->banUser((int)$userId);
            if ($success) {
                Logger::info("User banned by admin", ['banned_user_id' => $userId, 'admin_id' => $currentUserId]);
                return ['success' => true, 'message' => 'Пользователь заблокирован'];
            }
            return ['success' => false, 'error' => 'Пользователь не найден'];
        } catch (Exception $e) {
            Logger::error("AdminService::banUser error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при блокировке пользователя'];
        }
    }

    public function unbanUser(?int $userId, ?int $currentUserId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        try {
            $success = $this->userService->unbanUser((int)$userId);
            if ($success) {
                Logger::info("User unbanned by admin", ['unbanned_user_id' => $userId, 'admin_id' => $currentUserId]);
                return ['success' => true, 'message' => 'Пользователь разблокирован'];
            }
            return ['success' => false, 'error' => 'Пользователь не найден'];
        } catch (Exception $e) {
            Logger::error("AdminService::unbanUser error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при разблокировке пользователя'];
        }
    }

    public function makeAdmin(?int $userId, ?int $currentUserId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        try {
            $success = $this->userService->makeAdmin((int)$userId);
            if ($success) {
                Logger::info("User promoted to admin", ['promoted_user_id' => $userId, 'admin_id' => $currentUserId]);
                return ['success' => true, 'message' => 'Пользователь назначен администратором'];
            }
            return ['success' => false, 'error' => 'Пользователь не найден'];
        } catch (Exception $e) {
            Logger::error("AdminService::makeAdmin error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при назначении администратора'];
        }
    }

    public function removeAdmin(?int $userId, ?int $currentUserId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        if ($userId == $currentUserId) {
            return ['success' => false, 'error' => 'Нельзя снять права администратора с самого себя'];
        }

        try {
            $user = $this->userService->findUserById($userId);
            if (!$user || !$user['is_admin']) {
                return ['success' => false, 'error' => 'Пользователь не является администратором'];
            }

            $success = $this->userService->removeAdmin((int)$userId);
            if ($success) {
                Logger::info("Admin rights removed", ['demoted_user_id' => $userId, 'admin_id' => $currentUserId]);
                return ['success' => true, 'message' => 'Права администратора сняты'];
            }
            return ['success' => false, 'error' => 'Пользователь не найден'];
        } catch (Exception $e) {
            Logger::error("AdminService::removeAdmin error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при снятии прав администратора'];
        }
    }

    public function getFiles(): array
    {
        try {
            $files = $this->getAllFiles();
            return ['success' => true, 'files' => $files];
        } catch (Exception $e) {
            Logger::error("AdminService::getFiles error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при загрузке файлов'];
        }
    }

    public function deleteFile(?int $fileId, ?int $currentUserId): array
    {
        if (!$fileId) {
            return ['success' => false, 'error' => 'ID файла не указан'];
        }

        try {
            $success = $this->adminRepository->deleteFile($fileId);
            if ($success) {
                Logger::info("File deleted by admin", ['file_id' => $fileId, 'admin_id' => $currentUserId]);
                return ['success' => true, 'message' => 'Файл успешно удален'];
            }
            return ['success' => false, 'error' => 'Файл не найден'];
        } catch (Exception $e) {
            Logger::error("AdminService::deleteFile error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при удалении файла'];
        }
    }

    public function clearFiles(?int $currentUserId): array
    {
        try {
            $deletedCount = $this->adminRepository->deleteAllFiles();

            Logger::info("All files deleted by admin", [
                'deleted_count' => $deletedCount,
                'admin_id' => $currentUserId,
            ]);

            return [
                'success' => true,
                'message' => "Удалено файлов: $deletedCount",
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::clearFiles error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при удалении файлов: ' . $e->getMessage()];
        }
    }

    public function getLogs(string $level, int $limit): array
    {
        try {
            $logs = Logger::getRecentLogs($limit, $level);
            return ['success' => true, 'logs' => $logs];
        } catch (Exception $e) {
            Logger::error("AdminService::getLogs error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при загрузке логов'];
        }
    }

    public function clearLogs(?int $currentUserId): array
    {
        try {
            $success = Logger::clearLogs();
            if ($success) {
                Logger::info("Logs cleared by admin", ['admin_id' => $currentUserId]);
                return ['success' => true, 'message' => 'Логи успешно очищены'];
            }
            return ['success' => false, 'error' => 'Ошибка при очистке логов'];
        } catch (Exception $e) {
            Logger::error("AdminService::clearLogs error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при очистке логов'];
        }
    }

    public function getSecurityReport(): array
    {
        try {
            $report = $this->userService->getSecurityReport();
            return ['success' => true, 'report' => $report];
        } catch (Exception $e) {
            Logger::error("AdminService::getSecurityReport error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при генерации отчета безопасности'];
        }
    }

    public function exportUserData(?int $userId, ?int $currentUserId): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        try {
            $userData = $this->userService->exportUserData((int)$userId);
            Logger::info("User data exported by admin", [
                'exported_user_id' => $userId,
                'admin_id' => $currentUserId,
            ]);
            return ['success' => true, 'data' => $userData];
        } catch (Exception $e) {
            Logger::error("AdminService::exportUserData error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при экспорте данных пользователя'];
        }
    }

    public function bulkDeleteUsers(array $data, ?int $currentUserId): array
    {
        $userIds = $data['user_ids'] ?? [];

        if (empty($userIds) || !is_array($userIds)) {
            return ['success' => false, 'error' => 'Не указаны ID пользователей для удаления'];
        }

        $userIds = array_filter($userIds, fn($id) => $id != $currentUserId);

        try {
            $results = $this->userService->bulkDeleteUsers($userIds);
            Logger::info("Bulk user deletion performed by admin", [
                'total' => $results['total'],
                'success_count' => count($results['success']),
                'failed_count' => count($results['failed']),
                'admin_id' => $currentUserId,
            ]);
            return [
                'success' => true,
                'message' => 'Массовое удаление завершено',
                'results' => $results,
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::bulkDeleteUsers error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при массовом удалении пользователей'];
        }
    }

    public function searchUsers(string $query): array
    {
        if (empty($query)) {
            return ['success' => false, 'error' => 'Поисковый запрос не указан'];
        }

        try {
            $users = $this->userService->searchUsers($query);
            return ['success' => true, 'users' => $users];
        } catch (Exception $e) {
            Logger::error("AdminService::searchUsers error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при поиске пользователей'];
        }
    }

    public function getUserActivity(?int $userId, int $days): array
    {
        if (!$userId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        try {
            $activity = $this->userService->getUserActivity($userId, $days);
            return ['success' => true, 'activity' => $activity];
        } catch (Exception $e) {
            Logger::error("AdminService::getUserActivity error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при получении активности пользователя'];
        }
    }

    public function resetUserPassword(int $targetUserId, ?int $currentUserId): array
    {
        if (!$targetUserId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        if ($targetUserId === $currentUserId) {
            return ['success' => false, 'error' => 'Нельзя сбросить пароль самому себе'];
        }

        try {
            $tempPassword = $this->userService->resetPasswordByAdmin($targetUserId);
            Logger::info("Password reset by admin", [
                'admin_id' => $currentUserId,
                'target_user_id' => $targetUserId,
            ]);
            return [
                'success' => true,
                'message' => 'Пароль успешно сброшен',
                'temp_password' => $tempPassword,
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::resetUserPassword error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при сбросе пароля'];
        }
    }

    public function getActiveUsers(int $days): array
    {
        try {
            $days = max(1, min(365, $days));
            $activeUsers = $this->userService->getActiveUsers($days);
            return [
                'success' => true,
                'active_users' => $activeUsers,
                'days' => $days,
                'count' => count($activeUsers),
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::getActiveUsers error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при получении активных пользователей'];
        }
    }

    public function exportUsers(?int $currentUserId): Response
    {
        try {
            $fileInfo = $this->getUsersExportFile();

            if (!file_exists($fileInfo['file_path'])) {
                return new Response(['success' => false, 'error' => 'Файл не найден'], 404);
            }

            header('Content-Type: ' . ($fileInfo['content_type'] ?? 'application/octet-stream'));
            header('Content-Disposition: attachment; filename="' . basename($fileInfo['filename']) . '"');
            header('Content-Length: ' . filesize($fileInfo['file_path']));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($fileInfo['file_path']);
            unlink($fileInfo['file_path']);
            exit;
        } catch (Exception $e) {
            Logger::error("AdminService::exportUsers error", [
                'error' => $e->getMessage(),
                'admin_id' => $currentUserId,
            ]);
            return new Response(['success' => false, 'error' => 'Ошибка при экспорте пользователей'], 500);
        }
    }
}
