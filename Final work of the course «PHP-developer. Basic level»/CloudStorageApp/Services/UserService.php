<?php

namespace App\Services;

use App\Core\Db;
use PDO;
use Exception;

class UserService
{
    private $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function getAllUsers(): array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT id, email, first_name, last_name, middle_name, role, age, gender, created_at 
                FROM users 
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("UserService::getAllUsers exception: " . $e->getMessage());
            throw $e;
        }
    }

    public function getUserById($id): ?array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT id, email, first_name, last_name, middle_name, role, age, gender, created_at 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (Exception $e) {
            error_log("UserService::getUserById exception: " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteUser($id): bool
    {
        try {
            error_log("UserService::deleteUser called for ID: $id");

            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$id]);

            error_log("UserService::deleteUser result: " . ($result ? 'success' : 'failed'));
            return $result;
        } catch (Exception $e) {
            error_log("UserService::deleteUser exception: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateUser(int $id, array $data): bool
    {
        try {
            error_log("UserService::updateUser called for ID: $id with data: " . json_encode($data));

            $conn = $this->db->getConnection();

            $fields = [];
            $values = [];

            foreach ($data as $key => $value) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }

            $values[] = $id;

            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            error_log("UserService::updateUser SQL: " . $sql);

            $stmt = $conn->prepare($sql);
            $result = $stmt->execute($values);

            error_log("UserService::updateUser result: " . ($result ? 'success' : 'failed'));
            return $result;
        } catch (Exception $e) {
            error_log("UserService::updateUser exception: " . $e->getMessage());
            throw $e;
        }
    }

    public function createUser(array $data): int
    {
        try {
            error_log("UserService::createUser called with data: " . json_encode($data));

            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("
                INSERT INTO users (email, first_name, last_name, middle_name, password, role, age, gender) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $data['email'],
                $data['first_name'],
                $data['last_name'],
                $data['middle_name'] ?? null,
                $data['password'],
                $data['role'] ?? 'user',
                $data['age'] ?? null,
                $data['gender'] ?? null
            ]);

            if ($result) {
                $userId = (int)$conn->lastInsertId();
                return $userId;
            } else {
                return 0;
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function findUserByEmail(string $email): ?array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT id, email, first_name, last_name, middle_name, role, password, age, gender, created_at 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (Exception $e) {
            error_log("UserService::findUserByEmail exception: " . $e->getMessage());
            throw $e;
        }
    }
}
