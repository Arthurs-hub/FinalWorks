<?php

declare(strict_types=1);

namespace App\Services;

interface IUserService
{
    // Core user retrieval and validation
    public function validateUserData(array $data, bool $isUpdate = false): array;
    public function findUserByEmail(string $email): ?array;
    public function findUserById(int $userId): ?array;
    public function findByEmail(string $email): ?array;

    // CRUD
    public function createUser(array $data): int;
    public function createUserWithRootDirectory(array $userData): int;
    public function updateUser(int $userId, array $data): bool;
    public function deleteUser(int $userId): bool;
    public function getAllUsers(): array;
    public function getUserById(int $id): ?array;

    // Auth
    public function authenticateUser(string $email, string $password): ?array;
    public function login(array $data): array;
    public function register(array $data): array;
    public function logout(): array;
    public function getCurrentUser(?int $userId): array;

    // Passwords
    public function resetPassword(string $email): string;
    public function updatePassword(int $userId, string $hashedPassword): bool;
    public function changePassword(int $userId, string $currentPassword, string $newPassword): void;
    public function resetPasswordWithToken(string $token, string $newPassword, string $passwordConfirmation): array;
    public function validateResetToken(string $token): array;

    // Stats and admin
    public function getUserStats(int $userId): array;
    public function getUserStatsWithAuth(?int $userId, ?int $currentUserId): array;
    public function isAdmin(?int $userId): bool;
    public function promoteToAdmin(int $userId): bool;
    public function demoteFromAdmin(int $userId): bool;

    // Misc domain operations used by controllers
    public function list(): array;
    public function get(?int $userId): array;
    public function update(?int $userId, array $data, ?int $currentUserId): array;
    public function changeUserPassword(?int $userId, array $data): array;
    public function delete(?int $userId, ?int $currentUserId): array;

    // Public password reset flow
    public function publicPasswordReset(array $data): array;
    public function requestPasswordReset(array $data): array;

    // Admin helper
    public function createFirstAdmin(array $data): array;

    // Two-factor authentication
    public function validateTwoFactorCode(string $code): bool;

    // Admin actions
    public function getSystemHealth(): array;
    public function banUser(int $userId): bool;
    public function unbanUser(int $userId): bool;
    public function makeAdmin(int $userId): bool;
    public function removeAdmin(int $userId): bool;
    public function getSecurityReport(): array;
    public function exportUserData(int $userId): array;
    public function bulkDeleteUsers(array $userIds): array;
    public function searchUsers(string $query): array;
    public function getUserActivity(int $userId, int $days): array;
    public function resetPasswordByAdmin(int $targetUserId): string;
    public function getActiveUsers(int $days): array;
}
