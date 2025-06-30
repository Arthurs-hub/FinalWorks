<?php

namespace App\Services;

use App\Core\Db;
use App\Core\Logger;
use App\Repositories\UserRepository;
use App\Repositories\FileRepository;
use App\Services\UserService;
use Exception;
use PDO;

class AdminService
{
    private UserRepository $userRepository;
    private UserService $userService;
    private Db $db;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->userService = new UserService();
        $this->db = new Db();
    }

    public function getAdminStats(): array
    {
        try {

            $totalUsers = $this->userRepository->countUsers();
            $totalAdmins = $this->userRepository->countAdmins();


            $activeUsers30 = 0;
            $activeUsers7 = 0;

            try {
                $activeUsers30Days = $this->userRepository->getActiveUsers(30);
                $activeUsers30 = is_array($activeUsers30Days) ? count($activeUsers30Days) : 0;
            } catch (Exception $e) {
                Logger::warning("Failed to get active users (30 days)", ['error' => $e->getMessage()]);
            }

            try {
                $activeUsers7Days = $this->userRepository->getActiveUsers(7);
                $activeUsers7 = is_array($activeUsers7Days) ? count($activeUsers7Days) : 0;
            } catch (Exception $e) {
                Logger::warning("Failed to get active users (7 days)", ['error' => $e->getMessage()]);
            }

            $totalFiles = $this->getTotalFilesCount();
            $totalSize = $this->getTotalFilesSize();
            $totalDirectories = $this->getTotalDirectoriesCount();
            $totalShares = $this->getTotalSharesCount();


            $diskUsage = $this->getDiskUsageInfo();

            return [
                'users' => [
                    'total' => $totalUsers,
                    'admins' => $totalAdmins,
                    'regular' => $totalUsers - $totalAdmins,
                    'active_30_days' => $activeUsers30,
                    'active_7_days' => $activeUsers7
                ],
                'files' => [
                    'total_count' => $totalFiles,
                    'total_size' => $totalSize,
                    'total_size_formatted' => $this->formatFileSize($totalSize),
                    'total_directories' => $totalDirectories,
                    'total_shares' => $totalShares
                ],
                'system' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage' => $this->formatFileSize(memory_get_usage(true)),
                    'disk_usage' => $diskUsage['used_space_formatted'] ?? 'Н/Д'
                ]
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::getAdminStats error", [
                'error' => $e->getMessage()
            ]);


            return [
                'users' => [
                    'total' => 0,
                    'admins' => 0,
                    'regular' => 0,
                    'active_30_days' => 0,
                    'active_7_days' => 0
                ],
                'files' => [
                    'total_count' => 0,
                    'total_size' => 0,
                    'total_size_formatted' => '0 B',
                    'total_directories' => 0,
                    'total_shares' => 0
                ],
                'system' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage' => $this->formatFileSize(memory_get_usage(true)),
                    'disk_usage' => 'Н/Д'
                ]
            ];
        }
    }

    public function exportUsersToCSV(): array
    {
        try {
            $users = $this->userRepository->getAllUsersWithStats();

            if (empty($users)) {
                return [
                    'success' => false,
                    'error' => 'Нет пользователей для экспорта'
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
                'Получено расшариваний'
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
                    $user['received_shares_count'] ?? 0
                ];

                fputcsv($handle, $row, ';');
            }

            fclose($handle);

            return [
                'success' => true,
                'file_path' => $tempFile,
                'filename' => $filename,
                'users_count' => count($users)
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::exportUsersToCSV error", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Ошибка при экспорте: ' . $e->getMessage()
            ];
        }
    }

    private function getTotalFilesCount(): int
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM files");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            Logger::warning("Failed to get files count", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function getTotalFilesSize(): int
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT COALESCE(SUM(size), 0) as total_size FROM files");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total_size'] ?? 0);
        } catch (Exception $e) {
            Logger::warning("Failed to get files size", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function getTotalDirectoriesCount(): int
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM directories");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            Logger::warning("Failed to get directories count", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function getTotalSharesCount(): int
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM shared_items");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            Logger::warning("Failed to get shares count", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    public function cleanupOrphanedFiles(): array
    {
        try {
            $conn = $this->db->getConnection();
            $deletedCount = 0;

            $uploadsDir = __DIR__ . '/../uploads/files/';
            if (!is_dir($uploadsDir)) {
                return ['deleted_count' => 0];
            }

            $files = scandir($uploadsDir);
            $dbFiles = [];

            $stmt = $conn->prepare("SELECT stored_name FROM files");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dbFiles[] = $row['stored_name'];
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;

                if (!in_array($file, $dbFiles)) {
                    $filePath = $uploadsDir . $file;
                    if (is_file($filePath)) {
                        if (!unlink($filePath)) {
                            Logger::warning("Failed to delete orphaned file", ['file' => $file]);
                        } else {
                            $deletedCount++;
                            Logger::info("Orphaned file deleted", ['file' => $file]);
                        }
                    }
                }
            }

            foreach ($dbFiles as $dbFile) {
                $filePath = $uploadsDir . $dbFile;
                if (!file_exists($filePath)) {
                    $stmt = $conn->prepare("DELETE FROM files WHERE stored_name = ?");
                    $stmt->execute([$dbFile]);
                    Logger::info("Database record for missing file deleted", ['stored_name' => $dbFile]);
                }
            }

            return ['deleted_count' => $deletedCount];
        } catch (Exception $e) {
            Logger::error("AdminService::cleanupOrphanedFiles error", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

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
            $uploadsDir = __DIR__ . '/../uploads/';
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
                'usage_percentage' => $totalSpace > 0 ? round(($totalSpace - $freeSpace) / $totalSpace * 100, 2) : 0
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::getDiskUsageInfo error", [
                'error' => $e->getMessage()
            ]);
            return [
                'used_space' => 0,
                'used_space_formatted' => '0 B',
                'free_space' => 0,
                'free_space_formatted' => '0 B',
                'total_space' => 0,
                'total_space_formatted' => '0 B',
                'file_count' => 0,
                'usage_percentage' => 0
            ];
        }
    }

    public function deleteUser(int $userId): bool
    {
        try {
            return $this->userService->deleteUser($userId);
        } catch (Exception $e) {
            Logger::error("AdminService::deleteUser error", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function createUser(array $userData): int
    {
        try {

            $existingUser = $this->userRepository->findByEmail($userData['email']);
            if ($existingUser) {
                throw new Exception('Пользователь с таким email уже существует');
            }

            return $this->userService->createUser($userData);
        } catch (Exception $e) {
            Logger::error("AdminService::createUser error", [
                'error' => $e->getMessage(),
                'email' => $userData['email'] ?? 'unknown'
            ]);
            return 0;
        }
    }

    public function updateUser(int $userId, array $data): bool
    {
        try {

            $existingUser = $this->userRepository->findByEmail($data['email']);
            if ($existingUser && $existingUser['id'] != $userId) {
                throw new Exception('Пользователь с таким email уже существует');
            }

            return $this->userRepository->updateUser($userId, $data);
        } catch (Exception $e) {
            Logger::error("AdminService::updateUser error", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function validateUserData(array $data): array
    {
        return $this->userService->validateUserData($data);
    }

    public function getAllFiles(): array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT 
                    f.id,
                    f.filename,
                    f.stored_name,
                    f.mime_type,
                    f.size,
                    f.created_at,
                    u.email as owner_email,
                    u.first_name as owner_first_name,
                    u.last_name as owner_last_name
                FROM files f
                LEFT JOIN users u ON f.user_id = u.id
                ORDER BY f.created_at DESC
                LIMIT 1000
            ");
            $stmt->execute();
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($files as &$file) {
                $file['size_formatted'] = $this->formatFileSize((int)$file['size']);
            }

            return $files;
        } catch (Exception $e) {
            Logger::error("AdminService::getAllFiles error", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function deleteFile(int $fileId): bool
    {
        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("SELECT stored_name FROM files WHERE id = ?");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$file) {
                return false;
            }

            $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }


            $stmt = $conn->prepare("DELETE FROM shared_items WHERE item_type = 'file' AND item_id = ?");
            $stmt->execute([$fileId]);


            $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
            $result = $stmt->execute([$fileId]);

            Logger::info("File deleted by admin", [
                'file_id' => $fileId,
                'stored_name' => $file['stored_name']
            ]);

            return $result;
        } catch (Exception $e) {
            Logger::error("AdminService::deleteFile error", [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getSystemStats(): array
    {
        try {
            $conn = $this->db->getConnection();


            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
            $stmt->execute();
            $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE is_admin = 1");
            $stmt->execute();
            $totalAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(size), 0) as total_size FROM files");
            $stmt->execute();
            $filesStats = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM directories");
            $stmt->execute();
            $totalDirectories = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM shared_items");
            $stmt->execute();
            $totalShares = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'users' => [
                    'total' => (int)$totalUsers,
                    'admins' => (int)$totalAdmins
                ],
                'files' => [
                    'total_count' => (int)$filesStats['total'],
                    'total_size' => (int)$filesStats['total_size'],
                    'total_size_formatted' => $this->formatFileSize((int)$filesStats['total_size'])
                ],
                'directories' => [
                    'total' => (int)$totalDirectories
                ],
                'shares' => [
                    'total' => (int)$totalShares
                ]
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::getSystemStats error", [
                'error' => $e->getMessage()
            ]);
            return [
                'users' => ['total' => 0, 'admins' => 0],
                'files' => ['total_count' => 0, 'total_size' => 0, 'total_size_formatted' => '0 B'],
                'directories' => ['total' => 0],
                'shares' => ['total' => 0]
            ];
        }
    }

    public function getTopUsers(int $limit = 10): array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT 
                    u.id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    COUNT(f.id) as files_count,
                    COALESCE(SUM(f.size), 0) as total_size
                FROM users u
                LEFT JOIN files f ON u.id = f.user_id
                GROUP BY u.id, u.email, u.first_name, u.last_name
                ORDER BY total_size DESC, files_count DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($users as &$user) {
                $user['total_size_formatted'] = $this->formatFileSize((int)$user['total_size']);
            }

            return $users;
        } catch (Exception $e) {
            Logger::error("AdminService::getTopUsers error", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getRecentActivity(int $limit = 50): array
    {
        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("
                SELECT 
                    'file_upload' as activity_type,
                    f.created_at as activity_date,
                    f.filename as details,
                    u.email as user_email,
                    u.first_name,
                    u.last_name
                FROM files f
                JOIN users u ON f.user_id = u.id
                ORDER BY f.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $fileActivity = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmt = $conn->prepare("
                SELECT 
                    'directory_created' as activity_type,
                    d.created_at as activity_date,
                    d.name as details,
                    u.email as user_email,
                    u.first_name,
                    u.last_name
                FROM directories d
                JOIN users u ON d.user_id = u.id
                WHERE d.parent_id IS NOT NULL
                ORDER BY d.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $dirActivity = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $activity = array_merge($fileActivity, $dirActivity);
            usort($activity, function ($a, $b) {
                return strtotime($b['activity_date']) - strtotime($a['activity_date']);
            });

            return array_slice($activity, 0, $limit);
        } catch (Exception $e) {
            Logger::error("AdminService::getRecentActivity error", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getFileTypeStats(): array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT 
                    CASE 
                        WHEN mime_type LIKE 'image/%' THEN 'Изображения'
                        WHEN mime_type LIKE 'video/%' THEN 'Видео'
                        WHEN mime_type LIKE 'audio/%' THEN 'Аудио'
                        WHEN mime_type LIKE 'text/%' OR mime_type = 'application/json' THEN 'Текстовые файлы'
                        WHEN mime_type = 'application/pdf' THEN 'PDF документы'
                        WHEN mime_type LIKE 'application/vnd.ms-%' OR mime_type LIKE 'application/vnd.openxmlformats%' THEN 'Офисные документы'
                        WHEN mime_type LIKE 'application/zip' OR mime_type LIKE 'application/x-%' THEN 'Архивы'
                        ELSE 'Другие'
                    END as file_type,
                    COUNT(*) as count,
                    COALESCE(SUM(size), 0) as total_size
                FROM files
                GROUP BY file_type
                ORDER BY count DESC
            ");
            $stmt->execute();
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($stats as &$stat) {
                $stat['total_size_formatted'] = $this->formatFileSize((int)$stat['total_size']);
            }

            return $stats;
        } catch (Exception $e) {
            Logger::error("AdminService::getFileTypeStats error", [
                'error' => $e->getMessage()
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
                'top_sharers' => $topSharers
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::getSharesStats error", [
                'error' => $e->getMessage()
            ]);
            return [
                'by_type' => [],
                'top_sharers' => []
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
                'results' => $results
            ]);

            return $results;
        } catch (Exception $e) {
            Logger::error("AdminService::performMaintenance error", [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    private function cleanupOldSessions(): array
    {
        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("SHOW TABLES LIKE 'sessions'");
            $stmt->execute();

            if (!$stmt->fetch()) {
                return ['message' => 'Таблица sessions не найдена', 'deleted_count' => 0];
            }

            $stmt = $conn->prepare("DELETE FROM sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $deletedCount = $stmt->rowCount();

            return ['deleted_count' => $deletedCount];
        } catch (Exception $e) {
            Logger::error("AdminService::cleanupOldSessions error", [
                'error' => $e->getMessage()
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
                $cutoffTime = time() - (30 * 24 * 60 * 60); // 30 дней назад

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
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    private function optimizeDatabase(): array
    {
        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("SHOW TABLES");
            $stmt->execute();
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $optimizedTables = [];

            foreach ($tables as $table) {
                $stmt = $conn->prepare("OPTIMIZE TABLE `$table`");
                $stmt->execute();
                $optimizedTables[] = $table;
            }

            return [
                'optimized_tables' => $optimizedTables,
                'count' => count($optimizedTables)
            ];
        } catch (Exception $e) {
            Logger::error("AdminService::optimizeDatabase error", [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    public function backupDatabase(): array
    {
        try {
            $config = require __DIR__ . '/../config/config.php';

            $dsn = $config['db_dsn'];
            preg_match('/host=([^;]+)/', $dsn, $hostMatch);
            preg_match('/dbname=([^;]+)/', $dsn, $dbMatch);

            $host = $hostMatch[1] ?? 'localhost';
            $dbname = $dbMatch[1] ?? 'cloud_storage';
            $username = $config['db_user'];
            $password = $config['db_pass'];

            $backupDir = __DIR__ . '/../backups/';
            if (!is_dir($backupDir)) {
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
                    'file_size' => $fileSize
                ]);

                return [
                    'success' => true,
                    'backup_file' => basename($backupFile),
                    'file_size' => $this->formatFileSize($fileSize)
                ];
            } else {
                throw new Exception('Ошибка при создании резервной копии');
            }
        } catch (Exception $e) {
            Logger::error("AdminService::backupDatabase error", [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
