<?php

namespace App\Services;

use App\Core\Logger;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\DirectoryRepository;
use App\Repositories\UserRepository;
use App\Validators\DirectoryValidator;
use Exception;

class DirectoryService
{
    private UserRepository $userRepository;
    private DirectoryRepository $directoryRepository;
    private DirectoryValidator $directoryValidator;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->directoryRepository = new DirectoryRepository();
        $this->directoryValidator = new DirectoryValidator();
    }


    public function addDirectory(array $data, int $userId): array
    {
        try {
            $this->validateDirectoryData($data);

            $baseName = $data['name'];
            $requestedParentId = $data['parent_id'] ?? 'root';

            Logger::info("DirectoryService::addDirectory called with name: {$baseName}, userId: {$userId}");

            $dbParentId = $this->directoryRepository->resolveParentId($requestedParentId, $userId);

            if ($dbParentId === false) {
                return ['success' => false, 'error' => 'Указанная родительская папка не найдена или недоступна.'];
            }

            $name = $baseName;
            $suffix = 0;

            while (true) {
                $existingDir = $this->directoryRepository->findDirectoryByNameAndParent($name, $dbParentId, $userId);
                if (!$existingDir) {
                    break;
                }
                $suffix++;
                $name = $baseName . " ({$suffix})";
            }

            $directoryId = $this->directoryRepository->createDirectory($name, $dbParentId, $userId);

            if ($directoryId) {
                $uploadsDir = __DIR__ . '/../uploads';
                $foldersDir = $uploadsDir . '/folders';

                if (!is_dir($foldersDir)) {
                    if (!mkdir($foldersDir, 0777, true)) {
                        Logger::error("Failed to create folders directory at: $foldersDir");
                        return ['success' => false, 'error' => 'Не удалось создать директорию folders'];
                    }
                }

                $newFolderPath = $foldersDir . '/' . $directoryId;
                if (!is_dir($newFolderPath)) {
                    if (!mkdir($newFolderPath, 0777, true)) {
                        Logger::error("Failed to create physical directory at: $newFolderPath");
                        return ['success' => false, 'error' => 'Не удалось создать физическую папку'];
                    }
                }

                Logger::info("Directory created: {$name} by user {$userId}, physical folder: $newFolderPath");
                return ['success' => true, 'message' => 'Папка успешно создана', 'directory_id' => $directoryId, 'name' => $name];
            }

            return ['success' => false, 'error' => 'Ошибка при создании папки в базе данных'];
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            Logger::error("DirectoryService::addDirectory error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Внутренняя ошибка сервера'];
        }
    }

    public function renameDirectory(array $data, int $userId): array
    {
        $validation = $this->directoryValidator->validateRenameDirectory($data);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['message']];
        }

        try {
            $id = $data['id'] ?? null;
            $newName = $data['new_name'] ?? null;

            $result = $this->directoryRepository->renameDirectory($id, $newName, $userId);

            if ($result === true) {
                return ['success' => true, 'message' => 'Папка переименована'];
            } elseif (is_string($result)) {
                return ['success' => false, 'error' => $result];
            } else {
                return ['success' => false, 'error' => 'Ошибка переименования'];
            }
        } catch (Exception $e) {
            Logger::error("DirectoryService::renameDirectory error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при переименовании папки'];
        }
    }

    public function getDirectoryWithContents($directoryId, int $userId): ?array
    {
        try {
            return $this->directoryRepository->getDirectoryWithContents($directoryId, $userId);
        } catch (Exception $e) {
            Logger::error("DirectoryService::getDirectoryWithContents error", [
                'error' => $e->getMessage(),
                'directory_id' => $directoryId,
                'user_id' => $userId,
            ]);
            return null;
        }
    }

    public function getDirectory(?string $directoryId, int $userId): array
    {
        try {
            Logger::info("DirectoryService::getDirectory called", [
                'directory_id' => $directoryId,
                'user_id' => $userId
            ]);

            $directoryData = $this->directoryRepository->getDirectoryWithContents($directoryId, $userId);

            if (!$directoryData) {
                Logger::warning("Directory not found", [
                    'directory_id' => $directoryId,
                    'user_id' => $userId
                ]);
                return ['success' => false, 'error' => 'Папка не найдена'];
            }

            $processedData = $this->processDirectoryData($directoryData, $userId);

            Logger::info("Directory data processed successfully", [
                'directory_id' => $directoryId,
                'user_id' => $userId,
                'files_count' => count($processedData['files'] ?? []),
                'subdirectories_count' => count($processedData['subdirectories'] ?? [])
            ]);

            return [
                'success' => true,
                'directory' => $processedData['directory'] ?? null,
                'subdirectories' => $processedData['subdirectories'] ?? [],
                'shared_directories' => $processedData['shared_directories'] ?? [],
                'files' => $processedData['files'] ?? []
            ];
        } catch (Exception $e) {
            Logger::error("DirectoryService::getDirectory error", [
                'error' => $e->getMessage(),
                'directory_id' => $directoryId,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'error' => 'Ошибка сервера при получении директории'];
        }
    }

    public function getOrCreateSubdirectory(string $name, int $parentId, int $userId): array
    {
        $existingDir = $this->directoryRepository->findDirectoryByNameAndParent($name, $parentId, $userId);
        if ($existingDir) {
            return ['success' => true, 'directory_id' => $existingDir['id'], 'name' => $name];
        }
        return $this->addDirectory(['name' => $name, 'parent_id' => $parentId], $userId);
    }

    public function moveDirectory(array $data, int $userId): array
    {
        try {
            $validationResult = $this->validateMoveData($data, $userId);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            $result = $this->directoryRepository->moveDirectory(
                $validationResult['directory_id'],
                $validationResult['target_parent_id']
            );

            if ($result) {
                Logger::info("Directory moved successfully", [
                    'directory_id' => $validationResult['directory_id'],
                    'target_parent_id' => $validationResult['target_parent_id'],
                    'user_id' => $userId,
                ]);
                return ['success' => true, 'message' => 'Папка успешно перемещена'];
            }

            return ['success' => false, 'error' => 'Ошибка при перемещении папки'];
        } catch (Exception $e) {
            Logger::error("DirectoryService::moveDirectory error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при перемещении папки'];
        }
    }

    public function deleteDirectory(?string $directoryId, int $userId): array
    {
        try {
            $id = ($directoryId === 'root' || $directoryId === null || $directoryId === '' || $directoryId === 0 || $directoryId === '0') ? null : (int)$directoryId;

            if (!$this->directoryRepository->checkDirectoryOwnership($id, $userId)) {
                return ['success' => false, 'error' => 'Вы можете удалять только свои папки'];
            }

            $result = $this->directoryRepository->deleteDirectory($id, $userId);
            return ['success' => $result, 'message' => $result ? 'Папка удалена' : 'Ошибка удаления'];
        } catch (Exception $e) {
            Logger::error("DirectoryService::deleteDirectory error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при удалении папки'];
        }
    }

    public function shareDirectory(array $data, int $userId): array
    {
        try {
            Logger::info("DirectoryService::shareDirectory called", [
                'data' => $data,
                'user_id' => $userId
            ]);

            $this->validateShareData($data);

            $folderId = $data['folder_id'] ?? $data['directory_id'] ?? null;
            if (!$folderId) {
                return ['success' => false, 'error' => 'ID папки не указан'];
            }

            $targetEmail = $data['email'] ?? $data['target_email'] ?? null;
            if (!$targetEmail) {
                return ['success' => false, 'error' => 'Email получателя не указан'];
            }

            if (!$this->directoryRepository->checkDirectoryOwnership($folderId, $userId)) {
                return ['success' => false, 'error' => 'Нет прав доступа к папке'];
            }

            $result = $this->directoryRepository->shareDirectory(
                (int)$folderId,
                $userId,
                $targetEmail,
                $this->userRepository
            );

            if ($result['success']) {
                Logger::info("Directory shared successfully", [
                    'folder_id' => $folderId,
                    'from_user' => $userId,
                    'to_email' => $targetEmail,
                    'target_user_id' => $result['target_user_id'] ?? null,
                ]);
            } else {
                Logger::warning("Directory sharing failed", [
                    'folder_id' => $folderId,
                    'from_user' => $userId,
                    'to_email' => $targetEmail,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }

            return $result;
        } catch (\InvalidArgumentException $e) {
            Logger::error("DirectoryService::shareDirectory validation error", [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => $userId
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            Logger::error("DirectoryService::shareDirectory error", [
                'error' => $e->getMessage(),
                'data' => $data,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'error' => 'Ошибка при расшаривании папки'];
        }
    }

    public function unshareDirectory(array $data, int $userId): array
    {
        try {
            $directoryId = $data['directory_id'] ?? null;

            if (!$directoryId) {
                return ['success' => false, 'error' => 'ID папки не указан'];
            }

            $this->directoryRepository->unshareDirectoryRecursively($directoryId, $userId);
            return ['success' => true, 'message' => 'Папка и её содержимое успешно расшарены'];
        } catch (Exception $e) {
            Logger::error("DirectoryService::unshareDirectory error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при отмене расшаривания папки'];
        }
    }

    public function downloadDirectory(?string $directoryId, int $userId): Response
    {
        try {
            $id = ($directoryId === 'root' || $directoryId === null || $directoryId === '' || $directoryId === 0 || $directoryId === '0') ? null : (int)$directoryId;

            $zipFilePath = $this->directoryRepository->createZipArchiveForDirectory($id, $userId);

            if (!file_exists($zipFilePath)) {
                throw new Exception('Архив не создан');
            }

            $this->sendZipFile($zipFilePath, $id);
            return new Response(['success' => true]);
        } catch (Exception $e) {
            return new Response(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getSharedDirectoriesList(int $userId): array
    {
        try {
            $sharedDirectories = $this->directoryRepository->getSharedDirectories($userId);
            return ['success' => true, 'shared_directories' => $sharedDirectories];
        } catch (Exception $e) {
            Logger::error("DirectoryService::getSharedDirectoriesList error", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Ошибка при загрузке расшаренных папок'];
        }
    }

    private function validateDirectoryData(array $data): void
    {
        Validator::required($data['name'] ?? '', 'Имя папки');
        Validator::maxLength($data['name'], 255, 'Имя папки');
        Validator::noSpecialChars($data['name'], 'Имя папки');
    }

    private function validateShareData(array $data): void
    {

        $email = $data['email'] ?? $data['target_email'] ?? '';
        if (empty($email)) {
            throw new \InvalidArgumentException('Email получателя не указан');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Некорректный email адрес');
        }

        $folderId = $data['folder_id'] ?? $data['directory_id'] ?? '';
        if (empty($folderId)) {
            throw new \InvalidArgumentException('ID папки не указан');
        }

        if (!is_numeric($folderId) || (int)$folderId <= 0) {
            throw new \InvalidArgumentException('Некорректный ID папки');
        }
    }

    private function validateMoveData(array $data, int $userId): array
    {
        if (!isset($data['directory_id']) || !isset($data['target_parent_id'])) {
            return ['success' => false, 'error' => 'Недостаточно данных'];
        }

        $directoryId = (int)$data['directory_id'];
        $targetParentId = $data['target_parent_id'];

        if (!$this->directoryRepository->checkDirectoryOwnership($directoryId, $userId)) {
            return ['success' => false, 'error' => 'Нет прав доступа к папке'];
        }

        if ($targetParentId === 'root') {
            $targetParentId = $this->directoryRepository->getOrCreateRootDirectoryId($userId);
        } else {
            $targetParentId = (int)$targetParentId;
            if (!$this->directoryRepository->checkDirectoryOwnership($targetParentId, $userId)) {
                return ['success' => false, 'error' => 'Нет прав доступа к целевой папке'];
            }
        }

        if ($directoryId === $targetParentId) {
            return ['success' => false, 'error' => 'Нельзя переместить папку саму в себя'];
        }

        return ['success' => true, 'directory_id' => $directoryId, 'target_parent_id' => $targetParentId];
    }

    private function processDirectoryData(array $directoryData, int $userId): array
    {
        if (isset($directoryData['directory'])) {
            $directory = &$directoryData['directory'];

            if (!empty($directory['parent_id'])) {
                $parentId = $directory['parent_id'];

                $hasAccessToParent = $this->directoryRepository->checkDirectoryAccess($parentId, $userId);

                if (!$hasAccessToParent) {

                    $directory['real_parent_id'] = $parentId;

                    $directory['parent_id'] = null;
                    $directory['is_shared_root'] = true;
                } else {
                    $directory['real_parent_id'] = $parentId;
                    $directory['is_shared_root'] = false;
                }
            } else {
                $directory['real_parent_id'] = null;
                $directory['is_shared_root'] = false;
            }
        }

        if (isset($directoryData['directory'])) {
            $directory = &$directoryData['directory'];

            Logger::info("Processing directory data", [
                'directory_id' => $directory['id'],
                'directory_name' => $directory['name'],
                'parent_id' => $directory['parent_id'],
                'user_id' => $directory['user_id'],
                'current_user_id' => $userId
            ]);

            $sharedInfo = $this->directoryRepository->isDirectorySharedToUser($directory['id'], $userId);
            if ($sharedInfo) {
                $directory['is_shared'] = true;
                $directory['shared_by'] = $sharedInfo['shared_by'];
            } else {
                $directory['is_shared'] = false;
                $directory['shared_by'] = null;
            }

            $directory['is_shared_by_owner'] = $this->directoryRepository->isDirectorySharedByOwner($directory['id'], $userId);

            $directory['safe_parent_id'] = null;
            if ($directory['parent_id']) {
                Logger::info("Looking for accessible parent", [
                    'directory_id' => $directory['id'],
                    'parent_id' => $directory['parent_id']
                ]);

                $accessibleParent = $this->directoryRepository->findAccessibleParentDirectory($directory['id'], $userId);
                if ($accessibleParent) {
                    $directory['safe_parent_id'] = $accessibleParent['id'];
                    Logger::info("Found accessible parent", [
                        'safe_parent_id' => $directory['safe_parent_id']
                    ]);
                } else {

                    $directory['safe_parent_id'] = 'root';
                    Logger::info("No accessible parent found, setting to root");
                }
            } else {
                Logger::info("No parent_id, this is root directory");
            }

            Logger::info("Final directory data", [
                'id' => $directory['id'],
                'name' => $directory['name'],
                'parent_id' => $directory['parent_id'],
                'safe_parent_id' => $directory['safe_parent_id'],
                'is_shared' => $directory['is_shared'],
                'user_id' => $directory['user_id']
            ]);
        }

        if (isset($directoryData['subdirectories'])) {
            foreach ($directoryData['subdirectories'] as &$subdir) {

                if ($subdir['user_id'] == $userId) {

                    $subdir['is_shared_by_owner'] = $this->directoryRepository->isDirectorySharedByOwner($subdir['id'], $userId);
                    $subdir['is_shared'] = false;
                } else {

                    $subdir['is_shared'] = true;
                    $subdir['is_shared_by_owner'] = false;
                }
            }
            unset($subdir);
        }

        if (isset($directoryData['shared_directories'])) {
            foreach ($directoryData['shared_directories'] as &$sharedDir) {
                $sharedDir['is_shared'] = true;
                $sharedDir['is_shared_by_owner'] = false;
            }
            unset($sharedDir);
        }

        if (isset($directoryData['files'])) {
            foreach ($directoryData['files'] as &$file) {
                if (isset($file['file_size']) && is_numeric($file['file_size'])) {
                    $file['file_size_formatted'] = $this->formatFileSize((int)$file['file_size']);
                }
            }
            unset($file);
        }

        return $directoryData;
    }

    private function sendZipFile(string $zipFilePath, $directoryId): void
    {
        $safeFileName = $this->sanitizeFileName('directory_' . $directoryId) . '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $safeFileName . '"');
        header('Content-Length: ' . filesize($zipFilePath));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($zipFilePath);
        unlink($zipFilePath);
        exit;
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
}
