<?php

namespace App\Repositories;

interface IUserRepository
{
    public function findAll(): array;
    public function find(int $id): ?array;
    public function findByEmail(string $email): ?array;
    public function create(array $data): int;
    public function updateUser(int $id, array $data): bool;
    public function deleteUser(int $id): bool;
    public function countAdmins(): int;
    public function createRootDirectory(int $userId): bool;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
    public function inTransaction(): bool;
}
