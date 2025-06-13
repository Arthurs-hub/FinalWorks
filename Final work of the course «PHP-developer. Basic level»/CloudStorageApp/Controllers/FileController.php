<?php

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Services\FileService;
use App\Services\UserService;
use Exception;
use finfo;
use PDO;
use RuntimeException;

class FileController
{
    private const UPLOAD_DIR_FILES = __DIR__ . '/../uploads/files/';

    private FileService $fileService;
    private UserService $userService;
    private Db $db;

    public function __construct()
    {
        $this->fileService = new FileService();
        $this->userService = new UserService();
        $this->db = new Db();
    }
    public function list(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        $userId = $_SESSION['user_id'];
        $conn = $this->db->getConnection();

        $directoryId = $request->getData()['directory_id'] ?? $_GET['directory_id'] ?? 'root';

        if ($directoryId === 'root') {
            $directoryId = $this->getRootDirectoryId($conn, $userId);
        }

        $stmt = $conn->prepare("
    SELECT id FROM directories 
    WHERE id = ? AND (user_id = ? OR id IN (
        SELECT item_id FROM shared_items WHERE item_type = 'directory' AND shared_with_user_id = ?
    ))
");
        $stmt->execute([$directoryId, $userId, $userId]);
        if (!$stmt->fetch()) {

            $directoryId = $this->getRootDirectoryId($conn, $userId);
        }

        if ($directoryId === 'root' || $directoryId === 0 || $directoryId === '0') {
            $directoryId = null;
        }
        error_log("FileController: directoryId = " . var_export($directoryId, true));

        $stmt = $conn->prepare("
        SELECT d.id, d.name, d.directory_name, d.created_at, 'directory' as type, 0 as is_shared, 0 as is_shared_by_owner, NULL as shared_by, d.user_id
        FROM directories d
        WHERE d.user_id = :userId AND d.parent_id " . ($directoryId === null ? "IS NULL" : "= :directoryId") . "
        UNION
        SELECT d.id, d.name, d.directory_name, d.created_at, 'directory' as type, 1 as is_shared, 0 as is_shared_by_owner, u.email as shared_by, d.user_id
        FROM directories d
        JOIN shared_items si ON si.item_type = 'directory' AND si.item_id = d.id
        JOIN users u ON si.shared_by_user_id = u.id
        WHERE si.shared_with_user_id = :userId AND d.parent_id " . ($directoryId === null ? "IS NULL" : "= :directoryId") . " AND d.parent_id IS NOT NULL
        ORDER BY created_at DESC
    ");

        $params = [':userId' => $userId];
        if ($directoryId !== null) {
            $params[':directoryId'] = $directoryId;
        }
        $stmt->execute($params);
        $directories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($directoryId === $this->getRootDirectoryId($conn, $userId)) {

            $stmt = $conn->prepare("
            SELECT DISTINCT d.id
            FROM directories d
            JOIN files f ON f.directory_id = d.id
            JOIN shared_items si ON si.item_id = f.id AND si.item_type = 'file'
            WHERE d.parent_id IS NULL AND si.shared_with_user_id = ?
        ");
            $stmt->execute([$userId]);
            $sharedRootIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $in = $sharedRootIds ? ('(' . implode(',', array_fill(0, count($sharedRootIds), '?')) . ')') : '(NULL)';
            $stmt = $conn->prepare("
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
            WHERE si.shared_with_user_id = ? AND f.directory_id IN $in
            ORDER BY created_at DESC
        ");
            $params = array_merge(
                [$userId, $userId, $directoryId, $userId],
                $sharedRootIds
            );
            $stmt->execute($params);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $conn->prepare("
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, f.size AS file_size, 'file' AS type,
                0 as is_shared,
                (SELECT COUNT(*) FROM shared_items si WHERE si.item_type = 'file' AND si.item_id = f.id AND si.shared_by_user_id = :userId) as is_shared_by_owner,
                NULL as shared_by
            FROM files f
            WHERE f.user_id = :userId AND f.directory_id = :directoryId
            UNION
            SELECT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.directory_id, f.user_id, f.size AS file_size, 'file' AS type,
                1 as is_shared,
                0 as is_shared_by_owner,
                u.email as shared_by
            FROM files f
            JOIN shared_items si ON si.item_type = 'file' AND si.item_id = f.id
            JOIN users u ON f.user_id = u.id
            WHERE si.shared_with_user_id = :userId AND f.directory_id = :directoryId
            ORDER BY created_at DESC
        ");
            $stmt->execute([':userId' => $userId, ':directoryId' => $directoryId]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($files as &$file) {
            if (isset($file['file_size'])) {
                $file['file_size'] = $this->formatFileSize((int)$file['file_size']);
            }
        }
        unset($file);

        $currentDirectory = null;
        if ($directoryId !== 0) {
            $stmt = $conn->prepare("SELECT id, name, directory_name, parent_id FROM directories WHERE id = :directoryId");
            $stmt->execute([':directoryId' => $directoryId]);
            $currentDirectory = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return new Response([
            'success' => true,
            'files' => $files,
            'directories' => $directories,
            'current_directory' => $currentDirectory,
            'current_directory_id' => $directoryId
        ]);
    }

    private function getRootDirectoryId(PDO $conn, int $userId): int
    {
        $stmt = $conn->prepare("
            SELECT id FROM directories 
            WHERE parent_id IS NULL AND user_id = ?
        ");
        $stmt->execute([$userId]);
        $rootDir = $stmt->fetch(PDO::FETCH_ASSOC);

        return $rootDir ? (int)$rootDir['id'] : 0;
    }
    private function ensureDirectoryExists(PDO $conn, int $userId, string $dirId): int
    {
        if ($dirId === 'root') {
            $stmt = $conn->prepare("
                SELECT id FROM directories 
                WHERE parent_id IS NULL AND user_id = ?
            ");
            $stmt->execute([$userId]);
            $rootDir = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rootDir) {

                $stmt = $conn->prepare("
                    INSERT INTO directories (name, parent_id, user_id) 
                    VALUES ('Корневая папка', NULL, ?)
                ");
                $stmt->execute([$userId]);
                return (int)$conn->lastInsertId();
            }

            return (int)$rootDir['id'];
        }

        return (int)$dirId;
    }

    public function upload(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];
            $conn = $this->db->getConnection();

            $directoryId = $_POST['directory_id'] ?? 'root';

            error_log("Upload: received directoryId: $directoryId");

            if ($directoryId === 'root') {
                $directoryId = $this->ensureDirectoryExists($conn, $userId, 'root');
            } else {

                $stmt = $conn->prepare("
                    SELECT id FROM directories 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$directoryId, $userId]);
                if (!$stmt->fetch()) {

                    $directoryId = $this->ensureDirectoryExists($conn, $userId, 'root');
                }
            }

            $paths = [];
            if (isset($_POST['paths'])) {
                $paths = json_decode($_POST['paths'], true);
            }

            $dirMap = [];
            $dirMap[''] = $directoryId;

            $uniqueDirs = [];
            foreach ($paths as $path) {
                $dir = dirname($path);
                if ($dir !== '.' && $dir !== '') {
                    $parts = explode('/', $dir);
                    $currentPath = '';
                    foreach ($parts as $part) {
                        $parentPath = $currentPath;
                        $currentPath = $currentPath === '' ? $part : $currentPath . '/' . $part;

                        if (!isset($uniqueDirs[$currentPath])) {
                            $uniqueDirs[$currentPath] = $parentPath;
                        }
                    }
                }
            }

            ksort($uniqueDirs);
            foreach ($uniqueDirs as $dirPath => $parentPath) {
                $parentId = $dirMap[$parentPath] ?? $directoryId;

                $dirName = basename($dirPath);

                $stmt = $conn->prepare("
                    SELECT id FROM directories 
                    WHERE name = ? AND parent_id = ? AND user_id = ?
                ");
                $stmt->execute([$dirName, $parentId, $userId]);
                $existingDir = $stmt->fetch();

                if ($existingDir) {
                    $dirMap[$dirPath] = $existingDir['id'];
                } else {

                    $stmt = $conn->prepare("
                        INSERT INTO directories (name, directory_name, parent_id, user_id) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$dirName, $dirName, $parentId, $userId]);
                    $dirMap[$dirPath] = $conn->lastInsertId();
                }
            }

            $uploadedFiles = [];
            $errors = [];

            if (!empty($_FILES['files']['name'])) {
                $fileCount = count($_FILES['files']['name']);

                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                        $originalName = $_FILES['files']['name'][$i];
                        $tmpName = $_FILES['files']['tmp_name'][$i];
                        $fileSize = $_FILES['files']['size'][$i];

                        $filePath = isset($paths[$i]) ? $paths[$i] : $originalName;
                        $fileDir = dirname($filePath);

                        $targetDirId = $directoryId;

                        if ($fileDir !== '.' && $fileDir !== '') {

                            $targetDirId = $dirMap[$fileDir] ?? $directoryId;
                        }

                        error_log("File: $originalName, Dir: $fileDir, Target Dir ID: $targetDirId");

                        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                        $storedName = uniqid() . '_' . time() . '.' . $extension;

                        $uploadDir = __DIR__ . '/../uploads/files/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $storedPath = $uploadDir . $storedName;

                        if (move_uploaded_file($tmpName, $storedPath)) {

                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mimeType = $finfo->file($storedPath);

                            $stmt = $conn->prepare("
                                INSERT INTO files (
                                    filename, stored_name, filepath, size, file_size, 
                                    mime_type, user_id, directory_id
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");

                            $stmt->execute([
                                $originalName,
                                $storedName,
                                'uploads/files/' . $storedName,
                                $fileSize,
                                $fileSize,
                                $mimeType,
                                $userId,
                                $targetDirId
                            ]);

                            $uploadedFiles[] = [
                                'id' => $conn->lastInsertId(),
                                'name' => $originalName,
                                'size' => $fileSize,
                                'directory_id' => $targetDirId
                            ];

                            error_log("File saved: $originalName in directory $targetDirId");
                        } else {
                            $errors[] = "Не удалось загрузить файл: $originalName";
                        }
                    } else {
                        $errors[] = "Ошибка загрузки файла: " . $_FILES['files']['name'][$i];
                    }
                }
            }

            $response = [
                'success' => true,
                'message' => 'Файлы успешно загружены',
                'uploaded_files' => $uploadedFiles,
                'created_directories' => array_values($dirMap)
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            return new Response($response);
        } catch (Exception $e) {
            error_log("Upload error: " . $e->getMessage());
            return new Response([
                'success' => false,
                'error' => 'Ошибка при загрузке файлов: ' . $e->getMessage()
            ], 500);
        }
    }

    public function share(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];
            $data = $request->getData();

            if (!isset($data['file_id']) || !isset($data['email'])) {
                return new Response(['success' => false, 'error' => 'Не указан ID файла или email пользователя'], 400);
            }

            $fileId = $data['file_id'];
            $targetEmail = trim($data['email']);

            $conn = $this->db->getConnection();
            $conn->beginTransaction();

            try {

                $stmt = $conn->prepare("SELECT id FROM files WHERE id = ? AND user_id = ?");
                $stmt->execute([$fileId, $userId]);
                $file = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$file) {
                    throw new RuntimeException('Файл не найден или нет прав доступа');
                }

                $targetUser = $this->userService->findUserByEmail($targetEmail);
                if (!$targetUser) {
                    throw new RuntimeException('Пользователь с указанным email не найден');
                }

                if ($targetUser['id'] == $userId) {
                    throw new RuntimeException('Нельзя предоставить доступ самому себе');
                }

                $stmt = $conn->prepare("
                SELECT id FROM shared_items 
                WHERE item_type = 'file' AND item_id = ? AND shared_with_user_id = ?
            ");
                $stmt->execute([$fileId, $targetUser['id']]);
                if ($stmt->fetch()) {
                    throw new RuntimeException('Доступ к файлу уже предоставлен этому пользователю');
                }

                $stmt = $conn->prepare("
                INSERT INTO shared_items 
                (item_type, item_id, shared_by_user_id, shared_with_user_id) 
                VALUES ('file', ?, ?, ?)
            ");
                $stmt->execute([$fileId, $userId, $targetUser['id']]);

                $conn->commit();

                return new Response([
                    'success' => true,
                    'message' => 'Доступ к файлу успешно предоставлен'
                ]);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return new Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getFileInfo(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }
        $userId = $_SESSION['user_id'];
        $fileId = $request->routeParams['id'] ?? null;

        if (!$fileId) {
            return new Response(['success' => false, 'error' => 'ID файла не указан'], 400);
        }

        $file = $this->getFileInfoByIdAndUser((int)$fileId, (int)$userId);

        if (isset($file['error'])) {
            return new Response(['success' => false, 'error' => $file['error']], 404);
        }

        return new Response(['success' => true, 'file' => $file]);
    }

    private function getFileInfoByIdAndUser(int $fileId, int $userId): array
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("
        SELECT f.*, 
               (CASE WHEN f.user_id = ? THEN 0 ELSE 1 END) as is_shared
        FROM files f
        WHERE f.id = ? AND (f.user_id = ? OR EXISTS (
            SELECT 1 FROM shared_items si 
            WHERE si.item_id = f.id AND si.item_type = 'file' 
            AND si.shared_with_user_id = ?
        ))
    ");
        $stmt->execute([$userId, $fileId, $userId, $userId]);
        $fileInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fileInfo) {
            return ['error' => 'File not found'];
        }

        return $fileInfo;
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

    public function download(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }
        $fileId = $request->routeParams['id'] ?? null;

        if (!$fileId) {
            return new Response(['success' => false, 'error' => 'ID файла не указан'], 400);
        }

        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            return new Response(['success' => false, 'error' => 'Файл не найден'], 404);
        }

        $userId = $_SESSION['user_id'];
        if ($file['user_id'] != $userId) {
            $stmt = $conn->prepare("SELECT * FROM shared_items WHERE item_id = ? AND shared_with_user_id = ? AND item_type = 'file'");
            $stmt->execute([$fileId, $userId]);
            if (!$stmt->fetch()) {
                return new Response(['success' => false, 'error' => 'Нет прав на доступ к файлу'], 403);
            }
        }

        $filePath = self::UPLOAD_DIR_FILES . $file['stored_name'];
        if (!file_exists($filePath)) {
            return new Response(['success' => false, 'error' => 'Файл не найден на сервере'], 404);
        }

        $isInline = false;
        if (isset($_GET['inline']) && $_GET['inline'] == '1') {
            $isInline = true;
        }

        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . filesize($filePath));
        if ($isInline) {
            header('Content-Disposition: inline; filename="' . $file['filename'] . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
        }
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        exit;
    }

    public function rename(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован']);
        }

        try {
            $userId = $_SESSION['user_id'];
            $data = $request->getData();

            if (!isset($data['file_id']) || !isset($data['new_name'])) {
                return new Response([
                    'success' => false,
                    'error' => 'Не указан ID файла или новое имя'
                ]);
            }

            $fileId = $data['file_id'];
            $newName = trim($data['new_name']);

            if (empty($newName)) {
                return new Response([
                    'success' => false,
                    'error' => 'Новое имя файла не может быть пустым'
                ]);
            }

            $conn = $this->db->getConnection();

            $stmt = $conn->prepare(
                "
            SELECT id
            FROM files 
            WHERE id = ? AND (
                user_id = ? OR 
                id IN (SELECT item_id FROM shared_items WHERE shared_with_user_id = ? AND item_type = 'file')
            )
        "
            );
            $stmt->execute([$fileId, $userId, $userId]);

            if (!$stmt->fetch()) {
                return new Response([
                    'success' => false,
                    'error' => 'Файл не найден или нет прав доступа'
                ]);
            }

            $stmt = $conn->prepare("UPDATE files SET filename = ? WHERE id = ?");
            $result = $stmt->execute([$newName, $fileId]);

            if ($result) {
                return new Response([
                    'success' => true,
                    'message' => 'Файл успешно переименован'
                ]);
            } else {
                return new Response([
                    'success' => false,
                    'error' => 'Ошибка при переименовании файла'
                ]);
            }
        } catch (Exception $e) {
            return new Response([
                'success' => false,
                'error' => 'Ошибка при переименовании файла: ' . $e->getMessage()
            ]);
        }
    }

    public function move(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        $data = $request->getData();
        $fileId = $data['file_id'] ?? null;
        $targetDirId = $data['target_directory_id'] ?? null;
        $userId = $_SESSION['user_id'];

        if (!$fileId || !$targetDirId) {
            return new Response(['success' => false, 'error' => 'Недостаточно данных'], 400);
        }

        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("SELECT id FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$fileId, $userId]);
        if (!$stmt->fetch()) {
            return new Response(['success' => false, 'error' => 'Файл не найден или нет прав доступа'], 404);
        }

        if ($targetDirId !== 'root') {
            $stmt = $conn->prepare(
                "
            SELECT id FROM directories WHERE id = ? AND (
                user_id = ? OR 
                id IN (
                    SELECT item_id FROM shared_items 
                    WHERE item_type = 'directory' AND shared_with_user_id = ?
                )
            )
        "
            );
            $stmt->execute([$targetDirId, $userId, $userId]);
            if (!$stmt->fetch()) {
                return new Response(['success' => false, 'error' => 'Папка назначения не найдена или нет прав доступа'], 404);
            }
        } else {

            $stmt = $conn->prepare("SELECT id FROM directories WHERE parent_id IS NULL AND user_id = ?");
            $stmt->execute([$userId]);
            $rootDir = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rootDir) {
                return new Response(['success' => false, 'error' => 'Корневая папка не найдена'], 404);
            }
            $targetDirId = $rootDir['id'];
        }

        $stmt = $conn->prepare("UPDATE files SET directory_id = ? WHERE id = ?");
        $result = $stmt->execute([$targetDirId, $fileId]);

        if ($result) {
            return new Response(['success' => true, 'message' => 'Файл успешно перемещён']);
        } else {
            return new Response(['success' => false, 'error' => 'Ошибка при перемещении файла']);
        }
    }

    public function remove(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        $userId = $_SESSION['user_id'];
        $fileId = $request->routeParams['id'] ?? null;

        if (!$fileId) {
            return new Response(['success' => false, 'error' => 'ID файла не указан'], 400);
        }

        try {
            $success = $this->fileService->deleteFile((int)$fileId, $userId);
            return new Response(['success' => $success]);
        } catch (Exception $e) {
            return new Response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function unshare(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        $userId = $_SESSION['user_id'];
        $data = $request->getData();
        $fileId = $data['file_id'] ?? null;

        if (!$fileId) {
            return new Response(['success' => false, 'error' => 'ID файла не указан'], 400);
        }

        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("SELECT id FROM shared_items WHERE item_id = ? AND shared_with_user_id = ? AND item_type = 'file'");
            $stmt->execute([$fileId, $userId]);
            if (!$stmt->fetch()) {
                return new Response(['success' => false, 'error' => 'Доступ к файлу не найден или уже отозван'], 404);
            }

            $stmt = $conn->prepare("DELETE FROM shared_items WHERE item_id = ? AND shared_with_user_id = ? AND item_type = 'file'");
            $stmt->execute([$fileId, $userId]);

            return new Response(['success' => true, 'message' => 'Доступ к файлу успешно отозван']);
        } catch (Exception $e) {
            return new Response(['success' => false, 'error' => 'Ошибка при отзыве доступа: ' . $e->getMessage()], 500);
        }
    }
}
