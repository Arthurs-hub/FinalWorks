<?php

declare(strict_types=1);

namespace App\Repositories;

interface IUserRepository
{
    public function find(int $id): ?array;
    public function findById(int $id): ?array;
    public function findUserByEmail(string $email): ?array;
    public function findByEmail(string $email): ?array;
    public function findByEmailWithPassword(string $email): ?array;
    public function create(array $data): int;
    public function createUserWithRootDirectory(array $userData): int;
    public function updateUser(int $userId, array $data): bool;
    public function deleteUser(int $userId): bool;
    public function getAllUsers(): array;
    public function searchUsers(string $query): array;
    public function userExists(int $userId): bool;
    public function isAdmin(int $userId): bool;
    public function makeAdmin(int $userId): bool;
    public function removeAdmin(int $userId): bool;
    public function updateLastLogin(int $userId): bool;
    public function updatePassword(int $userId, string $hashedPassword): bool;
    public function countUsers(): int;
    public function countAdmins(): int;
    public function getActiveUsers(int $days): array;
    public function banUser(int $userId): bool;
    public function unbanUser(int $userId): bool;
    public function isUserBanned(int $userId): bool;
    public function getUserActivity(int $userId, int $days): array;
    public function getInactiveUsers(int $days): array;
    public function deleteExpiredPasswordResetTokens(): int;
    public function promoteToAdmin(int $userId): bool;
    public function getUserFiles(int $userId): array;
    public function getUserDirectories(int $userId): array;
    public function getUserSharedFiles(int $userId): array;
    public function getUserReceivedShares(int $userId): array;
    public function getUserStats(int $userId): array;
    public function getAllUsersWithStats(): array;
    public function countWeakPasswords(): int;
    public function getInactiveAdmins(int $days): array;
    public function getUserById(int $id): ?array;
    public function bulkDeleteUsers(array $userIds): array;
    public function exportUsersToCSV(): array;
}