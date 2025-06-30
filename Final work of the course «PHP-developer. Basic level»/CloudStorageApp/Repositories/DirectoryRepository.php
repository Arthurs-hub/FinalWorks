<?php

namespace App\Repositories;

use App\Core\Repository;
use PDO;

class DirectoryRepository extends Repository
{
    public function findRootDirectory(int $userId): ?array
    {
        return $this->fetchOne("SELECT id, name, parent_id, user_id, created_at FROM directories WHERE parent_id IS NULL AND user_id = ?", [$userId]);
    }

    public function createRootDirectory(int $userId): int
    {
        return $this->insert('directories', [
            'name' => 'Корневая папка',
            'parent_id' => null,
            'user_id' => $userId
        ]);
    }

    public function createDirectory(string $name, int $parentId, int $userId): int
    {
        return $this->insert('directories', [
            'name' => $name,
            'parent_id' => $parentId,
            'user_id' => $userId
        ]);
    }

    public function checkDirectoryOwnership(int $directoryId, int $userId): bool
    {
        return $this->exists('directories', ['id' => $directoryId, 'user_id' => $userId]);
    }

    public function updateDirectoryName(int $directoryId, string $newName, int $userId): bool
    {
        return $this->update('directories', ['name' => $newName], ['id' => $directoryId, 'user_id' => $userId]);
    }

    public function moveDirectory(int $directoryId, int $targetParentId): bool
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("UPDATE directories SET parent_id = ? WHERE id = ?");
        return $stmt->execute([$targetParentId, $directoryId]);
    }

    public function deleteDirectory(int $directoryId): bool
    {
        return $this->delete('directories', ['id' => $directoryId]);
    }

    public function removeDirectoryShares(int $directoryId): bool
    {
        return $this->delete('shared_items', ['item_type' => 'directory', 'item_id' => $directoryId]);
    }

    public function shareDirectory(int $directoryId, int $sharedByUserId, int $sharedWithUserId): bool
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("
            SELECT id FROM shared_items 
            WHERE item_id = ? AND item_type = 'directory' AND shared_with_user_id = ?
        ");
        $stmt->execute([$directoryId, $sharedWithUserId]);

        if ($stmt->fetch()) {
            return false;
        }

        $stmt = $conn->prepare("
            INSERT INTO shared_items (item_type, item_id, shared_by_user_id, shared_with_user_id) 
            VALUES ('directory', ?, ?, ?)
        ");

        return $stmt->execute([$directoryId, $sharedByUserId, $sharedWithUserId]);
    }

    public function unshareDirectory(int $directoryId, int $userId): bool
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            DELETE FROM shared_items 
            WHERE item_type = 'directory' AND item_id = ? AND shared_with_user_id = ?
        ");
        return $stmt->execute([$directoryId, $userId]);
    }

    public function getDirectoryForDownload(int $directoryId, int $userId): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT d.id, d.name, d.user_id
            FROM directories d
            LEFT JOIN shared_items si ON d.id = si.item_id AND si.item_type = 'directory'
            WHERE d.id = ? AND (d.user_id = ? OR si.shared_with_user_id = ?)
        ");
        $stmt->execute([$directoryId, $userId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getDirectoryFilesForDownload(int $directoryId, int $userId): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT f.id, f.filename, f.stored_name
            FROM files f
            LEFT JOIN shared_items si ON f.id = si.item_id AND si.item_type = 'file'
            WHERE f.directory_id = ? AND (f.user_id = ? OR si.shared_with_user_id = ?)
        ");
        $stmt->execute([$directoryId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDirectorySubdirectoriesForDownload(int $directoryId, int $userId): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT d.id, d.name
            FROM directories d
            LEFT JOIN shared_items si ON d.id = si.item_id AND si.item_type = 'directory'
            WHERE d.parent_id = ? AND (d.user_id = ? OR si.shared_with_user_id = ?)
        ");
        $stmt->execute([$directoryId, $userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    'created_at' => date('Y-m-d H:i:s')
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
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
