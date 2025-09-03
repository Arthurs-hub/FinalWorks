<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\IFileRepository;
use Exception;



class FileService implements IFileService
{
    private IFileRepository $fileRepository;
    private IDirectoryService $directoryService;
    private IUserService $userService;


    public function __construct(IFileRepository $fileRepository, IDirectoryService $directoryService, IUserService $userService)
    {
        $this->fileRepository = $fileRepository;
        $this->directoryService = $directoryService;
        $this->userService = $userService;
    }

    public function getFilesList(int $userId, $directoryId = null): array
    {
        if ($directoryId === 'root' || $directoryId === null || $directoryId === '' || $directoryId === 0 || $directoryId === '0') {
            $directoryId = $this->fileRepository->getRootDirectoryId($userId);
        } else {
            $directoryId = (int) $directoryId;
        }

        if (!$this->fileRepository->checkDirectoryAccess($directoryId, $userId)) {
            $directoryId = $this->fileRepository->getRootDirectoryId($userId);
        }

        $directories = $this->fileRepository->getDirectoriesInDirectory($userId, $directoryId);

        if ($directoryId === $this->fileRepository->getRootDirectoryId($userId)) {
            $sharedRootIds = $this->fileRepository->getSharedRootDirectoryIds($userId);
            $files = $this->fileRepository->getFilesInRootDirectory($userId, $directoryId, $sharedRootIds);
        } else {
            $files = $this->fileRepository->getFilesInDirectoryWithShared($userId, $directoryId);
        }

        foreach ($files as &$file) {
            if (isset($file['file_size'])) {
                $file['file_size'] = \App\Utils\FileUtils::formatFileSize((int) $file['file_size']);
            }
        }
        unset($file);

        $currentDirectory = $this->getCurrentDirectory($directoryId, $userId);

        return [
            'files' => $files,
            'directories' => $directories,
            'current_directory' => $currentDirectory,
            'current_directory_id' => $directoryId,
        ];
    }

    public function getCurrentDirectory($directoryId, $userId)
    {
        if ($directoryId === 'root') {
            return [
                'id' => 'root',
                'name' => 'Корневая папка',
                'parent_id' => null,
                'shared_by' => null,
                'is_shared' => false
            ];
        }

        $directory = $this->fileRepository->getCurrentDirectory($directoryId);

        if (!$directory) {
            return null;
        }

        $sharedInfo = $this->fileRepository->isDirectorySharedToUser($directoryId, $userId);
        if ($sharedInfo) {
            $directory['shared_by'] = $sharedInfo['shared_by'];
            $directory['is_shared'] = true;
        } else {
            $directory['shared_by'] = null;
            $directory['is_shared'] = false;
        }

        return $directory;
    }

    public function getFileForDownload(int $fileId, int $userId): array
    {
        return $this->fileRepository->getFileWithAccess($fileId, $userId);
    }

    public function getFileForPreview(int $fileId, int $userId): array
    {
        return $this->fileRepository->getFileWithAccess($fileId, $userId);
    }

    public function downloadFile(int $fileId, int $userId): array
    {
        return $this->fileRepository->getFileWithAccess($fileId, $userId);
    }

    public function previewFile(int $fileId, int $userId): array
    {
        return $this->fileRepository->getFileWithAccess($fileId, $userId);
    }

