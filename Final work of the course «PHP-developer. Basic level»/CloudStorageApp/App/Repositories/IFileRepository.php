<?php

declare(strict_types=1);

namespace App\Repositories;

interface IFileRepository
{
    public function getRootDirectoryId(int $userId): int;
    public function checkDirectoryAccess(int $directoryId, int $userId): bool;
    public function getDirectoriesInDirectory(int $userId, ?int $directoryId): array;
    public function getSharedRootDirectoryIds(int $userId): array;
    public function getFilesInRootDirectory(int $userId, int $directoryId, array $sharedRootIds): array;
    public function getOwnFilesInDirectory(int $userId, int $directoryId): array;
    public function getSharedFilesInRootDirectories(int $userId): array;
    public function getFilesInDirectoryWithShared(int $userId, int $directoryId): array;
    public function getCurrentDirectory(?int $directoryId): ?array;
    public function ensureDirectoryExists(int $userId, string $dirId): int;
    public function checkDirectoryOwnership(int $userId, int $directoryId): bool;
    public function findExistingDirectory(string $name, int $parentId, int $userId): ?array;
    public function createDirectory(string $name, string $directoryName, int $parentId, int $userId): int;
    public function createFile(array $fileData): int;
    public function getFileForShare(int $fileId, int $userId): ?array;
    public function checkExistingShare(int $fileId, int $targetUserId): bool;
    public function createShare(int $fileId, int $ownerId, int $targetUserId): bool;
    public function getFileInfo(int $fileId, int $userId): ?array;
    public function getFileInformation(?int $fileId, int $userId): array;
    public function getFileForDownload(int $fileId): ?array;
    public function getFileWithAccess(int $fileId, int $userId): array;
    public function checkFileAccess(int $fileId, int $userId): bool;
    public function checkFileAccessForRename(int $fileId, int $userId): bool;
    public function renameFile(int $fileId, string $newName): bool;
    public function checkDirectoryAccessForMove(int $directoryId, int $userId): bool;
    public function moveFile(int $fileId, int $targetDirId): bool;
    public function deleteFile(int $fileId, int $userId): ?array;
    public function removeFile(int $fileId, int $userId): bool;
    public function checkSharedFileAccess(int $fileId, int $userId): bool;
    public function removeSharedAccess(int $fileId, int $userId): bool;
    public function getSharedFiles(int $userId): array;
    public function searchFiles(string $query, int $userId): array;
    public function isDirectorySharedToUser(int $directoryId, int $userId): ?array;
    public function debugSharedFiles(int $userId): void;
    public function bulkDeleteFiles(array $fileIds, int $userId): array;
    public function getFilesByDirectory(int $directoryId, int $userId): array;
    public function getSharedFilesByDirectory(int $directoryId, int $userId): array;
    public function getFileStats(int $userId): array;
    public function getRecentFiles(int $userId, int $limit = 10): array;
    public function getFilesByType(int $userId, string $mimeTypePattern): array;
    public function cleanupOrphanedFiles(): int;
    public function getFileSharesList(int $fileId): array;
    public function getFileById(int $fileId): ?array;
    public function getFilePath(string $storedName): string;
}
