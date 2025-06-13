<?php

namespace App\Services;

use App\Core\Db;
use Exception;
use PDO;
use RuntimeException;

class FileService
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }


    public function getSharedFiles(int $userId): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM shared_items WHERE shared_with_user_id = ?");
        $stmt->execute([$userId]);

        $sharedFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$sharedFiles) {
            return [];
        }

        $files = [];
        foreach ($sharedFiles as $sharedFile) {
            $files[] = [
                'id' => $sharedFile['item_id'],
                'name' => $sharedFile['item_name'],
                'size' => $sharedFile['item_size'],
                'type' => $sharedFile['item_type'],
                'is_shared' => true,
                'is_owner' => false,
            ];
        }

        return $files;
    }

    public function deleteFile(int $fileId, int $userId): bool
    {
        $conn = $this->db->getConnection();

        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("SELECT stored_name FROM files WHERE id = ? AND user_id = ?");
            $stmt->execute([$fileId, $userId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($file) {

                $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $stmt = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
                $stmt->execute([$fileId, $userId]);

                $conn->commit();
                return true;
            }

            $conn->rollBack();
            return false;
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    public function renameFile(int $fileId, int $userId, string $newName): bool
    {
        $conn = $this->db->getConnection();

        try {
            $stmt = $conn->prepare("
                UPDATE files 
                SET filename = ? 
                WHERE id = ? AND (
                    user_id = ? OR 
                    id IN (SELECT item_id FROM shared_items WHERE shared_with_user_id = ?)
                )
            ");

            return $stmt->execute([$newName, $fileId, $userId, $userId]);
        } catch (Exception $e) {
            throw new RuntimeException('Ошибка при переименовании файла: ' . $e->getMessage());
        }
    }

    public function listFiles(int $userId, string $dirId = 'root', int $page = 1, int $perPage = 20): array
    {
        $conn = $this->db->getConnection();
        $offset = ($page - 1) * $perPage;

        try {
            $stmt = $conn->prepare("
                SELECT id, file.name as name, 'file' as type 
                FROM files 
                WHERE user_id = ? AND directory_id = ?
                LIMIT ? OFFSET ?
            ");

            $stmt->execute([$userId, $dirId, $perPage, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new RuntimeException('Ошибка при получении списка файлов: ' . $e->getMessage());
        }
    }
}
