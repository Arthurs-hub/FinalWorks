<?php

namespace App\Services;

use App\Repositories\FileRepository;
use App\Core\Logger;
use App\Core\Response;
use App\Services\UserService;
use Exception;
use RuntimeException;

class FileService
{
    private FileRepository $fileRepository;
    private UserService $userService;

    public function __construct()
    {
        $this->fileRepository = new FileRepository();
        $this->userService = new UserService();
    }

    public function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getFilesList(int $userId, $directoryId = null): array
    {
        if ($directoryId === 'root' || $directoryId === null || $directoryId === '' || $directoryId === 0 || $directoryId === '0') {
            $directoryId = $this->fileRepository->getRootDirectoryId($userId);
        } else {
            $directoryId = (int)$directoryId;
        }

        // Проверяем доступ к директории
        if (!$this->fileRepository->checkDirectoryAccess($directoryId, $userId)) {
            $directoryId = $this->fileRepository->getRootDirectoryId($userId);
        }

        $directories = $this->fileRepository->getDirectoriesInDirectory($userId, $directoryId);

        // Исправляем ошибку в вызове метода
        if ($directoryId === $this->fileRepository->getRootDirectoryId($userId)) {
            $sharedRootIds = $this->fileRepository->getSharedRootDirectoryIds($userId);
            $files = $this->fileRepository->getFilesInRootDirectory($userId, $directoryId, $sharedRootIds);
        } else {
            $files = $this->fileRepository->getFilesInDirectoryWithAccess($userId, $directoryId, $userId);
        }

        foreach ($files as &$file) {
            if (isset($file['file_size'])) {
                $file['file_size'] = $this->formatFileSize((int)$file['file_size']);
            }
        }
        unset($file);

        $currentDirectory = $this->fileRepository->getCurrentDirectory($directoryId);

        return [
            'files' => $files,
            'directories' => $directories,
            'current_directory' => $currentDirectory,
            'current_directory_id' => $directoryId
        ];
    }

