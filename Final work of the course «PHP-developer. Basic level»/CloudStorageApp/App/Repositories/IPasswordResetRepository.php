<?php

namespace App\Repositories;

interface IPasswordResetRepository
{
    public function createToken(string $email): ?string;
    public function findUserByToken(string $token): ?array;
    public function deleteToken(string $token): bool;
}