    public function deleteFile(?int $fileId, int $userId): array
    {
        if (!$fileId) {
            return ['success' => false, 'error' => 'ID файла не указан'];
        }

        try {
            $file = $this->fileRepository->deleteFile($fileId, $userId);

            if ($file) {
                $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $success = $this->fileRepository->removeFile($fileId, $userId);

                if ($success) {
                    Logger::info("File deleted successfully", [
                        'file_id' => $fileId,
                        'user_id' => $userId,
                    ]);
                    return ['success' => true, 'message' => 'Файл успешно удален'];
                } else {
                    return ['success' => false, 'error' => 'Ошибка при удалении записи из базы данных'];
                }
            }

            return ['success' => false, 'error' => 'Файл не найден или нет прав на удаление'];
        } catch (Exception $e) {
            Logger::error("FileService::deleteFile error", [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при удалении файла'];
        }
    }

    public function listFiles($directoryId, int $userId): array
    {
        try {
            $result = $this->getFilesList($userId, $directoryId);
            return array_merge(['success' => true], $result);
        } catch (Exception $e) {
            Logger::error("FileService::listFiles error", [
                'error' => $e->getMessage(),
                'directory_id' => $directoryId,
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при загрузке файлов'];
        }
    }

    public function renameFile(?int $fileId, array $data, int $userId): array
    {
        if (!$fileId) {
            return ['success' => false, 'error' => 'ID файла не указан'];
        }

        $newName = $data['new_name'] ?? '';
        if (empty($newName)) {
            return ['success' => false, 'error' => 'Новое имя файла не указано'];
        }

        try {
            if (!$this->fileRepository->checkFileAccessForRename($fileId, $userId)) {
                return ['success' => false, 'error' => 'Файл не найден или нет прав на переименование'];
            }

            $success = $this->fileRepository->renameFile($fileId, $newName);
            if ($success) {
                Logger::info("File renamed successfully", [
                    'file_id' => $fileId,
                    'new_name' => $newName,
                    'user_id' => $userId,
                ]);
                return ['success' => true, 'message' => 'Файл успешно переименован'];
            }
            return ['success' => false, 'error' => 'Ошибка при переименовании файла'];
        } catch (Exception $e) {
            Logger::error("FileService::renameFile error", [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при переименовании файла'];
        }
    }

    public function moveFile(?int $fileId, array $data, int $userId): array
    {
        if (!$fileId) {
            return ['success' => false, 'error' => 'ID файла не указан'];
        }

        $newDirectoryId = $data['directory_id'] ?? null;
        if (!$newDirectoryId) {
            return ['success' => false, 'error' => 'ID новой папки не указан'];
        }

        try {
            if (!$this->fileRepository->checkFileAccessForRename($fileId, $userId)) {
                return ['success' => false, 'error' => 'Файл не найден или нет прав на перемещение'];
            }

            if ($newDirectoryId === 'root') {
                $newDirectoryId = $this->fileRepository->getRootDirectoryId($userId);
            } else {
                $newDirectoryId = (int) $newDirectoryId;
                if (!$this->fileRepository->checkDirectoryAccessForMove($newDirectoryId, $userId)) {
                    return ['success' => false, 'error' => 'Нет прав доступа к целевой папке'];
                }
            }

            $success = $this->fileRepository->moveFile($fileId, $newDirectoryId);
            if ($success) {
                Logger::info("File moved successfully", [
                    'file_id' => $fileId,
                    'new_directory_id' => $newDirectoryId,
                    'user_id' => $userId,
                ]);
                return ['success' => true, 'message' => 'Файл успешно перемещен'];
            }
            return ['success' => false, 'error' => 'Ошибка при перемещении файла'];
        } catch (Exception $e) {
            Logger::error("FileService::moveFile error", [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при перемещении файла'];
        }
    }

    public function shareFile(?int $fileId, array $data, int $userId): array
    {
        if (!$fileId) {
            return ['success' => false, 'error' => 'ID файла не указан'];
        }

        try {

            $file = $this->fileRepository->getFileInfo($fileId, $userId);
            if (!$file) {
                return ['success' => false, 'error' => 'Файл не найден или нет прав доступа'];
            }

            $targetUserId = $data['user_id'] ?? $data['target_user_id'] ?? null;
            $targetEmail = $data['email'] ?? $data['target_email'] ?? null;

            if (!$targetUserId && $targetEmail) {
                $targetUser = $this->userService->findUserByEmail($targetEmail);
                if ($targetUser) {
                    $targetUserId = $targetUser['id'];
                } else {
                    return ['success' => false, 'error' => 'Пользователь с таким email не найден'];
                }
            }

            if (!$targetUserId) {
                return ['success' => false, 'error' => 'Не указан пользователь для расшаривания'];
            }

            if ($targetUserId == $userId) {
                return ['success' => false, 'error' => 'Нельзя предоставить доступ самому себе'];
            }

            if ($this->fileRepository->checkExistingShare($fileId, $targetUserId)) {
                return ['success' => false, 'error' => 'Файл уже расшарен этому пользователю'];
            }

            $success = $this->fileRepository->createShare($fileId, $userId, $targetUserId);
            if ($success) {
                Logger::info("File shared successfully", [
                    'file_id' => $fileId,
                    'from_user' => $userId,
                    'to_user' => $targetUserId,
                ]);
                return ['success' => true, 'message' => 'Файл успешно расшарен'];
            }
            return ['success' => false, 'error' => 'Ошибка при расшаривании файла'];
        } catch (Exception $e) {
            Logger::error("FileService::shareFile error", [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при расшаривании файла'];
        }
    }

    public function unshareFile(?int $fileId, int $targetUserId): array
    {
        if (!$fileId) {
            return ['success' => false, 'error' => 'ID файла не указан'];
        }

        if (!$targetUserId) {
            return ['success' => false, 'error' => 'ID пользователя не указан'];
        }

        try {
            if (!$this->fileRepository->checkSharedFileAccess($fileId, $targetUserId)) {
                return ['success' => false, 'error' => 'Файл не найден или не расшарен с этим пользователем'];
            }

            if ($this->fileRepository->removeSharedAccess($fileId, $targetUserId)) {
                Logger::info("File unshared successfully", [
                    'file_id' => $fileId,
                    'user_id' => $targetUserId,
                ]);
                return ['success' => true, 'message' => 'Доступ к файлу успешно отозван'];
            }

            return ['success' => false, 'error' => 'Ошибка при отзыве доступа'];
        } catch (Exception $e) {
            Logger::error("FileService::unshareFile error", [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'user_id' => $targetUserId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при отзыве доступа'];
        }
    }

    public function getSharedFilesList(int $userId): array
    {
        try {
            $sharedFiles = $this->fileRepository->getSharedFiles($userId);

            foreach ($sharedFiles as &$file) {
                if (isset($file['file_size'])) {
                    $file['file_size_formatted'] = \App\Utils\FileUtils::formatFileSize((int) $file['file_size']);
                }
            }
            unset($file);

            return ['success' => true, 'files' => $sharedFiles];
        } catch (Exception $e) {
            Logger::error("FileService::getSharedFilesList error", [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при загрузке расшаренных файлов'];
        }
    }

    public function getFileInformation(?int $fileId, int $userId): array
    {
        if (!$fileId) {
            return ['success' => false, 'error' => 'ID файла не указан'];
        }

        $file = $this->fileRepository->getFileInfo($fileId, $userId);

        if (!$file) {
            return ['success' => false, 'error' => 'Файл не найден или нет прав доступа'];
        }

        if (!isset($file['file_size_formatted']) && isset($file['size']) && is_numeric($file['size'])) {
            $file['file_size_formatted'] = \App\Utils\FileUtils::formatFileSize((int) $file['size']);
        }

        $filePath = __DIR__ . '/../uploads/files/' . $file['stored_name'];
        if (!file_exists($filePath)) {
            Logger::warning("Physical file not found for info request", ['file_id' => $fileId, 'path' => $filePath]);
        }
        $file['filepath'] = $filePath;

        return ['success' => true, 'file' => $file];
    }

    public function bulkDeleteFiles(array $data, int $userId): array
    {
        $fileIds = $data['file_ids'] ?? [];
        if (empty($fileIds) || !is_array($fileIds)) {
            return ['success' => false, 'error' => 'Не указаны ID файлов для удаления'];
        }

        try {
            $results = [];
            $successCount = 0;

            foreach ($fileIds as $fileId) {
                $deleteResult = $this->deleteFile($fileId, $userId);
                if ($deleteResult['success']) {
                    $successCount++;
                    $results[] = ['file_id' => $fileId, 'success' => true];
                } else {
                    $results[] = [
                        'file_id' => $fileId,
                        'success' => false,
                        'error' => $deleteResult['error'] ?? 'Не удалось удалить файл'
                    ];
                }
            }

            Logger::info("Bulk delete completed", [
                'total' => count($fileIds),
                'success_count' => $successCount,
                'user_id' => $userId,
            ]);

            return [
                'success' => true,
                'results' => $results,
                'success_count' => $successCount,
                'total' => count($fileIds),
                'message' => "Удалено $successCount из " . count($fileIds) . " файлов"
            ];
        } catch (Exception $e) {
            Logger::error("FileService::bulkDeleteFiles error", [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при массовом удалении файлов'];
        }
    }

    public function searchUserFiles(string $query, int $userId): array
    {
        if (empty($query)) {
            return ['success' => false, 'error' => 'Поисковый запрос не указан'];
        }

        try {
            $searchResults = $this->fileRepository->searchFiles($query, $userId);

            foreach ($searchResults as &$file) {
                if (isset($file['file_size'])) {
                    $file['file_size_formatted'] = \App\Utils\FileUtils::formatFileSize((int) $file['file_size']);
                }
            }
            unset($file);

            Logger::info("File search completed", [
                'query' => $query,
                'results_count' => count($searchResults),
                'user_id' => $userId,
            ]);

            return ['success' => true, 'files' => $searchResults, 'query' => $query];
        } catch (Exception $e) {
            Logger::error("FileService::searchUserFiles error", [
                'error' => $e->getMessage(),
                'query' => $query,
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при поиске файлов'];
        }
    }

    public function uploadFiles(int $userId, array $files, array $postData): array
    {
        try {
            if (!isset($files['files'])) {
                return ['success' => false, 'error' => 'Файлы не выбраны'];
            }

            $filesArray = $files['files'];

            $directoryId = $postData['directory_id'] ?? 'root';
            if ($directoryId === null || $directoryId === '' || $directoryId === '0' || $directoryId === 0 || $directoryId === 'null' || $directoryId === 'undefined') {
                $directoryId = 'root';
            }
            $paths = json_decode($postData['paths'] ?? '[]', true);

            if (!is_array($filesArray['name'])) {
                $filesArray = [
                    'name' => [$filesArray['name']],
                    'type' => [$filesArray['type']],
                    'tmp_name' => [$filesArray['tmp_name']],
                    'error' => [$filesArray['error']],
                    'size' => [$filesArray['size']],
                ];
            }

            if (!is_array($paths)) {
                $paths = [];
            }

            $responses = [];
            $fileCount = count($filesArray['name']);

            for ($i = 0; $i < $fileCount; $i++) {
                $file = [
                    'name' => $filesArray['name'][$i],
                    'type' => $filesArray['type'][$i],
                    'tmp_name' => $filesArray['tmp_name'][$i],
                    'error' => $filesArray['error'][$i],
                    'size' => $filesArray['size'][$i],
                ];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $responses[] = [
                        'file' => $file['name'],
                        'success' => false,
                        'error' => 'Ошибка загрузки файла',
                    ];
                    continue;
                }

                $relativePath = $paths[$i] ?? '';
                $uploadResult = $this->uploadSingleFile($file, $directoryId, $userId, $relativePath);
                $responses[] = array_merge(['file' => $file['name']], $uploadResult);
            }

            $successCount = count(array_filter($responses, fn($r) => $r['success']));
            $totalCount = count($responses);

            Logger::info("Files upload completed", [
                'total' => $totalCount,
                'success_count' => $successCount,
                'user_id' => $userId,
            ]);

            return [
                'success' => $successCount > 0,
                'message' => "Загружено $successCount из $totalCount файлов",
                'results' => $responses,
                'total' => $totalCount,
                'success_count' => $successCount,
            ];
        } catch (Exception $e) {
            Logger::error("FileService::uploadFiles error", [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при загрузке файлов'];
        }
    }

    private function uploadSingleFile(array $file, $directoryId, int $userId, string $relativePath = ''): array
    {
        try {
            if ($file['size'] > 50 * 1024 * 1024) {
                return ['success' => false, 'error' => 'Файл слишком большой (максимум 50MB)'];
            }

            if ($directoryId === 'root' || $directoryId === '' || $directoryId === null || $directoryId === 0 || $directoryId === '0') {
                $directoryId = $this->fileRepository->getRootDirectoryId($userId);
                if (!$directoryId) {
                    return ['success' => false, 'error' => 'Корневая папка не найдена'];
                }
            } else {
                $directoryId = (int) $directoryId;
                if (!$this->fileRepository->checkDirectoryAccess($directoryId, $userId)) {
                    return ['success' => false, 'error' => 'Нет доступа к указанной папке'];
                }
            }

            if ($relativePath !== '' && strpos($relativePath, '/') !== false) {
                $pathParts = explode('/', $relativePath);
                $fileName = array_pop($pathParts);

                $parentId = $directoryId;
                foreach ($pathParts as $folderName) {

                    $result = $this->directoryService->getOrCreateSubdirectory($folderName, $parentId, $userId);

                    if (!$result['success']) {
                        return ['success' => false, 'error' => "Ошибка создания папки {$folderName}: " . ($result['error'] ?? '')];
                    }

                    $parentId = (int) $result['directory_id'];
                }
                $directoryId = $parentId;
                $file['name'] = $fileName;
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $storedName = uniqid() . ($extension ? '.' . $extension : '');

            $uploadDir = __DIR__ . '/../uploads/files/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uploadPath = $uploadDir . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return ['success' => false, 'error' => 'Ошибка при сохранении файла'];
            }

            $fileId = $this->fileRepository->createFile([
                'filename' => $file['name'],
                'stored_name' => $storedName,
                'mime_type' => $file['type'],
                'size' => $file['size'],
                'directory_id' => $directoryId,
                'user_id' => $userId,
            ]);

            Logger::info("File uploaded successfully", [
                'file_id' => $fileId,
                'filename' => $file['name'],
                'user_id' => $userId,
                'directory_id' => $directoryId,
            ]);

            return [
                'success' => true,
                'message' => 'Файл успешно загружен',
                'file_id' => $fileId,
            ];
        } catch (Exception $e) {
            Logger::error("FileService::uploadSingleFile error", [
                'filename' => $file['name'] ?? null,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Ошибка при загрузке файла'];
        }
    }

    public function getFileShares(int $fileId, int $userId): array
    {
        try {
            if (!$fileId) {
                return ['success' => false, 'error' => 'ID файла не указан'];
            }

            $file = $this->fileRepository->getFileById($fileId);
            if (!$file) {
                return ['success' => false, 'error' => 'Файл не найден'];
            }

            if ($file['user_id'] != $userId) {
                return ['success' => false, 'error' => 'Только владелец файла может просматривать список расшариваний'];
            }

            $shares = $this->fileRepository->getFileSharesList($fileId);

            return [
                'success' => true,
                'file_id' => $fileId,
                'file_name' => $file['filename'],
                'shares' => $shares,
                'shares_count' => count($shares)
            ];
        } catch (Exception $e) {
            Logger::error("FileService::getFileShares error", [
                'error' => $e->getMessage(),
                'file_id' => $fileId,
                'user_id' => $userId,
            ]);
            return ['success' => false, 'error' => 'Ошибка при получении списка расшариваний'];
        }
    }
}
