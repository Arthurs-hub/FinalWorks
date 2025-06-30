<?php

namespace App\Services;

use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\AuthMiddleware;
use App\Core\Validator;
use App\Core\Logger;
use App\Services\UserService;
use App\Repositories\DirectoryRepository;
use Exception;
use PDO;
use PDOException;
use RuntimeException;
use ZipArchive;

class DirectoryService
{
    private UserService $userService;
    private Db $db;
    private DirectoryRepository $directoryRepository;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->db = new Db();
        $this->directoryRepository = new DirectoryRepository(); // Инициализируем
    }

    public function add(Request $request): Response
    {
        try {

            $userId = AuthMiddleware::getCurrentUserId();

            $data = $request->getData();

            Validator::required($data['name'] ?? '', 'Имя папки');
            Validator::maxLength($data['name'], 255, 'Имя папки');
            Validator::noSpecialChars($data['name'], 'Имя папки');

            $name = $data['name'];
            $requestedParentId = $data['parent_id'] ?? 'root';

            $conn = $this->db->getConnection();

            $dbParentId = null;
            if ($requestedParentId === 'root') {
                $stmt = $conn->prepare("SELECT id FROM directories WHERE parent_id IS NULL AND user_id = ?");
                $stmt->execute([$userId]);
                $rootDir = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($rootDir) {
                    $dbParentId = $rootDir['id'];
                } else {
                    $stmtCreateRoot = $conn->prepare("INSERT INTO directories (name, parent_id, user_id) VALUES ('Корневая папка', NULL, ?)");
                    $stmtCreateRoot->execute([$userId]);
                    $dbParentId = $conn->lastInsertId();
                }
            } else {
                $dbParentId = $requestedParentId;
                $stmt = $conn->prepare("SELECT id FROM directories WHERE id = ? AND user_id = ?");
                $stmt->execute([$dbParentId, $userId]);
                $validParentDir = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$validParentDir) {
                    Logger::error("Invalid parent directory", ['parent_id' => $dbParentId, 'user_id' => $userId]);
                    return new Response(['success' => false, 'error' => 'Указанная родительская папка не найдена или недоступна.']);
                }
            }

            $stmt = $conn->prepare("INSERT INTO directories (name, parent_id, user_id) VALUES (?, ?, ?)");
            $insertResult = $stmt->execute([$name, $dbParentId, $userId]);

            if ($insertResult) {
                Logger::info("Directory created: {$name} by user {$userId}");
                return new Response(['success' => true, 'message' => 'Папка успешно создана']);
            } else {
                return new Response(['success' => false, 'error' => 'Ошибка при создании папки в базе данных']);
            }
        } catch (\InvalidArgumentException $e) {

            return new Response(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (PDOException $e) {
            Logger::error("Database error in DirectoryService::add", ['error' => $e->getMessage()]);
            return new Response(['success' => false, 'error' => 'Ошибка базы данных при создании папки'], 500);
        } catch (Exception $e) {
            Logger::error("Unexpected error in DirectoryService::add", ['error' => $e->getMessage()]);
            return new Response(['success' => false, 'error' => 'Внутренняя ошибка сервера'], 500);
        }
    }

    public function rename(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован']);
        }
        $data = $request->getData();
        $id = $data['id'] ?? null;
        $newName = $data['new_name'] ?? null;
        $userId = $_SESSION['user_id'];
        if (!$id || !$newName) {
            return new Response(['success' => false, 'error' => 'Недостаточно данных']);
        }
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("UPDATE directories SET name = ? WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$newName, $id, $userId]);
        return new Response(['success' => $result]);
    }

    public function get(Request $request): Response
    {
        try {
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('ETag: "' . uniqid() . '"');

            if (!isset($_SESSION['user_id'])) {
                return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
            }

            $userId = $_SESSION['user_id'];
            $idRaw = $request->routeParams['id'] ?? null;

            error_log("DirectoryService::get called with idRaw: " . var_export($idRaw, true) . ", userId: " . $userId);

            if ($idRaw === 'root' || $idRaw === null || $idRaw === '' || $idRaw === 0 || $idRaw === '0') {
                $id = null;
            } else {
                $id = (int)$idRaw;
            }

            $conn = $this->db->getConnection();

            $isRootDirectory = false;

            if ($id === null) {
                error_log("Loading root directory for user: " . $userId);
                $isRootDirectory = true;

                $conn->beginTransaction();

                $stmt = $conn->prepare("
                    SELECT id, name, parent_id, user_id
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
                        'parent_id' => null,
                        'user_id' => $userId,
                        'is_shared' => 0,
                        'is_shared_by_owner' => 0,
                        'shared_by' => null,
                    ];
                } else {
                    $stmtShared = $conn->prepare("
                        SELECT 1 FROM shared_items 
                        WHERE item_type = 'directory' AND item_id = ? AND shared_by_user_id = ? LIMIT 1
                    ");
                    $stmtShared->execute([$directory['id'], $userId]);
                    $directory['is_shared_by_owner'] = $stmtShared->fetch() ? 1 : 0;
                    $directory['is_shared'] = 0;
                    $directory['shared_by'] = null;
                }

                $conn->commit();
            } else {
                error_log("Loading directory with id: " . $id . " for user: " . $userId);

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
                    LEFT JOIN users u ON d.user_id = u.id
                    WHERE d.id = ? AND (
                        d.user_id = ? OR 
                        d.id IN (
                            SELECT item_id FROM shared_items 
                            WHERE item_type = 'directory' 
                            AND shared_with_user_id = ?
                        )
                    )
                ");
                $stmt->execute([$userId, $userId, $userId, $id, $userId, $userId]);
                $directory = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($directory && $directory['parent_id'] === null && $directory['user_id'] == $userId) {
                    $isRootDirectory = true;
                    error_log("This is user's root directory (parent_id is null)");
                }
            }

            if (!$directory) {
                return new Response(['success' => false, 'error' => 'Папка не найдена'], 404);
            }

            $stmt = $conn->prepare("
                SELECT DISTINCT d.id, d.name, d.parent_id, d.user_id,
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
                LEFT JOIN users u ON d.user_id = u.id
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
            $stmt->execute([
                $userId,
                $userId,
                $userId,
                $directory['id'],
                $directory['id'],
                $userId,
                $userId
            ]);
            $subdirectories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $shared_directories = [];
            if ($isRootDirectory) {
                error_log("Loading shared directories for root directory, userId: " . $userId);

                $stmt = $conn->prepare("
                    SELECT d.id, d.name, d.parent_id, d.user_id,
                           u.email as shared_by,
                           1 as is_shared,
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
                    LEFT JOIN users u ON d.user_id = u.id
                    WHERE d.id IN (
                        SELECT item_id FROM shared_items 
                        WHERE item_type = 'directory' 
                        AND shared_with_user_id = ?
                    )
                    AND (
                        d.parent_id IS NULL
                        OR d.parent_id NOT IN (
                            SELECT item_id FROM shared_items 
                            WHERE item_type = 'directory' 
                            AND shared_with_user_id = ?
                        )
                    )
                    AND d.id != ?
                    ORDER BY d.name
                ");
                $stmt->execute([$userId, $userId, $userId, $directory['id']]);
                $shared_directories = $stmt->fetchAll(PDO::FETCH_ASSOC);

                error_log("Found " . count($shared_directories) . " shared directories");
                foreach ($shared_directories as $sharedDir) {
                    error_log("Shared directory: " . $sharedDir['name'] . " (ID: " . $sharedDir['id'] . ") from " . $sharedDir['shared_by']);
                }
            }

            $stmt = $conn->prepare("
                SELECT DISTINCT f.id, f.filename AS name, f.stored_name, f.mime_type, f.created_at, f.user_id,
                       f.size AS file_size, u.email as shared_by,
                       CASE 
                           WHEN f.user_id = ? THEN 0
                           WHEN EXISTS (
                               SELECT 1 FROM shared_items si 
                               WHERE si.item_id = f.id AND si.item_type = 'file'
                           ) THEN 1
                           ELSE 0
                       END as is_shared,
                       CASE
                           WHEN EXISTS (
                               SELECT 1 FROM shared_items si2 
                               WHERE si2.item_id = f.id AND si2.item_type = 'file' AND si2.shared_by_user_id = ?
                           ) THEN 1
                           ELSE 0
                       END as is_shared_by_owner
                FROM files f
                LEFT JOIN users u ON f.user_id = u.id
                LEFT JOIN shared_items si ON f.id = si.item_id AND si.item_type = 'file'
                WHERE (
                    (f.user_id = ? AND f.directory_id = ?) 
                    OR 
                    (si.shared_with_user_id = ? AND f.directory_id = ?)
                )
                ORDER BY f.filename
            ");
            $stmt->execute([$userId, $userId, $userId, $directory['id'], $userId, $directory['id']]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($directory['user_id'] != $userId) {
                foreach ($files as &$file) {
                    $file['is_shared'] = 1;
                    $file['shared_by'] = $directory['shared_by'] ?? null;
                    $file['is_shared_by_owner'] = 0;
                }
                unset($file);
            }

            foreach ($files as &$file) {
                $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
                if (file_exists($filePath)) {
                    $file['file_size'] = $this->formatFileSize(filesize($filePath));
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $file['mime_type'] = finfo_file($finfo, $filePath);
                    finfo_close($finfo);
                    $file['preview_available'] = strpos($file['mime_type'], 'image/') === 0 ||
                        $file['mime_type'] === 'application/pdf';
                } else {
                    $file['file_size'] = 'Н/Д';
                    $file['mime_type'] = 'Н/Д';
                    $file['preview_available'] = false;
                }
            }
            unset($file);

            error_log("Returning response with " . count($subdirectories) . " subdirectories and " . count($shared_directories) . " shared directories");

            return new Response([
                'success' => true,
                'directory' => $directory,
                'subdirectories' => $subdirectories,
                'shared_directories' => $shared_directories,
                'files' => $files
            ]);
        } catch (Exception $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("DirectoryService::get exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return new Response(['success' => false, 'error' => 'Ошибка сервера при получении директории'], 500);
        }
    }

    public function move(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        try {
            $userId = $_SESSION['user_id'];
            $data = $request->getData();

            error_log("Move request data: " . print_r($data, true));

            if (!isset($data['directory_id']) || !isset($data['target_parent_id'])) {
                error_log("Missing directory_id or target_parent_id");
                return new Response([
                    'success' => false,
                    'error' => 'Недостаточно данных'
                ], 400);
            }

            $directoryId = (int)$data['directory_id'];
            $targetParentId = $data['target_parent_id'];

            if (!$this->directoryRepository->checkDirectoryOwnership($directoryId, $userId)) {
                return new Response(['success' => false, 'error' => 'Нет прав доступа к папке'], 403);
            }

            if ($targetParentId === 'root') {
                $rootDir = $this->directoryRepository->findRootDirectory($userId);
                if (!$rootDir) {
                    $targetParentId = $this->directoryRepository->createRootDirectory($userId);
                } else {
                    $targetParentId = $rootDir['id'];
                }
            } else {
                $targetParentId = (int)$targetParentId;
                if (!$this->directoryRepository->checkDirectoryOwnership($targetParentId, $userId)) {
                    return new Response(['success' => false, 'error' => 'Нет прав доступа к целевой папке'], 403);
                }
            }

            if ($directoryId === $targetParentId) {
                return new Response(['success' => false, 'error' => 'Нельзя переместить папку саму в себя'], 400);
            }

            if ($this->directoryRepository->moveDirectory($directoryId, $targetParentId)) {
                Logger::info("Directory moved successfully", [
                    'directory_id' => $directoryId,
                    'target_parent_id' => $targetParentId,
                    'user_id' => $userId
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Папка успешно перемещена'
                ]);
            }

            return new Response(['success' => false, 'error' => 'Ошибка при перемещении папки'], 500);
        } catch (Exception $e) {
            Logger::error("DirectoryService::move error", [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            return new Response(['success' => false, 'error' => 'Ошибка при перемещении папки'], 500);
        }
    }


    public function delete(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        $userId = $_SESSION['user_id'];
        $idRaw = $request->routeParams['id'];

        if ($idRaw === 'root' || $idRaw === null || $idRaw === '' || $idRaw === 0 || $idRaw === '0') {
            $id = null;
        } else {
            $id = (int)$idRaw;
        }

        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("
                SELECT id FROM directories 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$id, $userId]);
            $dir = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dir) {
                return new Response([
                    'success' => false,
                    'error' => 'Вы можете удалять только свои папки'
                ]);
            }

            $stmt = $conn->prepare("
                DELETE FROM shared_items 
                WHERE item_type = 'directory' AND item_id = ?
            ");
            $stmt->execute([$id]);

            $stmt = $conn->prepare("
                DELETE FROM directories 
                WHERE id = ? AND user_id = ?
            ");
            $result = $stmt->execute([$id, $userId]);

            return new Response(['success' => $result]);
        } catch (PDOException $e) {
            return new Response([
                'success' => false,
                'error' => 'Ошибка базы данных: ' . $e->getMessage()
            ]);
        }
    }

    public function share(Request $request): Response
    {
        try {
            $userId = AuthMiddleware::getCurrentUserId();
            $data = $request->getData();

            Validator::required($data['email'] ?? '', 'Email');
            Validator::email($data['email']);
            Validator::required($data['folder_id'] ?? '', 'ID папки');

            $conn = $this->db->getConnection();
            $conn->beginTransaction();

            try {
                $stmt = $conn->prepare("SELECT id, name FROM directories WHERE id = ? AND user_id = ?");
                $stmt->execute([$data['folder_id'], $userId]);
                $directory = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$directory) {
                    throw new RuntimeException('Папка не найдена или нет прав доступа');
                }

                $targetUser = $this->userService->findUserByEmail($data['email']);
                if (!$targetUser) {
                    throw new RuntimeException('Пользователь не найден');
                }

                if ($targetUser['id'] == $userId) {
                    throw new RuntimeException('Нельзя предоставить доступ самому себе');
                }

                $stmt = $conn->prepare("SELECT id FROM shared_items WHERE item_id = ? AND item_type = 'directory' AND shared_with_user_id = ?");
                $stmt->execute([$data['folder_id'], $targetUser['id']]);

                if ($stmt->fetch()) {
                    throw new RuntimeException('Доступ уже предоставлен этому пользователю');
                }

                $stmt = $conn->prepare("INSERT INTO shared_items (item_type, item_id, shared_by_user_id, shared_with_user_id) VALUES ('directory', ?, ?, ?)");
                $stmt->execute([$data['folder_id'], $userId, $targetUser['id']]);

                $this->shareDirectoryContentsRecursively($conn, $data['folder_id'], $userId, $targetUser['id']);

                $conn->commit();

                Logger::info("Directory shared successfully", [
                    'folder_id' => $data['folder_id'],
                    'from_user' => $userId,
                    'to_user' => $targetUser['id']
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Доступ к папке и её содержимому успешно предоставлен'
                ]);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
        } catch (\InvalidArgumentException $e) {
            return new Response(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            Logger::error("DirectoryService::share exception", ['error' => $e->getMessage()]);
            return new Response(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function shareDirectoryContentsRecursively($conn, $directoryId, $ownerId, $targetUserId): void
    {
        $stmt = $conn->prepare("
            SELECT id, filename 
            FROM files 
            WHERE directory_id = ? AND user_id = ?
        ");
        $stmt->execute([$directoryId, $ownerId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("Sharing files in directory $directoryId: " . count($files) . " files found");

        foreach ($files as $file) {
            $stmtCheck = $conn->prepare("
            SELECT id FROM shared_items 
            WHERE item_type = 'file' AND item_id = ? AND shared_with_user_id = ?
        ");
            $stmtCheck->execute([$file['id'], $targetUserId]);

            if (!$stmtCheck->fetch()) {
                $stmtInsert = $conn->prepare("
                INSERT INTO shared_items 
                (item_type, item_id, shared_by_user_id, shared_with_user_id) 
                VALUES ('file', ?, ?, ?)
            ");
                $result = $stmtInsert->execute([$file['id'], $ownerId, $targetUserId]);
                error_log("Shared file {$file['filename']} (ID: {$file['id']}) with user $targetUserId: " . ($result ? 'SUCCESS' : 'FAILED'));
            } else {
                error_log("File {$file['filename']} already shared with user $targetUserId");
            }
        }

        $stmt = $conn->prepare("
            SELECT id, name 
            FROM directories 
            WHERE parent_id = ? AND user_id = ?
        ");
        $stmt->execute([$directoryId, $ownerId]);
        $subdirectories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subdirectories as $subdir) {
            $stmtCheck = $conn->prepare("
            SELECT id FROM shared_items 
            WHERE item_type = 'directory' AND item_id = ? AND shared_with_user_id = ?
        ");
            $stmtCheck->execute([$subdir['id'], $targetUserId]);

            if (!$stmtCheck->fetch()) {
                $stmtInsert = $conn->prepare("
                INSERT INTO shared_items 
                (item_type, item_id, shared_by_user_id, shared_with_user_id) 
                VALUES ('directory', ?, ?, ?)
            ");
                $stmtInsert->execute([$subdir['id'], $ownerId, $targetUserId]);
            }

            $this->shareDirectoryContentsRecursively($conn, $subdir['id'], $ownerId, $targetUserId);
        }
    }

    public function unshare(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован'], 401);
        }

        $userId = $_SESSION['user_id'];
        $directoryId = $request->getData()['directory_id'] ?? null;

        if (!$directoryId) {
            return new Response(['success' => false, 'error' => 'ID папки не указан'], 400);
        }

        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("
                SELECT id FROM shared_items 
                WHERE item_id = ? 
                  AND item_type = 'directory' 
                  AND shared_with_user_id = ?
            ");
            $stmt->execute([$directoryId, $userId]);

            if (!$stmt->fetch()) {
                return new Response(['success' => false, 'error' => 'Папка не найдена или не расшарена для вас'], 404);
            }

            $stmt = $conn->prepare("
                DELETE FROM shared_items 
                WHERE item_id = ? 
                  AND item_type = 'directory' 
                  AND shared_with_user_id = ?
            ");
            $stmt->execute([$directoryId, $userId]);

            return new Response(['success' => true, 'message' => 'Доступ к папке успешно отозван']);
        } catch (Exception $e) {
            return new Response(['success' => false, 'error' => 'Ошибка при отзыве доступа: ' . $e->getMessage()], 500);
        }
    }

    public function download(Request $request): Response
    {
        if (!isset($_SESSION['user_id'])) {
            return new Response(['success' => false, 'error' => 'Пользователь не авторизован']);
        }

        try {
            $userId = $_SESSION['user_id'];
            $directoryIdRaw = $request->routeParams['id'] ?? null;
            error_log("Download called for directoryId=$directoryIdRaw, userId=$userId");

            if ($directoryIdRaw === 'root' || $directoryIdRaw === null || $directoryIdRaw === '' || $directoryIdRaw === 0 || $directoryIdRaw === '0') {
                $directoryId = null;
            } else {
                $directoryId = (int)$directoryIdRaw;
            }

            if (!$directoryId) {
                throw new RuntimeException('ID папки не указан');
            }

            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("
                SELECT 
                    d.id,
                    d.name,
                    d.user_id
                FROM directories d
                LEFT JOIN shared_items si ON d.id = si.item_id AND si.item_type = 'directory'
                WHERE d.id = ? AND (
                    d.user_id = ? OR 
                    si.shared_with_user_id = ?
                )
            ");
            $stmt->execute([$directoryId, $userId, $userId]);
            $directory = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$directory) {
                throw new RuntimeException('Папка не найдена или нет прав доступа');
            }

            $zipFileName = tempnam(sys_get_temp_dir(), 'dir_');
            $zip = new ZipArchive();

            if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Не удалось создать ZIP-архив');
            }

            $this->addDirectoryToZip($zip, $directoryId, $directory['name'], $conn, $userId);

            $zip->close();

            if (!file_exists($zipFileName) || filesize($zipFileName) === 0) {
                throw new RuntimeException('Архив не создан');
            }

            $safeFileName = $this->sanitizeFileName($directory['name']) . '.zip';

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $safeFileName . '"');
            header('Content-Length: ' . filesize($zipFileName));
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($zipFileName);
            unlink($zipFileName);
            error_log("Download finished for directoryId=$directoryId, file=$zipFileName, size=" . filesize($zipFileName));
            exit;
        } catch (Exception $e) {
            return new Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directoryId, string $path, PDO $conn, int $userId, string $parentPath = ''): void
    {
        $stmt = $conn->prepare("
            SELECT 
                f.id,
                f.filename,
                f.stored_name
            FROM files f
            LEFT JOIN shared_items si ON f.id = si.item_id AND si.item_type = 'file'
            WHERE f.directory_id = ? AND (
                f.user_id = ? OR 
                si.shared_with_user_id = ?
            )
        ");
        $stmt->execute([$directoryId, $userId, $userId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $currentPath = $parentPath . ($parentPath ? '/' : '') . $this->sanitizeFileName($path);

        $zip->addEmptyDir($currentPath);

        foreach ($files as $file) {
            $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
            if (file_exists($filePath)) {
                $zip->addFile(
                    $filePath,
                    $currentPath . '/' . $this->sanitizeFileName($file['filename'])
                );
            }
        }

        $stmt = $conn->prepare("
            SELECT 
                d.id,
                d.name
            FROM directories d
            LEFT JOIN shared_items si ON d.id = si.item_id AND si.item_type = 'directory'
            WHERE d.parent_id = ? AND (
                d.user_id = ? OR 
                si.shared_with_user_id = ?
            )
        ");
        $stmt->execute([$directoryId, $userId, $userId]);
        $subdirectories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subdirectories as $subdir) {
            $this->addDirectoryToZip(
                $zip,
                $subdir['id'],
                $subdir['name'],
                $conn,
                $userId,
                $currentPath
            );
        }
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

    private function sanitizeFileName(string $fileName): string
    {
        $fileName = preg_replace('/[\/\\\:\*\?"<>\|]/', '_', $fileName);
        $fileName = preg_replace('/_+/', '_', $fileName);
        return substr($fileName, 0, 255);
    }

    private function sanitizeFolderName(string $name): string
    {
        return preg_replace('/[\/\\\:\*\?"<>\|]/', '_', $name);
    }
}
