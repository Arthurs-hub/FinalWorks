<?php

namespace App\Repositories;

use App\Core\Validator;
use App\Core\Db;
use App\Core\Logger;
use App\Repositories\UserRepository;
use Exception;
use PDO;


class AdminRepository
{
    private Db $db;
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->db = new Db();
        $this->userRepository = new UserRepository();
    }

    public function fetchAdminStats(): array
    {
        try {
            $conn = $this->db->getConnection();

            $totalUsers = (int) $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();

            $totalAdmins = (int) $conn->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();

            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $activeUsers30 = (int) $stmt->fetchColumn();

            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $activeUsers7 = (int) $stmt->fetchColumn();

            $totalFiles = (int) $conn->query("SELECT COUNT(*) FROM files")->fetchColumn();

            $totalSize = (int) $conn->query("SELECT COALESCE(SUM(size), 0) FROM files")->fetchColumn();

            $totalDirectories = (int) $conn->query("SELECT COUNT(*) FROM directories")->fetchColumn();

            $totalShares = (int) $conn->query("SELECT COUNT(*) FROM shared_items")->fetchColumn();

            $memoryUsageBytes = memory_get_usage(true);
            if (!is_int($memoryUsageBytes) || $memoryUsageBytes < 0) {
                $memoryUsageBytes = 0;
            }

            $diskUsage = $this->getDiskUsageInfo();

            return [
                'users' => [
                    'total' => $totalUsers,
                    'admins' => $totalAdmins,
                    'regular' => $totalUsers - $totalAdmins,
                    'active_30_days' => $activeUsers30,
                    'active_7_days' => $activeUsers7,
                ],
                'files' => [
                    'total_count' => $totalFiles,
                    'total_size' => $totalSize,
                    'total_size_formatted' => $this->formatFileSize($totalSize),
                    'total_directories' => $totalDirectories,
                    'total_shares' => $totalShares,
                ],
                'system' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage_formatted' => $this->formatFileSize(memory_get_usage(true)),
                    'disk_usage' => $diskUsage['used_space_formatted'] ?? 'Н/Д',
                ],
            ];
        } catch (Exception $e) {
            Logger::error("AdminRepository::fetchAdminStats error", [
                'error' => $e->getMessage(),
            ]);

            return [
                'users' => [
                    'total' => 0,
                    'admins' => 0,
                    'regular' => 0,
                    'active_30_days' => 0,
                    'active_7_days' => 0,
                ],
                'files' => [
                    'total_count' => 0,
                    'total_size' => 0,
                    'total_size_formatted' => '0 B',
                    'total_directories' => 0,
                    'total_shares' => 0,
                ],
                'system' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage_formatted' => '0 B',
                    'disk_usage' => 'Н/Д',
                ],
            ];
        }
    }

    public function fetchSystemStats(): array
    {
        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
            $stmt->execute();
            $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE is_admin = 1");
            $stmt->execute();
            $totalAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            $stmt = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(size), 0) as total_size FROM files");
            $stmt->execute();
            $filesStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'total_size' => 0];

            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM directories");
            $stmt->execute();
            $totalDirectories = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM shared_items");
            $stmt->execute();
            $totalShares = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            return [
                'users' => [
                    'total' => (int)$totalUsers,
                    'admins' => (int)$totalAdmins,
                ],
                'files' => [
                    'total_count' => (int)$filesStats['total'],
                    'total_size' => (int)$filesStats['total_size'],
                    'total_size_formatted' => $this->formatFileSize((int)$filesStats['total_size']),
                ],
                'directories' => [
                    'total' => (int)$totalDirectories,
                ],
                'shares' => [
                    'total' => (int)$totalShares,
                ],
            ];
        } catch (Exception $e) {
            Logger::error("AdminRepository::fetchSystemStats error", [
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

    public function fetchUsers(): array
    {
        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("
                SELECT 
                    u.id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.middle_name,
                    u.age,
                    u.gender,
                    u.is_admin,
                    u.is_banned,
                    u.created_at,
                    u.last_login,
                    COUNT(f.id) as files_count,
                    COALESCE(SUM(f.size), 0) as total_size,
                    (SELECT COUNT(*) FROM directories d WHERE d.user_id = u.id) as directories_count,
                    (SELECT COUNT(*) FROM shared_items si WHERE si.shared_by_user_id = u.id) as shared_files_count,
                    (SELECT COUNT(*) FROM shared_items si WHERE si.shared_with_user_id = u.id) as received_shares_count
                FROM users u
                LEFT JOIN files f ON u.id = f.user_id
                GROUP BY u.id, u.email, u.first_name, u.last_name, u.middle_name, u.age, u.gender, u.is_admin, u.is_banned, u.created_at, u.last_login
                ORDER BY u.created_at DESC
                LIMIT 1000
            ");

            $stmt->execute();

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $users;
        } catch (Exception $e) {
            Logger::error("AdminRepository::fetchUsers error", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function fetchUserById(int $userId): ?array
    {
        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("
                SELECT 
                    u.id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.middle_name,
                    u.age,
                    u.gender,
                    u.is_admin,
                    u.is_banned,
                    u.created_at,
                    u.last_login,
                    COUNT(f.id) as files_count,
                    COALESCE(SUM(f.size), 0) as total_size,
                    (SELECT COUNT(*) FROM directories d WHERE d.user_id = u.id) as directories_count,
                    (SELECT COUNT(*) FROM shared_items si WHERE si.shared_by_user_id = u.id) as shared_files_count,
                    (SELECT COUNT(*) FROM shared_items si WHERE si.shared_with_user_id = u.id) as received_shares_count
                FROM users u
                LEFT JOIN files f ON u.id = f.user_id
                WHERE u.id = ?
                GROUP BY u.id, u.email, u.first_name, u.last_name, u.middle_name, u.age, u.gender, u.is_admin, u.is_banned, u.created_at, u.last_login
                LIMIT 1
            ");

            $stmt->execute([$userId]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user ?: null;
        } catch (Exception $e) {
            Logger::error("AdminRepository::fetchUserById error", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function updateUserData(int $userId, array $data): array
    {
        try {
            if (isset($data['email'])) {
                try {
                    Validator::email($data['email']);
                } catch (\InvalidArgumentException $e) {
                    return ['success' => false, 'error' => 'Неверный email: ' . $e->getMessage()];
                }

                $existingUser = $this->userRepository->findUserByEmail($data['email']);
                if ($existingUser && $existingUser['id'] != $userId) {
                    return ['success' => false, 'error' => 'Пользователь с таким email уже существует'];
                }
            }

            if (isset($data['password']) && $data['password'] !== '') {
                try {
                    Validator::maxLength($data['password'], 255, 'Пароль');
                } catch (\InvalidArgumentException $e) {
                    return ['success' => false, 'error' => 'Ошибка пароля: ' . $e->getMessage()];
                }
            }

            $updated = $this->userRepository->updateUser($userId, $data);

            if ($updated) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Ошибка при обновлении пользователя'];
            }
        } catch (Exception $e) {
            Logger::error("AdminRepository::updateUserData error", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Ошибка при обновлении пользователя: ' . $e->getMessage()];
        }
    }

    public function validateUserData(array $data): array
    {
        $errors = [];

        try {
            Validator::required($data['email'] ?? '', 'Email');
            Validator::email($data['email']);
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            Validator::required($data['first_name'] ?? '', 'Имя');
            Validator::maxLength($data['first_name'], 50, 'Имя');
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            Validator::required($data['last_name'] ?? '', 'Фамилия');
            Validator::maxLength($data['last_name'], 50, 'Фамилия');
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        if (isset($data['password']) && $data['password'] !== '') {
            try {
                Validator::maxLength($data['password'], 255, 'Пароль');
            } catch (\InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (isset($data['confirm_password']) && $data['password'] !== ($data['confirm_password'] ?? null)) {
            $errors[] = 'Пароли не совпадают';
        }

        return $errors;
    }

    public function createUser(array $data): int
    {
        try {
            $existingUser = $this->userRepository->findUserByEmail($data['email'] ?? '');
            if ($existingUser) {
                throw new Exception('Пользователь с таким email уже существует');
            }

            $insertData = [
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'middle_name' => $data['middle_name'] ?? '',
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'age' => isset($data['age']) ? (int)$data['age'] : null,
                'gender' => $data['gender'] ?? '',
                'is_admin' => !empty($data['is_admin']) ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $userId = $this->userRepository->create($insertData);

            if ($userId) {
                Logger::info("User created by admin", [
                    'created_user_id' => $userId,
                    'email' => $data['email'] ?? '',
                ]);
            }

            return $userId;
        } catch (Exception $e) {
            Logger::error("AdminRepository::createUser error", [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? '',
            ]);

            return 0;
        }
    }

    public function createUserWithValidation(array $data): array
    {
        try {

            Validator::required($data['email'] ?? '', 'Email');
            Validator::email($data['email']);
            Validator::required($data['password'] ?? '', 'Пароль');

            if (isset($data['confirm_password']) && $data['password'] !== $data['confirm_password']) {
                return ['success' => false, 'errors' => ['Пароли не совпадают']];
            }

            $existingUser = $this->userRepository->findUserByEmail($data['email']);
            if ($existingUser) {
                return ['success' => false, 'errors' => ['Пользователь с таким email уже существует']];
            }

            $insertData = [
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'middle_name' => $data['middle_name'] ?? '',
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'age' => isset($data['age']) ? (int)$data['age'] : null,
                'gender' => $data['gender'] ?? '',
                'is_admin' => !empty($data['is_admin']) ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $userId = $this->userRepository->create($insertData);

            if ($userId) {
                Logger::info("User created by admin", [
                    'created_user_id' => $userId,
                    'email' => $data['email'],
                ]);

                return ['success' => true, 'user_id' => $userId, 'message' => 'Пользователь успешно создан'];
            } else {
                return ['success' => false, 'errors' => ['Ошибка при создании пользователя']];
            }
        } catch (Exception $e) {
            Logger::error("AdminRepository::createUserWithValidation error", [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? '',
            ]);

            return ['success' => false, 'errors' => [$e->getMessage() ?: 'Ошибка при создании пользователя']];
        }
    }

    public function fetchTopUsers(int $limit = 10): array
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
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $users;
        } catch (Exception $e) {
            Logger::error("AdminRepository::fetchTopUsers error", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function fetchRecentActivity(int $limit = 50): array
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
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
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
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $dirActivity = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $activity = array_merge($fileActivity, $dirActivity);
            usort($activity, function ($a, $b) {
                return strtotime($b['activity_date']) - strtotime($a['activity_date']);
            });

            return array_slice($activity, 0, $limit);
        } catch (Exception $e) {
            Logger::error("AdminRepository::fetchRecentActivity error", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function banUser(int $userId): bool
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
        $stmt->execute([$userId]);

        return $stmt->rowCount() > 0;
    }

    public function deleteUserById(int $userId): bool
    {
        $conn = $this->db->getConnection();

        try {
            $conn->beginTransaction();

            $user = $this->userRepository->findById($userId);
            if (!$user) {
                $conn->rollBack();
                return false;
            }

            $stmt = $conn->prepare("DELETE FROM shared_items WHERE shared_by_user_id = ? OR shared_with_user_id = ?");
            $stmt->execute([$userId, $userId]);

            $stmt = $conn->prepare("DELETE FROM files WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $conn->prepare("DELETE FROM directories WHERE user_id = ?");
            $stmt->execute([$userId]);

            $deleted = $this->userRepository->deleteUser($userId);
            if (!$deleted) {
                $conn->rollBack();
                return false;
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollBack();
            Logger::error("AdminRepository::deleteUserById error", [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    public function fetchAllFiles(): array
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

            return $files;
        } catch (Exception $e) {
            Logger::error("AdminRepository::fetchAllFiles error", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function fetchFileTypeStats(): array
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

            return $stats;
        } catch (Exception $e) {
            Logger::error("AdminRepository::fetchFileTypeStats error", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function cleanupOldSessions(): array
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
            Logger::error("AdminRepository::cleanupOldSessions error", [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    public function optimizeDatabase(): array
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
                'count' => count($optimizedTables),
            ];
        } catch (Exception $e) {
            Logger::error("AdminRepository::optimizeDatabase error", [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    public function deleteFile(int $fileId): bool
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT stored_name FROM files WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $file) {
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

        Logger::info("File deleted", [
            'file_id' => $fileId,
            'stored_name' => $file['stored_name'],
        ]);

        return $result;
    }

    public function cleanupOrphanedFiles(): array
    {
        try {
            $conn = $this->db->getConnection();
            $deletedCount = 0;

            $uploadsDir = __DIR__ . '/../uploads/files/';
            if (! is_dir($uploadsDir)) {
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
                if ($file === '.' || $file === '..') {
                    continue;
                }

                if (! in_array($file, $dbFiles)) {
                    $filePath = $uploadsDir . $file;
                    if (is_file($filePath)) {
                        if (! unlink($filePath)) {
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
                if (! file_exists($filePath)) {
                    $stmt = $conn->prepare("DELETE FROM files WHERE stored_name = ?");
                    $stmt->execute([$dbFile]);
                    Logger::info("Database record for missing file deleted", ['stored_name' => $dbFile]);
                }
            }

            return ['deleted_count' => $deletedCount];
        } catch (Exception $e) {
            Logger::error("AdminRepository::cleanupOrphanedFiles error", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function getDiskUsageInfo(): array
    {
        try {
            $uploadsDir = __DIR__ . '/../uploads/';
            $totalSize = 0;
            $fileCount = 0;

            if (is_dir($uploadsDir)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($uploadsDir, \FilesystemIterator::SKIP_DOTS)
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
            Logger::error("AdminRepository::getDiskUsageInfo error", [
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

    private function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
