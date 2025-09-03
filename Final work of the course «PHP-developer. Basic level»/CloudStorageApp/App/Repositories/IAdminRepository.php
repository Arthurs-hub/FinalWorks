<?php

declare(strict_types=1);

namespace App\Repositories;

interface IAdminRepository
{
    public function fetchAdminStats(): array;
    public function cleanupOrphanedFiles(): array;
    public function fetchUsers(): array;
    public function fetchUserById(int $userId): array;
    public function fetchCurrentUser(): array;
    public function fetchSystemStats(): array;
    public function fetchTopUsers(int $limit): array;
    public function fetchRecentActivity(int $limit): array;
    public function fetchFileTypeStats(): array;
    public function cleanupOldSessions(): array;
    public function optimizeDatabase(): array;
    public function updateUserData(int $userId, array $data): array;
    public function deleteFile(int $fileId): bool;
    public function deleteAllFiles(): int;
    public function fetchAllFiles(): array;
    public function createUserWithValidation(array $data): array;
}
