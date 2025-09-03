<?php

namespace App\Repositories;

use App\Core\Logger;
use App\Core\Db;
use App\Core\Repository;
use Exception;
use RuntimeException;
use ZipArchive;
use PDO;

class DirectoryRepository extends Repository implements IDirectoryRepository
{
    public function __construct(Db $db)
    {
        parent::__construct($db);
    }

    public function findRootDirectory(int $userId): ?array
    {
        return $this->fetchOne("SELECT id, name, parent_id, user_id, created_at FROM directories WHERE parent_id IS NULL AND user_id = ?", [$userId]);
    }

    public function createRootDirectory(int $userId): int
    {
        return $this->insert('directories', [
            'name' => 'Корневая папка',
            'parent_id' => null,
            'user_id' => $userId,
        ]);
    }

    public function getOrCreateRootDirectoryId(int $userId): int
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT id FROM directories WHERE parent_id IS NULL AND user_id = ?");
        $stmt->execute([$userId]);
        $rootDir = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($rootDir) {
            return (int) $rootDir['id'];
        }

        $stmtCreateRoot = $conn->prepare("INSERT INTO directories (name, parent_id, user_id) VALUES ('Корневая папка', NULL, ?)");
        $stmtCreateRoot->execute([$userId]);
        return (int) $conn->lastInsertId();
    }

    public function resolveParentId($requestedParentId, int $userId)
    {
        $conn = $this->db->getConnection();

        if ($requestedParentId === 'root') {
            $stmt = $conn->prepare("SELECT id FROM directories WHERE parent_id IS NULL AND user_id = ?");
            $stmt->execute([$userId]);
            $rootDir = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rootDir) {
                return $rootDir['id'];
            } else {
                $stmtCreateRoot = $conn->prepare("INSERT INTO directories (name, parent_id, user_id) VALUES ('Корневая папка', NULL, ?)");
                $stmtCreateRoot->execute([$userId]);
                return $conn->lastInsertId();
            }
        } else {
            $dbParentId = $requestedParentId;
            $stmt = $conn->prepare("SELECT id FROM directories WHERE id = ? AND user_id = ?");
            $stmt->execute([$dbParentId, $userId]);
            $validParentDir = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$validParentDir) {
                return false;
            }
            return $dbParentId;
        }
    }

    public function createDirectory(string $name, $parentId, int $userId): int
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("INSERT INTO directories (name, parent_id, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $parentId, $userId]);
        return (int) $conn->lastInsertId();
    }

    public function checkDirectoryOwnership(?int $directoryId, int $userId): bool
    {
        if ($directoryId === null) {
            return true;
        }

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT id FROM directories WHERE id = ? AND user_id = ?");
        $stmt->execute([$directoryId, $userId]);
        return (bool) $stmt->fetch();
    }

    public function checkDirectoryAccess(int $directoryId, int $userId): bool
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT id FROM directories WHERE id = ? AND user_id = ?");
        $stmt->execute([$directoryId, $userId]);
        if ($stmt->fetch()) {
            return true;
        }

        $stmt = $conn->prepare("SELECT 1 FROM shared_items WHERE item_type = 'directory' AND item_id = ? AND shared_with_user_id = ?");
        $stmt->execute([$directoryId, $userId]);
        return (bool) $stmt->fetch();
    }

    public function findDirectoryByNameAndParent(string $name, ?int $parentId, int $userId): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT id FROM directories WHERE name = ? AND parent_id " . ($parentId === null ? "IS NULL" : "= ?") . " AND user_id = ? LIMIT 1");
        if ($parentId === null) {
            $stmt->execute([$name, $userId]);
        } else {
            $stmt->execute([$name, $parentId, $userId]);
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function renameDirectory(int $id, string $newName, int $userId): bool|string
    {
        $conn = $this->db->getConnection();

        $stmtCheck = $conn->prepare("SELECT user_id, name FROM directories WHERE id = ?");
        $stmtCheck->execute([$id]);
        $owner = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$owner) {
            return 'Папка не найдена';
        }
        if ((int) $owner['user_id'] !== $userId) {
            return 'Нет прав владельца';
        }

        if ($owner['name'] === $newName) {
            return 'Новое имя совпадает с текущим';
        }

        $stmt = $conn->prepare("UPDATE directories SET name = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$newName, $id, $userId]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return 'Не удалось переименовать папку';
    }

    public function moveDirectory(int $directoryId, ?int $targetParentId): bool
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("UPDATE directories SET parent_id = ? WHERE id = ?");
        return $stmt->execute([$targetParentId, $directoryId]);
    }

    public function deleteDirectory(?int $directoryId, int $userId, bool $isAdmin = false): bool
    {
        if ($directoryId === null) {
            return false;
        }

        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("DELETE FROM shared_items WHERE item_type = 'directory' AND item_id = ?");
        $stmt->execute([$directoryId]);

        if ($isAdmin) {

            $stmt = $conn->prepare("DELETE FROM directories WHERE id = ?");
            return $stmt->execute([$directoryId]);
        } else {

            $stmt = $conn->prepare("DELETE FROM directories WHERE id = ? AND user_id = ?");
            return $stmt->execute([$directoryId, $userId]);
        }
    }

    public function getDirectoryWithContents($idRaw, int $userId): ?array
    {
        $conn = $this->db->getConnection();

        try {
            Logger::info("Getting directory contents", [
                'id_raw' => $idRaw,
                'user_id' => $userId
            ]);

            if ($idRaw === 'root' || $idRaw === null || $idRaw === '' || $idRaw === 0 || $idRaw === '0') {
                $id = null;
                $isRootDirectory = true;
            } else {
                $id = (int) $idRaw;
                $isRootDirectory = false;
            }

            if ($id === null) {
                $directory = $this->findOrCreateRootDirectory(0, $userId);
                $isRootDirectory = true;
            } else {
                $directory = $this->getDirectoryById($id, $userId);
                if (!$directory) {
                    return null;
                }

                if ($directory['parent_id'] === null && $directory['user_id'] == $userId) {
                    $isRootDirectory = true;
                }
            }

            $subdirectories = $this->getSubdirectories($directory['id'], $userId);

            $shared_directories = [];
            if ($isRootDirectory) {
                $shared_directories = $this->getSharedDirectoriesForRoot($userId, $directory['id']);
            }

            $files = $this->getFilesInDirectoryWithShared($directory['id'], $userId, $isRootDirectory);

            $result = [
                'directory' => $directory,
                'subdirectories' => $subdirectories,
                'shared_directories' => $shared_directories,
                'files' => $files,
            ];

            Logger::info("Directory contents result", [
                'directory_id' => $directory['id'],
                'subdirectories_count' => count($subdirectories),
                'shared_directories_count' => count($shared_directories),
                'files_count' => count($files),
                'subdirectories_with_sharing_status' => array_map(function ($dir) {
                    return [
                        'id' => $dir['id'],
                        'name' => $dir['name'],
                        'is_shared' => $dir['is_shared'] ?? false,
                        'is_shared_by_owner' => $dir['is_shared_by_owner'] ?? false
                    ];
                }, $subdirectories)
            ]);

            return $result;
        } catch (Exception $e) {
            Logger::error("DirectoryRepository::getDirectoryWithContents error", [
                'error' => $e->getMessage(),
                'id_raw' => $idRaw,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function getDirectoryById(int $id, int $userId): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT d.id, d.name, d.parent_id, d.user_id, d.created_at,
                   CASE 
                       WHEN d.user_id = ? THEN 0
                       WHEN EXISTS (
                           SELECT 1 FROM shared_items si
                           WHERE si.item_id = d.id
                             AND si.item_type = 'directory'
                             AND si.shared_with_user_id = ?
                       ) THEN 1
                       ELSE 0
                   END as is_shared,
                   CASE 
                       WHEN EXISTS (
                           SELECT 1 FROM shared_items si2
                           WHERE si2.item_id = d.id
                             AND si2.item_type = 'directory'
                             AND si2.shared_by_user_id = ?
                       ) THEN 1
                       ELSE 0
                   END as is_shared_by_owner,
                   u.email as shared_by
            FROM directories d
            LEFT JOIN shared_items si ON si.item_id = d.id AND si.item_type = 'directory' AND si.shared_with_user_id = ?
            LEFT JOIN users u ON si.shared_by_user_id = u.id
            WHERE d.id = ? AND (
                d.user_id = ? OR 
                d.id IN (
                    SELECT item_id FROM shared_items 
                    WHERE item_type = 'directory' 
                    AND shared_with_user_id = ?
                )
            )
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $id, $userId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getDirectoryByIdPublic(int $id, int $userId, bool $isAdmin = false): ?array
    {
        if ($isAdmin) {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT id, name, parent_id, user_id, created_at FROM directories WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        return $this->getDirectoryById($id, $userId);
    }

    private function getSubdirectories(int $directoryId, int $userId): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT DISTINCT d.id, d.name, d.parent_id, d.user_id, d.created_at,
                   CASE 
                       WHEN d.user_id = ? THEN 0
                       WHEN EXISTS (
                           SELECT 1 FROM shared_items si
                           WHERE si.item_id = d.id
                             AND si.item_type = 'directory'
                             AND si.shared_with_user_id = ?
                       ) THEN 1
                       ELSE 0
                   END as is_shared,
                   CASE 
                       WHEN EXISTS (
                           SELECT 1 FROM shared_items si2
                           WHERE si2.item_id = d.id
                             AND si2.item_type = 'directory'
                             AND si2.shared_by_user_id = ?
                       ) THEN 1
                       ELSE 0
                   END as is_shared_by_owner,
                   u.email as shared_by
            FROM directories d
            LEFT JOIN shared_items si ON si.item_id = d.id AND si.item_type = 'directory' AND si.shared_with_user_id = ?
            LEFT JOIN users u ON si.shared_by_user_id = u.id
            WHERE d.parent_id = ? AND d.id != ?
            AND (
                d.user_id = ? OR 
                d.id IN (
                    SELECT item_id FROM shared_items 
                    WHERE item_type = 'directory' 
                    AND shared_with_user_id = ?
                )
            )
            ORDER BY d.name
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $directoryId, $directoryId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getSharedDirectoriesForRoot(int $userId, int $rootDirectoryId): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT d.id, d.name, d.parent_id, d.user_id, d.created_at,
                   u.email as shared_by,
                   1 as is_shared,
                   0 as is_shared_by_owner
            FROM directories d
            INNER JOIN shared_items si ON d.id = si.item_id AND si.item_type = 'directory'
            INNER JOIN users u ON si.shared_by_user_id = u.id
            WHERE si.shared_with_user_id = ?
            AND d.id != ?
            AND (
                d.parent_id IS NULL
                OR d.parent_id NOT IN (
                    SELECT item_id FROM shared_items si2
                    WHERE si2.item_type = 'directory' 
                    AND si2.shared_with_user_id = ?
                )
            )
            ORDER BY d.name
        ");
        $stmt->execute([$userId, $rootDirectoryId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFilesInDirectoryWithShared(int $directoryId, int $userId, bool $isRootDirectory = false): array
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.user_id,
                   f.size AS file_size, NULL as shared_by,
                   0 as is_shared,
                   CASE
                       WHEN EXISTS (
                           SELECT 1 FROM shared_items si2 
                           WHERE si2.item_id = f.id AND si2.item_type = 'file' AND si2.shared_by_user_id = ?
                       ) THEN 1
                       ELSE 0
                   END as is_shared_by_owner
            FROM files f
            WHERE f.user_id = ? AND f.directory_id = ?
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$userId, $userId, $directoryId]);
        $ownFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($isRootDirectory) {

            $stmt = $conn->prepare("
                SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.user_id,
                       f.size AS file_size, u.email as shared_by,
                       1 as is_shared,
                       0 as is_shared_by_owner
                FROM files f
                JOIN shared_items si ON si.item_type = 'file' AND si.item_id = f.id
                JOIN users u ON f.user_id = u.id
                JOIN directories d ON f.directory_id = d.id
                WHERE si.shared_with_user_id = ? 
                AND d.parent_id IS NULL 
                AND f.user_id != ?
                ORDER BY f.created_at DESC
            ");
            $stmt->execute([$userId, $userId]);
        } else {

            $stmt = $conn->prepare("
                SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.user_id,
                       f.size AS file_size, u.email as shared_by,
                       1 as is_shared,
                       0 as is_shared_by_owner
                FROM files f
                JOIN shared_items si ON si.item_type = 'file' AND si.item_id = f.id
                JOIN users u ON f.user_id = u.id
                WHERE si.shared_with_user_id = ? AND f.directory_id = ? AND f.user_id != ?
                ORDER BY f.created_at DESC
            ");
            $stmt->execute([$userId, $directoryId, $userId]);
        }

        $sharedFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $allFiles = array_merge($ownFiles, $sharedFiles);

        foreach ($allFiles as &$file) {
            $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
            if (file_exists($filePath)) {
                $actualSize = filesize($filePath);
                $file['file_size'] = $actualSize;
                $file['file_size_formatted'] = $this->formatFileSize($actualSize);
                $file['preview_available'] = strpos($file['mime_type'], 'image/') === 0 ||
                    $file['mime_type'] === 'application/pdf';
            } else {
                $file['file_size'] = 0;
                $file['file_size_formatted'] = '0 B';
                $file['preview_available'] = false;
            }
        }
        unset($file);

        error_log("Files in directory $directoryId for user $userId: own=" . count($ownFiles) . ", shared=" . count($sharedFiles) . ", total=" . count($allFiles));

        return $allFiles;
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function shareDirectory(int $folderId, int $userId, string $targetEmail, IUserRepository $userRepository): array
    {
        $conn = $this->db->getConnection();

        try {
            $conn->beginTransaction();

            Logger::info("Starting directory share process", [
                'folder_id' => $folderId,
                'owner_id' => $userId,
                'target_email' => $targetEmail
            ]);

            $stmt = $conn->prepare("SELECT id, name, user_id FROM directories WHERE id = ?");
            $stmt->execute([$folderId]);
            $directory = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$directory) {
                throw new RuntimeException('Папка не найдена');
            }

            if ((int) $directory['user_id'] !== $userId && !$this->checkDirectoryAccess($folderId, $userId)) {
                throw new RuntimeException('Нет прав доступа к папке');
            }

            $targetUser = $userRepository->findByEmail($targetEmail);
            if (!$targetUser) {
                throw new RuntimeException('Пользователь с email ' . $targetEmail . ' не найден');
            }

            if ($targetUser['id'] == $userId) {
                throw new RuntimeException('Нельзя предоставить доступ самому себе');
            }

            $stmt = $conn->prepare("SELECT id FROM shared_items WHERE item_id = ? AND item_type = 'directory' AND shared_with_user_id = ?");
            $stmt->execute([$folderId, $targetUser['id']]);

            if ($stmt->fetch()) {
                throw new RuntimeException('Доступ уже предоставлен этому пользователю');
            }

            $stmt = $conn->prepare("INSERT INTO shared_items (item_type, item_id, shared_by_user_id, shared_with_user_id) VALUES ('directory', ?, ?, ?)");
            $success = $stmt->execute([$folderId, $userId, $targetUser['id']]);

            if (!$success) {
                throw new RuntimeException('Ошибка при создании записи о расшаривании');
            }

            $this->shareDirectoryContentsRecursively($conn, $folderId, $userId, $targetUser['id']);

            $conn->commit();

            Logger::info("Directory shared successfully", [
                'folder_id' => $folderId,
                'owner_id' => $userId,
                'target_user_id' => $targetUser['id'],
                'target_email' => $targetEmail
            ]);

            return [
                'success' => true,
                'message' => 'Доступ к папке и её содержимому успешно предоставлен',
                'target_user_id' => $targetUser['id']
            ];
        } catch (Exception $e) {
            $conn->rollBack();
            Logger::error("DirectoryRepository::shareDirectory error", [
                'error' => $e->getMessage(),
                'folder_id' => $folderId,
                'owner_id' => $userId,
                'target_email' => $targetEmail,
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function shareDirectoryContentsRecursively(PDO $conn, int $directoryId, int $ownerId, int $targetUserId): void
    {
        try {

            $stmt = $conn->prepare("SELECT id, filename FROM files WHERE directory_id = ? AND user_id = ?");
            $stmt->execute([$directoryId, $ownerId]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($files as $file) {

                $stmtCheck = $conn->prepare("SELECT id FROM shared_items WHERE item_type = 'file' AND item_id = ? AND shared_with_user_id = ?");
                $stmtCheck->execute([$file['id'], $targetUserId]);

                if (!$stmtCheck->fetch()) {
                    $stmtInsert = $conn->prepare("INSERT INTO shared_items (item_type, item_id, shared_by_user_id, shared_with_user_id) VALUES ('file', ?, ?, ?)");
                    $stmtInsert->execute([$file['id'], $ownerId, $targetUserId]);
                }
            }

            $stmt = $conn->prepare("SELECT id, name FROM directories WHERE parent_id = ? AND user_id = ?");
            $stmt->execute([$directoryId, $ownerId]);
            $subdirectories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($subdirectories as $subdir) {

                $stmtCheck = $conn->prepare("SELECT id FROM shared_items WHERE item_type = 'directory' AND item_id = ? AND shared_with_user_id = ?");
                $stmtCheck->execute([$subdir['id'], $targetUserId]);

                if (!$stmtCheck->fetch()) {
                    $stmtInsert = $conn->prepare("INSERT INTO shared_items (item_type, item_id, shared_by_user_id, shared_with_user_id) VALUES ('directory', ?, ?, ?)");
                    $stmtInsert->execute([$subdir['id'], $ownerId, $targetUserId]);
                }

                $this->shareDirectoryContentsRecursively($conn, $subdir['id'], $ownerId, $targetUserId);
            }
        } catch (Exception $e) {
            Logger::error("Error in shareDirectoryContentsRecursively", [
                'directory_id' => $directoryId,
                'owner_id' => $ownerId,
                'target_user_id' => $targetUserId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function unshareDirectoryRecursively(int $directoryId, int $userId): void
    {
        $conn = $this->db->getConnection();

        $stmtFiles = $conn->prepare("SELECT id FROM files WHERE directory_id = ?");
        $stmtFiles->execute([$directoryId]);
        $files = $stmtFiles->fetchAll(PDO::FETCH_COLUMN);

        foreach ($files as $fileId) {
            $stmtDelFileShare = $conn->prepare("DELETE FROM shared_items WHERE item_type = 'file' AND item_id = ? AND shared_with_user_id = ?");
            $stmtDelFileShare->execute([$fileId, $userId]);
        }

        $stmtDirs = $conn->prepare("SELECT id FROM directories WHERE parent_id = ?");
        $stmtDirs->execute([$directoryId]);
        $subDirs = $stmtDirs->fetchAll(PDO::FETCH_COLUMN);

        foreach ($subDirs as $subDirId) {
            $this->unshareDirectoryRecursively($subDirId, $userId);
        }

        $stmtDelDirShare = $conn->prepare("DELETE FROM shared_items WHERE item_type = 'directory' AND item_id = ? AND shared_with_user_id = ?");
        $stmtDelDirShare->execute([$directoryId, $userId]);
    }

    public function getSharedDirectories(int $userId): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT d.id, d.name, d.parent_id, d.created_at,
                   u.email as shared_by,
                   1 as is_shared,
                   0 as is_shared_by_owner
            FROM directories d
            JOIN shared_items si ON d.id = si.item_id AND si.item_type = 'directory'
            JOIN users u ON si.shared_by_user_id = u.id
            WHERE si.shared_with_user_id = ?
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createZipArchiveForDirectory(?int $directoryId, int $userId): string
    {
        $conn = $this->db->getConnection();

        if ($directoryId === null) {

            $stmt = $conn->prepare("SELECT id, name FROM directories WHERE parent_id IS NULL AND user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $directory = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$directory) {
                throw new RuntimeException('Корневая папка не найдена');
            }
            $directoryId = $directory['id'];
        } else {
            $stmt = $conn->prepare("
                SELECT d.id, d.name, d.user_id
                FROM directories d
                LEFT JOIN shared_items si ON d.id = si.item_id AND si.item_type = 'directory'
                WHERE d.id = ? AND (d.user_id = ? OR si.shared_with_user_id = ?)
            ");
            $stmt->execute([$directoryId, $userId, $userId]);
            $directory = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$directory) {
                throw new RuntimeException('Папка не найдена или нет прав доступа');
            }
        }

        $zipFileName = tempnam(sys_get_temp_dir(), 'dir_');
        $zip = new ZipArchive();

        if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось создать ZIP-архив');
        }

        $this->addDirectoryToZip($zip, $directoryId, $directory['name'], $conn, $userId);

        $zip->close();

        return $zipFileName;
    }

    private function addDirectoryToZip(ZipArchive $zip, int $directoryId, string $path, PDO $conn, int $userId, string $parentPath = ''): void
    {
        $stmt = $conn->prepare("
            SELECT f.id, f.filename, f.stored_name
            FROM files f
            LEFT JOIN shared_items si ON f.id = si.item_id AND si.item_type = 'file'
            WHERE f.directory_id = ? AND (f.user_id = ? OR si.shared_with_user_id = ?)
        ");
        $stmt->execute([$directoryId, $userId, $userId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $currentPath = $parentPath . ($parentPath ? '/' : '') . $this->sanitizeFileName($path);

        $zip->addEmptyDir($currentPath);

        foreach ($files as $file) {
            $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $currentPath . '/' . $this->sanitizeFileName($file['filename']));
            }
        }

        $stmt = $conn->prepare("
            SELECT d.id, d.name
            FROM directories d
            LEFT JOIN shared_items si ON d.id = si.item_id AND si.item_type = 'directory'
            WHERE d.parent_id = ? AND (d.user_id = ? OR si.shared_with_user_id = ?)
        ");
        $stmt->execute([$directoryId, $userId, $userId]);
        $subdirectories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subdirectories as $subdir) {
            $this->addDirectoryToZip($zip, $subdir['id'], $subdir['name'], $conn, $userId, $currentPath);
        }
    }

    public function isDirectorySharedToUser(int $directoryId, int $userId): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT u.email as shared_by
            FROM shared_items si
            JOIN users u ON si.shared_by_user_id = u.id
            WHERE si.item_type = 'directory' AND si.item_id = ? AND si.shared_with_user_id = ?
        ");
        $stmt->execute([$directoryId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function sanitizeFileName(string $fileName): string
    {
        $fileName = preg_replace('/[\/\\\:\*\?"<>\|]/', '_', $fileName);
        $fileName = preg_replace('/_+/', '_', $fileName);
        return substr($fileName, 0, 255);
    }

    public function findAccessibleParentDirectory(int $directoryId, int $userId): ?array
    {
        $conn = $this->db->getConnection();

        Logger::info("findAccessibleParentDirectory called", [
            'directory_id' => $directoryId,
            'user_id' => $userId
        ]);

        $stmt = $conn->prepare("
            SELECT d.id, d.name, d.parent_id, d.user_id,
                   CASE
                       WHEN d.user_id = ? THEN 0
                       WHEN EXISTS (
                           SELECT 1 FROM shared_items si
                           WHERE si.item_id = d.id
                             AND si.item_type = 'directory'
                             AND si.shared_with_user_id = ?
                       ) THEN 1
                       ELSE 0
                   END as is_shared
            FROM directories d
            WHERE d.id = ?
        ");
        $stmt->execute([$userId, $userId, $directoryId]);
        $currentDir = $stmt->fetch(PDO::FETCH_ASSOC);

        Logger::info("Current directory info", [
            'current_dir' => $currentDir
        ]);

        if (!$currentDir) {
            Logger::warning("Current directory not found");
            return null;
        }

        if (!$currentDir['parent_id']) {
            Logger::info("No parent directory found");
            return null;
        }

        $parentId = $currentDir['parent_id'];
        Logger::info("Checking access to parent", ['parent_id' => $parentId]);

        if ($this->checkDirectoryAccess($parentId, $userId)) {
            Logger::info("Access to parent granted");

            $stmt = $conn->prepare("
                SELECT d.id, d.name, d.parent_id, d.user_id,
                       u.email as shared_by,
                       CASE
                           WHEN d.user_id = ? THEN 0
                           WHEN EXISTS (
                               SELECT 1 FROM shared_items si
                               WHERE si.item_id = d.id
                                 AND si.item_type = 'directory'
                                 AND si.shared_with_user_id = ?
                           ) THEN 1
                           ELSE 0
                       END as is_shared,
                       CASE 
                           WHEN EXISTS (
                               SELECT 1 FROM shared_items si2
                               WHERE si2.item_id = d.id
                                 AND si2.item_type = 'directory'
                                 AND si2.shared_by_user_id = ?
                           ) THEN 1
                           ELSE 0
                       END as is_shared_by_owner
                FROM directories d
                LEFT JOIN shared_items si ON si.item_id = d.id AND si.item_type = 'directory' AND si.shared_with_user_id = ?
                LEFT JOIN users u ON si.shared_by_user_id = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$userId, $userId, $userId, $userId, $parentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            Logger::info("Parent directory info", ['parent_info' => $result]);
            return $result ?: null;
        }

        Logger::info("No access to direct parent, searching recursively");

        return $this->findAccessibleParentDirectory($parentId, $userId);
    }

    public function getDirectoryNavigationInfo(int $directoryId, int $userId): array
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("
            SELECT d.id, d.name, d.parent_id, d.user_id,
                   u.email as shared_by,
                   CASE
                       WHEN d.user_id = ? THEN 0
                       WHEN EXISTS (
                           SELECT 1 FROM shared_items si
                           WHERE si.item_id = d.id
                             AND si.item_type = 'directory'
                             AND si.shared_with_user_id = ?
                       ) THEN 1
                       ELSE 0
                   END as is_shared
            FROM directories d
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.id = ?
        ");
        $stmt->execute([$userId, $userId, $directoryId]);
        $currentDirectory = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentDirectory) {
            return [
                'success' => false,
                'error' => 'Директория не найдена'
            ];
        }

        $safeParent = null;
        $safeParentId = null;
        $canGoBack = false;

        if ($currentDirectory['parent_id']) {
            $safeParent = $this->findAccessibleParentDirectory($directoryId, $userId);
            if ($safeParent) {
                $safeParentId = $safeParent['id'];
                $canGoBack = true;
            }
        }

        if (!$safeParentId) {
            $rootDir = $this->findOrCreateRootDirectory($directoryId, $userId);
            if ($rootDir && $rootDir['id'] != $directoryId) {
                $safeParentId = $rootDir['id'];
                $canGoBack = true;
            }
        }

        $navigationPath = $this->getNavigationPath($directoryId, $userId);

        return [
            'success' => true,
            'currentDirectory' => $currentDirectory,
            'safeParentId' => $safeParentId,
            'safeParent' => $safeParent,
            'navigationPath' => $navigationPath,
            'canGoBack' => $canGoBack
        ];
    }

    public function getNavigationPath(int $directoryId, int $userId): array
    {
        $conn = $this->db->getConnection();
        $path = [];
        $currentId = $directoryId;
        $maxDepth = 10;
        $depth = 0;

        while ($currentId && $depth < $maxDepth) {

            if (!$this->checkDirectoryAccess($currentId, $userId)) {
                break;
            }

            $stmt = $conn->prepare("
                SELECT d.id, d.name, d.parent_id, d.user_id,
                       CASE
                           WHEN d.user_id = ? THEN 0
                           ELSE 1
                       END as is_shared
                FROM directories d
                WHERE d.id = ?
            ");
            $stmt->execute([$userId, $currentId]);
            $dir = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dir) {
                break;
            }

            array_unshift($path, [
                'id' => $dir['id'],
                'name' => $dir['name'],
                'is_shared' => (bool) $dir['is_shared']
            ]);

            $currentId = $dir['parent_id'];
            $depth++;
        }

        if (empty($path) || $path[0]['id'] !== 'root') {
            array_unshift($path, [
                'id' => 'root',
                'name' => 'Мои файлы',
                'is_shared' => false
            ]);
        }

        return $path;
    }

    public function findOrCreateRootDirectory(int $directoryId, int $userId): array
    {
        $conn = $this->db->getConnection();

        $conn->beginTransaction();

        try {
            $stmt = $conn->prepare("
                SELECT id, name, name as directory_name, parent_id, user_id, created_at
                FROM directories 
                WHERE parent_id IS NULL AND user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $directory = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$directory) {
                $stmt = $conn->prepare("
                    INSERT INTO directories (name, parent_id, user_id) 
                    VALUES ('Корневая папка', NULL, ?)
                ");
                $stmt->execute([$userId]);
                $directory = [
                    'id' => $conn->lastInsertId(),
                    'name' => 'Корневая папка',
                    'directory_name' => 'Корневая папка',
                    'parent_id' => null,
                    'user_id' => $userId,
                    'is_shared' => 0,
                    'is_shared_by_owner' => 0,
                    'shared_by' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            } else {
                $stmtShared = $conn->prepare("
                    SELECT 1 FROM shared_items 
                    WHERE item_type = 'directory' AND item_id = ? AND shared_by_user_id = ? 
                    LIMIT 1
                ");
                $stmtShared->execute([$directory['id'], $userId]);
                $directory['is_shared_by_owner'] = $stmtShared->fetch() ? 1 : 0;
                $directory['is_shared'] = 0;
                $directory['shared_by'] = null;
                $directory['directory_name'] = $directory['name'];
            }

            $conn->commit();

            return $directory;
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function isSharedDirectory(int $directoryId, int $userId): bool
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT user_id FROM directories WHERE id = ?");
        $stmt->execute([$directoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return false;
        }

        return $result['user_id'] != $userId;
    }

    public function getUserRootDirectoryId(int $userId)
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT id FROM directories WHERE parent_id IS NULL AND user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return $result['id'];
        }

        $rootId = $this->createRootDirectory($userId);
        return $rootId ?: 'root';
    }

    public function isDirectorySharedByOwner(int $directoryId, int $userId): bool
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM shared_items 
            WHERE item_type = 'directory' 
            AND item_id = ? 
            AND shared_by_user_id = ?
        ");
        $stmt->execute([$directoryId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }
}
