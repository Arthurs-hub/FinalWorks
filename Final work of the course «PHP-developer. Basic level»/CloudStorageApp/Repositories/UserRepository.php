<?php

namespace App\Repositories;

use App\Core\Db;
use PDO;

class UserRepository
{
    private $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function findAll(): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT id, email, first_name, last_name, role, age, gender, created_at 
            FROM users 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($id): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT id, email, first_name, last_name, role, age, gender, created_at 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT id, email, first_name, last_name, role, password, age, gender, created_at 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public function create(array $data): int
    {
        $conn = $this->db->getConnection();

        $stmt = $conn->prepare("
            INSERT INTO users (email, first_name, last_name, password, role, age, gender, middle_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['email'],
            $data['first_name'],
            $data['last_name'],
            $data['password'],
            $data['role'] ?? 'user',
            $data['age'] ?? null,
            $data['gender'] ?? null,
            $data['middle_name'] ?? null
        ]);

        return $result ? (int)$conn->lastInsertId() : 0;
    }

    public function update(int $id, array $data): bool
    {
        $conn = $this->db->getConnection();

        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }

        $values[] = $id;

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);

        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