    public function deleteFile(int $fileId, int $userId): bool
    {
        try {
            $file = $this->fileRepository->deleteFile($fileId, $userId);

            if ($file) {
                // Исправляем ошибку в обращении к массиву
                $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $this->fileRepository->removeFile($fileId, $userId);

                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("FileService::deleteFile error: " . $e->getMessage());
            throw new RuntimeException('Ошибка при удалении файла: ' . $e->getMessage());
        }
    }

    // Исправляем название метода
    public function rename(int $fileId, string $newName, int $userId): Response
    {
        try {
            // Проверяем доступ к файлу - исправляем название метода
            if (!$this->fileRepository->checkFileAccessForRename($fileId, $userId)) {
                throw new RuntimeException('Файл не найден или нет прав доступа');
            }

            // Переименовываем
            if ($this->fileRepository->renameFile($fileId, $newName)) {
                Logger::info("File renamed successfully", [
                    'file_id' => $fileId,
                    'new_name' => $newName,
                    'user_id' => $userId
                ]);
                
                return new Response([
                    'success' => true,
                    'message' => 'Файл успешно переименован'
                ]);
            }

            throw new RuntimeException('Ошибка при переименовании файла');

        } catch (RuntimeException $e) {
            return new Response(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            Logger::error("FileService::rename error", [
                'file_id' => $fileId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function share(int $fileId, string $targetEmail, int $userId): Response
    {
        try {
            // Проверяем права на файл
            $file = $this->fileRepository->getFileForShare($fileId, $userId);
            if (!$file) {
                throw new RuntimeException('Файл не найден или нет прав доступа');
            }

            // Находим пользователя
            $targetUser = $this->userService->findUserByEmail($targetEmail);
            if (!$targetUser) {
                throw new RuntimeException('Пользователь с указанным email не найден');
            }

            if ($targetUser['id'] == $userId) {
                throw new RuntimeException('Нельзя предоставить доступ самому себе');
            }

            // Проверяем, не расшарен ли уже
            if ($this->fileRepository->checkExistingShare($fileId, $targetUser['id'])) {
                throw new RuntimeException('Доступ к файлу уже предоставлен этому пользователю');
            }

            // Создаем расшаривание
            if ($this->fileRepository->createShare($fileId, $userId, $targetUser['id'])) {
                Logger::info("File shared successfully", [
                    'file_id' => $fileId,
                    'from_user' => $userId,
                    'to_user' => $targetUser['id'],
                    'to_email' => $targetEmail
                ]);

                return new Response([
                    'success' => true,
                    'message' => 'Доступ к файлу успешно предоставлен'
                ]);
            }

            throw new RuntimeException('Ошибка при предоставлении доступа');

        } catch (RuntimeException $e) {
            return new Response(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            Logger::error("FileService::share error", [
                'file_id' => $fileId,
                'user_id' => $userId,
                'target_email' => $targetEmail,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function move(int $fileId, $targetDirId, int $userId): Response
    {
        try {
            // Проверяем доступ к файлу
            if (!$this->fileRepository->checkFileAccessForRename($fileId, $userId)) {
                return new Response(['success' => false, 'error' => 'Файл не найден или нет прав доступа'], 404);
            }

            // Определяем целевую директорию
            if ($targetDirId === 'root') {
                $targetDirId = $this->fileRepository->getRootDirectoryId($userId);
            } else {
                $targetDirId = (int)$targetDirId;
                if (!$this->fileRepository->checkDirectoryAccessForMove($targetDirId, $userId)) {
                    return new Response(['success' => false, 'error' => 'Нет доступа к целевой папке'], 403);
                }
            }

            if ($this->fileRepository->moveFile($fileId, $targetDirId)) {
                return new Response([
                    'success' => true,
                    'message' => 'Файл успешно перемещен'
                ]);
            }

            return new Response(['success' => false, 'error' => 'Ошибка при перемещении файла'], 500);

        } catch (Exception $e) {
            error_log("FileService::move error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при перемещении файла'], 500);
        }
    }

    public function upload(array $file, $directoryId, int $userId, string $relativePath = ''): Response
    {
        try {
            if ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
                return new Response(['success' => false, 'error' => 'Файл слишком большой (максимум 50MB)'], 400);
            }

            if ($directoryId === 'root') {
                $directoryId = $this->fileRepository->getRootDirectoryId($userId);
                if (!$directoryId) {
                    return new Response(['success' => false, 'error' => 'Корневая папка не найдена'], 500);
                }
            } else {
                $directoryId = (int)$directoryId;
                if (!$this->fileRepository->checkDirectoryAccess($directoryId, $userId)) {
                    return new Response(['success' => false, 'error' => 'Нет доступа к указанной папке'], 403);
                }
            }

            error_log("Relative path: " . var_export($relativePath, true));

            if ($relativePath !== '' && strpos($relativePath, '/') !== false) {
                $pathParts = explode('/', $relativePath);
                $fileName = array_pop($pathParts);

                $parentId = $directoryId;
                foreach ($pathParts as $folderName) {
                    // Проверяем, существует ли папка с таким именем у пользователя в parentId
                    $existingDir = $this->fileRepository->findExistingDirectory($folderName, $parentId, $userId);
                    if ($existingDir) {
                        $parentId = (int)$existingDir['id'];
                    } else {
                        // Создаём новую папку
                        $parentId = $this->fileRepository->createDirectory($folderName, $folderName, $parentId, $userId);
                    }
                }
                $directoryId = $parentId;
                $file['name'] = $fileName; // Обновляем имя файла на имя без пути
            } else {
                // Если путь пустой или без папок, имя файла не меняем
                $fileName = $file['name'];
            }

            error_log("Final directory ID: $directoryId, file name: {$file['name']}");

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $storedName = uniqid() . ($extension ? '.' . $extension : '');
            $uploadPath = __DIR__ . '/../uploads/files/' . $storedName;

            $uploadDir = dirname($uploadPath);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return new Response(['success' => false, 'error' => 'Ошибка при сохранении файла'], 500);
            }

            $fileId = $this->fileRepository->createFile([
                'filename' => $file['name'],
                'stored_name' => $storedName,
                'mime_type' => $file['type'],
                'size' => $file['size'],
                'directory_id' => $directoryId,
                'user_id' => $userId
            ]);

            Logger::info("File uploaded successfully", [
                'file_id' => $fileId,
                'filename' => $file['name'],
                'user_id' => $userId
            ]);

            return new Response([
                'success' => true,
                'message' => 'Файл успешно загружен',
                'file_id' => $fileId
            ]);

        } catch (\InvalidArgumentException $e) {
            return new Response(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            Logger::error("FileService::upload error", [
                'filename' => $file['name'] ?? null,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFileInfo(int $fileId, int $userId): ?array
    {
        try {
            $file = $this->fileRepository->getFileInfo($fileId, $userId);
            
            if ($file) {
                // Добавляем информацию о размере файла
                $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
                if (file_exists($filePath)) {
                    $file['file_size_formatted'] = $this->formatFileSize(filesize($filePath));
                    $file['preview_available'] = $this->isPreviewAvailable($file['mime_type']);
                } else {
                    $file['file_size_formatted'] = 'Н/Д';
                    $file['preview_available'] = false;
                }
            }

            return $file;

        } catch (Exception $e) {
            error_log("FileService::getFileInfo error: " . $e->getMessage());
            return null;
        }
    }

    public function unshare(int $fileId, int $userId): Response
    {
        try {
            // Проверяем, что файл расшарен с пользователем
            if (!$this->fileRepository->checkSharedFileAccess($fileId, $userId)) {
                return new Response(['success' => false, 'error' => 'Файл не найден или не расшарен с вами'], 404);
            }

            if ($this->fileRepository->removeSharedAccess($fileId, $userId)) {
                return new Response([
                    'success' => true,
                    'message' => 'Доступ к файлу успешно отозван'
                ]);
            }

            return new Response(['success' => false, 'error' => 'Ошибка при отзыве доступа'], 500);

        } catch (Exception $e) {
            error_log("FileService::unshare error: " . $e->getMessage());
            return new Response(['success' => false, 'error' => 'Ошибка при отзыве доступа'], 500);
        }
    }

    public function downloadFile(int $fileId, int $userId): void
    {
        try {
            $file = $this->fileRepository->getFileForDownload($fileId);
            
            if (!$file) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Файл не найден']);
                exit;
            }

            // Проверяем права доступа (добавляем проверку на администратора)
            $isOwner = $file['user_id'] == $userId;
            $hasSharedAccess = $this->fileRepository->checkFileAccess($fileId, $userId);
            $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
            
            if (!$isOwner && !$hasSharedAccess && !$isAdmin) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Нет прав доступа к файлу']);
                exit;
            }

            $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
            
            if (!file_exists($filePath)) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Физический файл не найден']);
                exit;
            }

            // Определяем тип отображения (inline для preview, attachment для download)
            $disposition = isset($_GET['inline']) ? 'inline' : 'attachment';
            
            // Отправляем файл
            header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
            header('Content-Disposition: ' . $disposition . '; filename="' . $file['filename'] . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($filePath);
            exit;

        } catch (Exception $e) {
            error_log("FileService::downloadFile error: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Ошибка при скачивании файла']);
            exit;
        }
    }

    private function isPreviewAvailable(string $mimeType): bool
    {
        $previewTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/html',
            'text/css',
            'text/javascript',
            'application/json'
        ];

        return in_array($mimeType, $previewTypes);
    }
}