<?php

namespace App\Repositories;

use App\Core\Repository;
use Exception;

class FileRepository extends Repository
{
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
        if ($sharedRootIds) {
            $placeholders = implode(',', array_fill(0, count($sharedRootIds), '?'));
            $in = "($placeholders)";
            $inCondition = "f.directory_id IN $in";
        } else {
            $inCondition = "0";
        }

        $sql = "
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, f.size AS file_size, 'file' AS type,
                0 as is_shared,
                (SELECT COUNT(*) FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_by_user_id = ?) as is_shared_by_owner,
                NULL as shared_by
            FROM files f
            WHERE f.user_id = ? AND f.directory_id = ?
            UNION
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, f.size AS file_size, 'file' AS type,
                1 as is_shared,
                0 as is_shared_by_owner,
                u.email as shared_by
            FROM files f
            JOIN shared_items si ON si.item_type = 'file' AND si.item_id = f.id
            JOIN users u ON f.user_id = u.id
            WHERE si.shared_with_user_id = ? AND (f.directory_id = ? OR $inCondition)
            ORDER BY created_at DESC
        ";

        $params = [$userId, $userId, $directoryId, $userId, $directoryId];
        if ($sharedRootIds) {
            $params = array_merge($params, $sharedRootIds);
        }

        return $this->fetchAll($sql, $params);
    }

    public function getFilesInDirectoryWithAccess(int $userId, int $directoryId, int $ownerId): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, f.size AS file_size, 'file' AS type,
                CASE WHEN f.user_id = ? THEN 0 ELSE 1 END as is_shared,
                (SELECT COUNT(*) FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_by_user_id = ?) as is_shared_by_owner,
                (CASE WHEN f.user_id = ? THEN NULL ELSE u.email END) as shared_by
            FROM files f
            LEFT JOIN users u ON f.user_id = u.id
            WHERE f.directory_id = ? AND (f.user_id = ? OR EXISTS (
                SELECT 1 FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_with_user_id = ?
            ))
            ORDER BY f.created_at DESC
        ", [$ownerId, $ownerId, $ownerId, $directoryId, $ownerId, $userId]);
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

            if (!$rootDir) {

                return $this->insert('directories', [
                    'name' => 'Корневая папка',
                    'parent_id' => null,
                    'user_id' => $userId
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
            'user_id' => $userId
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
            'shared_with_user_id' => $targetUserId
        ]);
    }

    public function createShare(int $fileId, int $ownerId, int $targetUserId): bool
    {
        try {
            $this->insert('shared_items', [
                'item_type' => 'file',
                'item_id' => $fileId,
                'shared_by_user_id' => $ownerId,
                'shared_with_user_id' => $targetUserId
            ]);
            return true;
        } catch (Exception $e) {
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
            'item_type' => 'file'
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
        return $this->fetchOne("SELECT stored_name FROM files WHERE id = ? AND user_id = ?", [$fileId, $userId]);
    }

    public function removeFile(int $fileId, int $userId): bool
    {
        return $this->delete('files', ['id' => $fileId, 'user_id' => $userId]);
    }

    public function checkSharedFileAccess(int $fileId, int $userId): bool
    {
        return $this->exists('shared_items', [
            'item_id' => $fileId,
            'shared_with_user_id' => $userId,
            'item_type' => 'file'
        ]);
    }

    public function removeSharedAccess(int $fileId, int $userId): bool
    {
        return $this->delete('shared_items', [
            'item_id' => $fileId,
            'shared_with_user_id' => $userId,
            'item_type' => 'file'
        ]);
    }

    public function isDirectorySharedWithUser(int $directoryId, int $userId): bool
    {
        $result = $this->fetchOne("
            SELECT id FROM directories 
            WHERE id = ? AND user_id != ? AND id IN (
                SELECT item_id FROM shared_items WHERE item_type = 'directory' AND shared_with_user_id = ?
            )
        ", [$directoryId, $userId, $userId]);

        return $result !== null;
    }

    public function getFilesInSharedDirectory(int $userId, int $directoryId): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, f.size AS file_size, 'file' AS type,
                1 as is_shared,
                0 as is_shared_by_owner,
                u.email as shared_by
            FROM files f
            JOIN shared_items si ON si.item_type = 'file' AND si.item_id = f.id
            JOIN users u ON f.user_id = u.id
            WHERE si.shared_with_user_id = ? AND f.directory_id = ?
            ORDER BY f.created_at DESC
        ", [$userId, $directoryId]);
    }

    public function checkFileAccessForFile(int $fileId, int $userId): bool
    {
        return $this->checkFileAccessForRename($fileId, $userId);
    }

    public function countUserFiles(int $userId): int
    {
        return $this->count('files', ['user_id' => $userId]);
    }

    public function getTotalFilesSize(int $userId): int
    {
        $result = $this->fetchOne("
            SELECT COALESCE(SUM(size), 0) as total_size 
            FROM files 
            WHERE user_id = ?
        ", [$userId]);

        return (int)($result['total_size'] ?? 0);
    }

    public function getRecentFiles(int $userId, int $limit = 10): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename, f.mime_type, f.size, f.created_at,
                   d.name as directory_name
            FROM files f
            LEFT JOIN directories d ON f.directory_id = d.id
            WHERE f.user_id = ?
            ORDER BY f.created_at DESC
            LIMIT ?
        ", [$userId, $limit]);
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

    public function getFilesSharedByUser(int $userId): array
    {
        return $this->fetchAll("
            SELECT f.id, f.filename, f.mime_type, f.size, f.created_at,
                   u.email as shared_with,
                   d.name as directory_name,
                   COUNT(si.id) as share_count
            FROM files f
            JOIN shared_items si ON f.id = si.item_id AND si.item_type = 'file'
            JOIN users u ON si.shared_with_user_id = u.id
            LEFT JOIN directories d ON f.directory_id = d.id
            WHERE si.shared_by_user_id = ?
            GROUP BY f.id, u.email
            ORDER BY f.created_at DESC
        ", [$userId]);
    }
}
