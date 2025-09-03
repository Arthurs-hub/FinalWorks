<?php

declare(strict_types=1);

namespace App\Repositories;

interface IDirectoryRepository
{
    public function resolveParentId($requestedParentId, int $userId);
    public function findDirectoryByNameAndParent(string $name, ?int $parentId, int $userId): ?array;
    public function createDirectory(string $name, ?int $parentId, int $userId): ?int;
    public function checkDirectoryAccess(int $id, int $userId): bool;
    public function renameDirectory(int $id, string $newName, int $userId);
    public function getDirectoryWithContents($directoryId, int $userId): ?array;
    public function moveDirectory(int $directoryId, ?int $targetParentId): bool;
    public function getDirectoryByIdPublic(int $id, int $userId, bool $isAdmin): ?array;
    public function checkDirectoryOwnership(int $id, int $userId): bool;
    public function deleteDirectory(int $id, int $userId, bool $isAdmin): bool;
    public function shareDirectory(int $folderId, int $userId, string $targetEmail, IUserRepository $userRepository): array;
    public function unshareDirectoryRecursively(int $directoryId, int $userId): void;
    public function createZipArchiveForDirectory(?int $directoryId, int $userId): string;
    public function getSharedDirectories(int $userId): array;
    public function getOrCreateRootDirectoryId(int $userId): int;
    public function isDirectorySharedToUser(int $directoryId, int $userId): ?array;
    public function isDirectorySharedByOwner(int $directoryId, int $userId): bool;
    public function findAccessibleParentDirectory(int $directoryId, int $userId): ?array;
    public function createRootDirectory(int $userId): int;
}
