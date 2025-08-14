<?php

namespace App\Core;

use PDO;

abstract class Repository
{
    protected Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    protected function prepare(string $sql)
    {
        return $this->db->getConnection()->prepare($sql);
    }

    protected function query(string $sql)
    {
        return $this->db->query($sql);
    }

    protected function exec(string $sql)
    {
        return $this->db->exec($sql);
    }

    protected function lastInsertId()
    {
        return $this->db->lastInsertId();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (\Exception $e) {
            Logger::error("Repository fetchOne error", [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            Logger::error("Repository fetchAll error", [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db->getConnection()->prepare($sql);

        return $stmt->execute($params);
    }

    protected function insert(string $table, array $data): int
    {
        try {
            $conn = $this->db->getConnection();
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));

            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = $conn->prepare($sql);
            $stmt->execute($data);

            return (int)$conn->lastInsertId();
        } catch (\Exception $e) {
            Logger::error("Repository insert error", [
                'table' => $table,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function update(string $table, array $data, array $where): bool
    {
        try {
            $conn = $this->db->getConnection();

            $setClause = implode(', ', array_map(fn($key) => "{$key} = :{$key}", array_keys($data)));
            $whereClause = implode(' AND ', array_map(fn($key) => "{$key} = :where_{$key}", array_keys($where)));

            $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";

            $params = $data;
            foreach ($where as $key => $value) {
                $params["where_{$key}"] = $value;
            }

            $stmt = $conn->prepare($sql);

            return $stmt->execute($params);
        } catch (\Exception $e) {
            Logger::error("Repository update error", [
                'table' => $table,
                'data' => $data,
                'where' => $where,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function delete(string $table, array $where): bool
    {
        try {
            $conn = $this->db->getConnection();

            $whereClause = implode(' AND ', array_map(fn($key) => "{$key} = :{$key}", array_keys($where)));
            $sql = "DELETE FROM {$table} WHERE {$whereClause}";

            $stmt = $conn->prepare($sql);
            $executed = $stmt->execute($where);

            // Проверяем, была ли удалена хотя бы одна строка
            return $executed && $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            Logger::error("Repository delete error", [
                'table' => $table,
                'where' => $where,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function exists(string $table, array $where): bool
    {
        try {
            $conn = $this->db->getConnection();

            $whereClause = implode(' AND ', array_map(fn($key) => "{$key} = :{$key}", array_keys($where)));
            $sql = "SELECT 1 FROM {$table} WHERE {$whereClause} LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->execute($where);

            return $stmt->fetch() !== false;
        } catch (\Exception $e) {
            Logger::error("Repository exists error", [
                'table' => $table,
                'where' => $where,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function count(string $table, array $where = []): int
    {
        try {
            $conn = $this->db->getConnection();

            $sql = "SELECT COUNT(*) as count FROM {$table}";
            $params = [];

            if (! empty($where)) {
                $whereClause = implode(' AND ', array_map(fn($key) => "{$key} = :{$key}", array_keys($where)));
                $sql .= " WHERE {$whereClause}";
                $params = $where;
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)($result['count'] ?? 0);
        } catch (\Exception $e) {
            Logger::error("Repository count error", [
                'table' => $table,
                'where' => $where,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
