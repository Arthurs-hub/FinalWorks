<?php

declare(strict_types=1);

namespace App\Services;

interface IFileService
{
    public function uploadFiles(int $userId, array $files, array $postData): array;
    public function getFileForDownload(int $fileId, int $userId): array;
    public function getFileForPreview(int $fileId, int $userId): array;
    public function downloadFile(int $fileId, int $userId): array;
    public function previewFile(int $fileId, int $userId): array;
    public function deleteFile(?int $fileId, int $userId): array;
    public function listFiles($directoryId, int $userId): array;
    public function renameFile(?int $fileId, array $data, int $userId): array;
    public function moveFile(?int $fileId, array $data, int $userId): array;
    public function shareFile(?int $fileId, array $data, int $userId): array;
    public function unshareFile(?int $fileId, int $targetUserId): array;
    public function getSharedFilesList(int $userId): array;
    public function getFileInformation(?int $fileId, int $userId): array;
    public function bulkDeleteFiles(array $data, int $userId): array;
    public function searchUserFiles(string $query, int $userId): array;
    public function getFileShares(int $fileId, int $userId): array;
}
