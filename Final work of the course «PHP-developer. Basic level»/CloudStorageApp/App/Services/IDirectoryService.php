<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Response;

interface IDirectoryService
{
    public function addDirectory(array $data, int $userId): array;
    public function renameDirectory(array $data, int $userId): array;
    public function getDirectory(?string $directoryId, int $userId): array;
    public function moveDirectory(array $data, int $userId): array;
    public function downloadDirectory(?string $directoryId, int $userId): Response;
    public function deleteDirectory(?string $directoryId, int $userId, bool $isAdmin = false): array;
    public function shareDirectory(array $data, int $userId): array;
    public function unshareDirectory(array $data, int $userId): array;
    public function getSharedDirectoriesList(int $userId): array;
    public function getOrCreateSubdirectory(string $name, int $parentId, int $userId): array;
}
