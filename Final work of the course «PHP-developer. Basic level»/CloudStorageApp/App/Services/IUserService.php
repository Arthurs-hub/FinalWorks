<?php

namespace App\Services;

interface IUserService
{
    public function validateUserData(array $data): array;
    public function createUser(array $data): int;
    public function getAllUsers(): array;
    public function getUserById(int $id): ?array;
    public function authenticateUser(string $email, string $password): ?array;
    public function resetPassword(string $email): string;
    public function getUserStats(int $userId): array;
    public function demoteFromAdmin(int $userId): bool;
}
