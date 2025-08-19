<?php

namespace App\Repositories;

use App\Core\Repository;
use Exception;
use PDO;
use App\Core\Logger;

class FileRepository extends Repository
{
    public function getConnection()
    {
        return $this->db->getInstance();
    }

    public function getRootDirectoryId(int $userId): int
    {
        $result = $this->fetchOne("
            SELECT id FROM directories 
            WHERE parent_id IS NULL AND user_id = ?
        ", [$userId]);

        return $result ? (int)$result['id'] : 0;
    }

    public function checkDirectoryAccess(int $directoryId, int $userId): bool
    {
        return $this->fetchOne("
            SELECT id FROM directories 
            WHERE id = ? AND (user_id = ? OR id IN (
                SELECT item_id FROM shared_items WHERE item_type = 'directory' AND shared_with_user_id = ?
            ))
        ", [$directoryId, $userId, $userId]) !== null;
    }

    public function getDirectoriesInDirectory(int $userId, ?int $directoryId): array
    {
        $sql = "
            SELECT d.id, d.name, d.directory_name, d.created_at, 'directory' as type, 0 as is_shared, 0 as is_shared_by_owner, NULL as shared_by, d.user_id
            FROM directories d
            WHERE d.user_id = ? AND d.parent_id " . ($directoryId === null ? "IS NULL" : "= ?") . "
            UNION
            SELECT d.id, d.name, d.directory_name, d.created_at, 'directory' as type, 1 as is_shared, 0 as is_shared_by_owner, u.email as shared_by, d.user_id
            FROM directories d
            JOIN shared_items si ON si.item_type = 'directory' AND si.item_id = d.id
            JOIN users u ON si.shared_by_user_id = u.id
            WHERE si.shared_with_user_id = ? AND d.parent_id " . ($directoryId === null ? "IS NULL" : "= ?") . " AND d.parent_id IS NOT NULL
            ORDER BY created_at DESC
        ";

        $params = [$userId];
        if ($directoryId !== null) {
            $params[] = $directoryId;
        }
        $params[] = $userId;
        if ($directoryId !== null) {
            $params[] = $directoryId;
        }

        return $this->fetchAll($sql, $params);
    }

    public function getSharedRootDirectoryIds(int $userId): array
    {
        $result = $this->fetchAll("
            SELECT DISTINCT d.id
            FROM directories d
            JOIN files f ON f.directory_id = d.id
            JOIN shared_items si ON si.item_id = f.id AND si.item_type = 'file'
            WHERE d.parent_id IS NULL AND si.shared_with_user_id = ?
        ", [$userId]);

        return array_column($result, 'id');
    }

    public function getFilesInRootDirectory(int $userId, int $directoryId, array $sharedRootIds): array
    {
        $sql = "

        SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, 
               f.size AS file_size, 'file' AS type,
            0 as is_shared,
            (SELECT COUNT(*) FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_by_user_id = ?) as is_shared_by_owner,
            NULL as shared_by
        FROM files f
        WHERE f.user_id = ? AND f.directory_id = ?
        
        UNION

        SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, 
               f.size AS file_size, 'file' AS type,
            1 as is_shared,
            0 as is_shared_by_owner,
            u.email as shared_by
        FROM files f
        JOIN shared_items si ON si.item_type = 'file' AND si.item_id = f.id
        JOIN users u ON si.shared_by_user_id = u.id
        JOIN directories d ON f.directory_id = d.id
        WHERE si.shared_with_user_id = ? 
        AND d.parent_id IS NULL 
        AND d.user_id != ?
        AND NOT EXISTS (
            
            SELECT 1 FROM shared_items si_dir 
            WHERE si_dir.item_type = 'directory' 
            AND si_dir.item_id = f.directory_id 
            AND si_dir.shared_with_user_id = ?
        )
        
        ORDER BY created_at DESC
    ";

        $params = [$userId, $userId, $directoryId, $userId, $userId, $userId];

        return $this->fetchAll($sql, $params);
    }

    public function getOwnFilesInDirectory(int $userId, int $directoryId): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, f.size AS file_size, 'file' AS type,
                0 as is_shared,
                (SELECT COUNT(*) FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_by_user_id = ?) as is_shared_by_owner,
                NULL as shared_by
            FROM files f
            WHERE f.user_id = ? AND f.directory_id = ?
            ORDER BY f.created_at DESC
        ", [$userId, $userId, $directoryId]);
    }

    public function getSharedFilesInRootDirectories(int $userId): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, f.size AS file_size, 'file' AS type,
                1 as is_shared,
                0 as is_shared_by_owner,
                u.email as shared_by
            FROM files f
            JOIN shared_items si ON si.item_type = 'file' AND si.item_id = f.id
            JOIN users u ON f.user_id = u.id
            JOIN directories d ON f.directory_id = d.id
            WHERE si.shared_with_user_id = ? 
            AND d.parent_id IS NULL 
            AND f.user_id != ?
            AND NOT EXISTS (
                
                SELECT 1 FROM shared_items si_dir 
                WHERE si_dir.item_type = 'directory' 
                AND si_dir.item_id = f.directory_id 
                AND si_dir.shared_with_user_id = ?
            )
            ORDER BY f.created_at DESC
        ", [$userId, $userId, $userId]);
    }

    public function getFilesInDirectoryWithShared(int $userId, int $directoryId): array
    {
        $ownFiles = $this->fetchAll("
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, f.size AS file_size, 'file' AS type,
                0 as is_shared,
                (SELECT COUNT(*) FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_by_user_id = ?) as is_shared_by_owner,
                NULL as shared_by
            FROM files f
            WHERE f.user_id = ? AND f.directory_id = ?
            ORDER BY f.created_at DESC
        ", [$userId, $userId, $directoryId]);

        $sharedFiles = $this->fetchAll("
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, f.size AS file_size, 'file' AS type,
                1 as is_shared,
                0 as is_shared_by_owner,
                u.email as shared_by
            FROM files f
            JOIN shared_items si ON si.item_type = 'file' AND si.item_id = f.id
            JOIN users u ON f.user_id = u.id
            WHERE si.shared_with_user_id = ? AND f.directory_id = ? AND f.user_id != ?
            ORDER BY f.created_at DESC
        ", [$userId, $directoryId, $userId]);

        return array_merge($ownFiles, $sharedFiles);
    }

    public function getCurrentDirectory(?int $directoryId): ?array
    {
        if ($directoryId === 0 || $directoryId === null) {
            return null;
        }

        return $this->fetchOne("SELECT id, name, directory_name, parent_id FROM directories WHERE id = ?", [$directoryId]);
    }

    public function ensureDirectoryExists(int $userId, string $dirId): int
    {
        if ($dirId === 'root') {
            $rootDir = $this->fetchOne("
                SELECT id FROM directories 
                WHERE parent_id IS NULL AND user_id = ?
            ", [$userId]);

            if (! $rootDir) {

                return $this->insert('directories', [
                    'name' => 'Корневая папка',
                    'parent_id' => null,
                    'user_id' => $userId,
                ]);
            }

            return (int)$rootDir['id'];
        }

        return (int)$dirId;
    }

    public function checkDirectoryOwnership(int $userId, int $directoryId): bool
    {
        return $this->exists('directories', ['id' => $directoryId, 'user_id' => $userId]);
    }

    public function findExistingDirectory(string $name, int $parentId, int $userId): ?array
    {
        return $this->fetchOne("
            SELECT id FROM directories 
            WHERE name = ? AND parent_id = ? AND user_id = ?
        ", [$name, $parentId, $userId]);
    }

    public function createDirectory(string $name, string $directoryName, int $parentId, int $userId): int
    {
        return $this->insert('directories', [
            'name' => $name,
            'directory_name' => $directoryName,
            'parent_id' => $parentId,
            'user_id' => $userId,
        ]);
    }

    public function createFile(array $fileData): int
    {
        return $this->insert('files', $fileData);
    }

    public function getFileForShare(int $fileId, int $userId): ?array
    {
        return $this->fetchOne("SELECT id FROM files WHERE id = ? AND user_id = ?", [$fileId, $userId]);
    }

    public function checkExistingShare(int $fileId, int $targetUserId): bool
    {
        return $this->exists('shared_items', [
            'item_type' => 'file',
            'item_id' => $fileId,
            'shared_with_user_id' => $targetUserId,
        ]);
    }

    public function createShare(int $fileId, int $ownerId, int $targetUserId): bool
    {
        try {
            $result = $this->insert('shared_items', [
                'item_type' => 'file',
                'item_id' => $fileId,
                'shared_by_user_id' => $ownerId,
                'shared_with_user_id' => $targetUserId,
            ]);

            error_log("Share created: fileId=$fileId, ownerId=$ownerId, targetUserId=$targetUserId, result=$result");

            return $result > 0;
        } catch (Exception $e) {
            error_log("Error creating share: " . $e->getMessage());
            return false;
        }
    }

    public function getFileInfo(int $fileId, int $userId): ?array
    {
        return $this->fetchOne("
            SELECT f.*, 
                   (CASE WHEN f.user_id = ? THEN 0 ELSE 1 END) as is_shared
            FROM files f
            WHERE f.id = ? AND (f.user_id = ? OR EXISTS (
                SELECT 1 FROM shared_items si 
                WHERE si.item_id = f.id AND si.item_type = 'file' 
                AND si.shared_with_user_id = ?
            ))
        ", [$userId, $fileId, $userId, $userId]);
    }


    public function getFileForDownload(int $fileId): ?array
    {
        return $this->fetchOne("SELECT * FROM files WHERE id = ?", [$fileId]);
    }

    public function checkFileAccess(int $fileId, int $userId): bool
    {
        return $this->exists('shared_items', [
            'item_id' => $fileId,
            'shared_with_user_id' => $userId,
            'item_type' => 'file',
        ]);
    }

    public function checkFileAccessForRename(int $fileId, int $userId): bool
    {
        return $this->fetchOne("
            SELECT id
            FROM files 
            WHERE id = ? AND (
                user_id = ? OR 
                id IN (SELECT item_id FROM shared_items WHERE shared_with_user_id = ? AND item_type = 'file')
            )
        ", [$fileId, $userId, $userId]) !== null;
    }

    public function renameFile(int $fileId, string $newName): bool
    {
        return $this->update('files', ['filename' => $newName], ['id' => $fileId]);
    }

    public function checkFileOwnership(int $fileId, int $userId): bool
    {
        return $this->exists('files', ['id' => $fileId, 'user_id' => $userId]);
    }

    public function checkDirectoryAccessForMove(int $directoryId, int $userId): bool
    {
        return $this->fetchOne("
            SELECT id FROM directories WHERE id = ? AND (
                user_id = ? OR 
                id IN (
                    SELECT item_id FROM shared_items 
                    WHERE item_type = 'directory' AND shared_with_user_id = ?
                )
            )
        ", [$directoryId, $userId, $userId]) !== null;
    }

    public function moveFile(int $fileId, int $targetDirId): bool
    {
        return $this->update('files', ['directory_id' => $targetDirId], ['id' => $fileId]);
    }

    public function deleteFile(int $fileId, int $userId): ?array
    {
        $file = $this->fetchOne("
            SELECT * FROM files 
            WHERE id = ? AND user_id = ?
        ", [$fileId, $userId]);

        return $file;
    }

    public function removeFile(int $fileId, int $userId): bool
    {
        $this->delete('shared_items', [
            'item_type' => 'file',
            'item_id' => $fileId
        ]);

        return $this->delete('files', [
            'id' => $fileId,
            'user_id' => $userId
        ]);
    }

    public function checkSharedFileAccess(int $fileId, int $userId): bool
    {
        return $this->exists('shared_items', [
            'item_type' => 'file',
            'item_id' => $fileId,
            'shared_with_user_id' => $userId
        ]);
    }

    public function removeSharedAccess(int $fileId, int $userId): bool
    {
        return $this->delete('shared_items', [
            'item_type' => 'file',
            'item_id' => $fileId,
            'shared_with_user_id' => $userId
        ]);
    }

    public function getSharedFiles(int $userId): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename, f.mime_type, f.size, f.created_at,
                   u.email as shared_by,
                   d.name as directory_name
            FROM files f
            JOIN shared_items si ON f.id = si.item_id AND si.item_type = 'file'
            JOIN users u ON si.shared_by_user_id = u.id
            LEFT JOIN directories d ON f.directory_id = d.id
            WHERE si.shared_with_user_id = ?
            ORDER BY f.created_at DESC
        ", [$userId]);
    }

    public function searchFiles(string $query, int $userId): array
    {
        $searchTerm = '%' . $query . '%';

        return $this->fetchAll("
            SELECT f.id, f.filename, f.stored_name, f.mime_type, f.created_at, 
                   f.size as file_size, f.user_id, f.directory_id,
                   'file' as type,
                   CASE WHEN f.user_id = ? THEN 0 ELSE 1 END as is_shared,
                   CASE WHEN f.user_id = ? THEN 
                       (SELECT COUNT(*) FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_by_user_id = ?)
                   ELSE 0 END as is_shared_by_owner,
                   CASE WHEN f.user_id != ? THEN 
                       (SELECT u.email FROM users u JOIN shared_items si ON si.shared_by_user_id = u.id 
                        WHERE si.item_id = f.id AND si.item_type = 'file' AND si.shared_with_user_id = ? LIMIT 1)
                   ELSE NULL END as shared_by
            FROM files f
            WHERE f.filename LIKE ? AND (
                f.user_id = ? OR 
                f.id IN (
                    SELECT item_id FROM shared_items 
                    WHERE item_type = 'file' AND shared_with_user_id = ?
                )
            )
            ORDER BY f.created_at DESC
        ", [$userId, $userId, $userId, $userId, $userId, $searchTerm, $userId, $userId]);
    }

    public function isDirectorySharedToUser(int $directoryId, int $userId): ?array
    {
        $result = $this->fetchOne("
            SELECT u.email as shared_by
            FROM shared_items si
            JOIN users u ON si.shared_by_user_id = u.id
            WHERE si.item_type = 'directory' AND si.item_id = ? AND si.shared_with_user_id = ?
        ", [$directoryId, $userId]);

        return $result ?: null;
    }

    public function debugSharedFiles(int $userId): void
    {
        $sharedFiles = $this->fetchAll("
            SELECT f.id, f.filename, f.directory_id, d.name as directory_name, d.parent_id
            FROM files f
            JOIN shared_items si ON si.item_id = f.id AND si.item_type = 'file'
            JOIN directories d ON f.directory_id = d.id
            WHERE si.shared_with_user_id = ?
        ", [$userId]);

        error_log("Shared files for user $userId: " . json_encode($sharedFiles));
    }

    public function bulkDeleteFiles(array $fileIds, int $userId): array
    {
        $results = [];

        foreach ($fileIds as $fileId) {
            try {
                $file = $this->deleteFile($fileId, $userId);
                if ($file) {

                    $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }

                    $success = $this->removeFile($fileId, $userId);
                    $results[] = [
                        'file_id' => $fileId,
                        'success' => $success,
                        'message' => $success ? 'Удален' : 'Ошибка удаления'
                    ];
                } else {
                    $results[] = [
                        'file_id' => $fileId,
                        'success' => false,
                        'message' => 'Файл не найден'
                    ];
                }
            } catch (Exception $e) {
                $results[] = [
                    'file_id' => $fileId,
                    'success' => false,
                    'message' => 'Ошибка: ' . $e->getMessage()
                ];
            }
        }

        return $results;
    }

    public function getFilesByDirectory(int $directoryId, int $userId): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename, f.stored_name, f.mime_type, f.created_at, 
                   f.size as file_size, f.user_id, f.directory_id,
                   'file' as type,
                   0 as is_shared,
                   (SELECT COUNT(*) FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_by_user_id = ?) as is_shared_by_owner,
                   NULL as shared_by
            FROM files f
            WHERE f.directory_id = ? AND f.user_id = ?
            ORDER BY f.created_at DESC
        ", [$userId, $directoryId, $userId]);
    }

    public function getSharedFilesByDirectory(int $directoryId, int $userId): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename, f.stored_name, f.mime_type, f.created_at, 
                   f.size as file_size, f.user_id, f.directory_id,
                   'file' as type,
                   1 as is_shared,
                   0 as is_shared_by_owner,
                   u.email as shared_by
            FROM files f
            JOIN shared_items si ON si.item_id = f.id AND si.item_type = 'file'
            JOIN users u ON si.shared_by_user_id = u.id
            WHERE f.directory_id = ? AND si.shared_with_user_id = ? AND f.user_id != ?
            ORDER BY f.created_at DESC
        ", [$directoryId, $userId, $userId]);
    }

    public function getFileStats(int $userId): array
    {
        $stats = $this->fetchOne("
            SELECT 
                COUNT(*) as total_files,
                COALESCE(SUM(size), 0) as total_size
            FROM files 
            WHERE user_id = ?
        ", [$userId]);

        $sharedStats = $this->fetchOne("
            SELECT COUNT(DISTINCT f.id) as shared_files_count
            FROM files f
            JOIN shared_items si ON si.item_id = f.id AND si.item_type = 'file'
            WHERE si.shared_with_user_id = ?
        ", [$userId]);

        return [
            'total_files' => (int)($stats['total_files'] ?? 0),
            'total_size' => (int)($stats['total_size'] ?? 0),
            'shared_files_count' => (int)($sharedStats['shared_files_count'] ?? 0)
        ];
    }

    public function getRecentFiles(int $userId, int $limit = 10): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename, f.stored_name, f.mime_type, f.created_at, 
                   f.size as file_size, f.user_id, f.directory_id,
                   'file' as type,
                   CASE WHEN f.user_id = ? THEN 0 ELSE 1 END as is_shared,
                   CASE WHEN f.user_id = ? THEN 
                       (SELECT COUNT(*) FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_by_user_id = ?)
                   ELSE 0 END as is_shared_by_owner,
                   CASE WHEN f.user_id != ? THEN 
                       (SELECT u.email FROM users u JOIN shared_items si ON si.shared_by_user_id = u.id 
                        WHERE si.item_id = f.id AND si.item_type = 'file' AND si.shared_with_user_id = ? LIMIT 1)
                   ELSE NULL END as shared_by
            FROM files f
            WHERE f.user_id = ? OR f.id IN (
                SELECT item_id FROM shared_items 
                WHERE item_type = 'file' AND shared_with_user_id = ?
            )
            ORDER BY f.created_at DESC
            LIMIT ?
        ", [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $limit]);
    }

    public function getFilesByType(int $userId, string $mimeTypePattern): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename, f.stored_name, f.mime_type, f.created_at, 
                   f.size as file_size, f.user_id, f.directory_id,
                   'file' as type,
                   CASE WHEN f.user_id = ? THEN 0 ELSE 1 END as is_shared,
                   CASE WHEN f.user_id = ? THEN 
                       (SELECT COUNT(*) FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_by_user_id = ?)
                   ELSE 0 END as is_shared_by_owner,
                   CASE WHEN f.user_id != ? THEN 
                       (SELECT u.email FROM users u JOIN shared_items si ON si.shared_by_user_id = u.id 
                        WHERE si.item_id = f.id AND si.item_type = 'file' AND si.shared_with_user_id = ? LIMIT 1)
                   ELSE NULL END as shared_by
            FROM files f
            WHERE f.mime_type LIKE ? AND (
                f.user_id = ? OR f.id IN (
                    SELECT item_id FROM shared_items 
                    WHERE item_type = 'file' AND shared_with_user_id = ?
                )
            )
            ORDER BY f.created_at DESC
        ", [$userId, $userId, $userId, $userId, $userId, $mimeTypePattern, $userId, $userId]);
    }

    public function cleanupOrphanedFiles(): int
    {
        $files = $this->fetchAll("SELECT id, stored_name FROM files");
        $deletedCount = 0;

        foreach ($files as $file) {
            $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
            if (!file_exists($filePath)) {

                $this->delete('shared_items', [
                    'item_type' => 'file',
                    'item_id' => $file['id']
                ]);

                $this->delete('files', ['id' => $file['id']]);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    public function getFileSharesList(int $fileId): array
    {
        try {
            $conn = $this->db->getInstance();

            $debugStmt = $conn->prepare("SELECT * FROM shared_items WHERE item_id = ? AND item_type = 'file'");
            $debugStmt->execute([$fileId]);
            $debugResult = $debugStmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $conn->prepare("
                SELECT 
                    si.id as share_id,
                    si.shared_with_user_id,
                    si.created_at as shared_at,
                    si.shared_by_user_id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as full_name
                FROM shared_items si
                JOIN users u ON si.shared_with_user_id = u.id
                WHERE si.item_id = ? AND si.item_type = 'file'
                ORDER BY si.created_at DESC
            ");

            $stmt->execute([$fileId]);
            $shares = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($shares as &$share) {
                $share['shared_at_formatted'] = date('d.m.Y H:i', strtotime($share['shared_at']));
            }
            unset($share);

            return $shares;
        } catch (Exception $e) {
            Logger::error("FileRepository::getFileSharesList error", [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            return [];
        }
    }

    public function getFileById(int $fileId): ?array
    {
        try {
            $conn = $this->db->getInstance();
            $stmt = $conn->prepare("SELECT * FROM files WHERE id = ?");
            $stmt->execute([$fileId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            Logger::error("FileRepository::getFileById error", [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
            ]);
            return null;
        }
    }
}
