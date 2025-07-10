<?php

namespace App\Repositories;

use App\Core\Repository;
use PDO;

class UserRepository extends Repository
{
    public function fetchOne(string $sql, array $params = []): ?array
    {
        return parent::fetchOne($sql, $params);
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return parent::fetchAll($sql, $params);
    }

    public function findUserByEmail(string $email): ?array
    {
        return $this->fetchOne("
            SELECT id, email, first_name, last_name, role, is_admin, created_at, last_login
            FROM users 
            WHERE email = ?
        ", [$email]);
    }

    public function findByEmailWithPassword(string $email): ?array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT id, email, first_name, last_name, password, is_admin, is_banned, created_at, last_login
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findById(int $userId): ?array
    {
        return $this->fetchOne("
            SELECT id, email, first_name, last_name, role, age, gender, is_admin, created_at, last_login
            FROM users 
            WHERE id = ?
        ", [$userId]);
    }

    public function getUserById(int $userId): ?array
    {
        return $this->findById($userId);
    }

    public function getUserStats(int $userId): array
    {
        $stats = $this->fetchOne("
            SELECT 
                COUNT(f.id) as files_count,
                COALESCE(SUM(f.size), 0) as total_size,
                COUNT(DISTINCT d.id) as directories_count,
                COUNT(DISTINCT si_by.id) as shares_given,
                COUNT(DISTINCT si_to.id) as shares_received
            FROM users u
            LEFT JOIN files f ON u.id = f.user_id
            LEFT JOIN directories d ON u.id = d.user_id
            LEFT JOIN shared_items si_by ON u.id = si_by.shared_by_user_id
            LEFT JOIN shared_items si_to ON u.id = si_to.shared_with_user_id
            WHERE u.id = ?
        ", [$userId]);

        return $stats ?: [
            'files_count' => 0,
            'total_size' => 0,
            'directories_count' => 0,
            'shares_given' => 0,
            'shares_received' => 0,
        ];
    }

    public function create(array $data): int
    {
        return $this->insert('users', $data);
    }

    public function updateUser(int $userId, array $data): bool
    {
        if (isset($data['password']) && ! empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }

        return $this->update('users', $data, ['id' => $userId]);
    }

    public function deleteUser(int $userId): bool
    {
        return $this->delete('users', ['id' => $userId]);
    }

    public function bulkDeleteUsers(array $userIds): array
    {
        $results = [
            'total' => count($userIds),
            'success' => [],
            'failed' => [],
        ];

        foreach ($userIds as $userId) {
            try {

                $deleted = $this->deleteUser($userId);
                if ($deleted) {
                    $results['success'][] = $userId;
                } else {
                    $results['failed'][] = $userId;
                }
            } catch (\Exception $e) {

                $results['failed'][] = $userId;
            }
        }

        return $results;
    }

    public function getAllUsers(): array
    {
        return $this->fetchAll("
            SELECT id, email, first_name, last_name,
                   CONCAT(first_name, ' ', last_name) as name,
                   is_admin, created_at, last_login
            FROM users 
            ORDER BY created_at DESC
        ");
    }

    public function searchUsers(string $query): array
    {
        try {
            $conn = $this->db->getConnection();
            $searchTerm = '%' . $query . '%';
            $stmt = $conn->prepare("
                SELECT id, email, first_name, last_name, is_admin, is_banned, created_at, last_login
                FROM users 
                WHERE email LIKE ? 
                   OR first_name LIKE ? 
                   OR last_name LIKE ?
                   OR CONCAT(first_name, ' ', last_name) LIKE ?
                ORDER BY 
                    CASE 
                        WHEN email = ? THEN 1
                        WHEN email LIKE ? THEN 2
                        WHEN CONCAT(first_name, ' ', last_name) LIKE ? THEN 3
                        ELSE 4
                    END,
                    created_at DESC
                LIMIT 50
            ");
            $stmt->execute([
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $query,
                $query . '%',
                $searchTerm,
            ]);

            return $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function isAdmin(int $userId): bool
    {
        $user = $this->fetchOne("
            SELECT is_admin, role 
            FROM users 
            WHERE id = ?
        ", [$userId]);

        if (! $user) {
            return false;
        }

        return $user['is_admin'] == 1 || strtolower($user['role']) === 'admin';
    }

    public function makeAdmin(int $userId): bool
    {
        return $this->update('users', ['is_admin' => 1], ['id' => $userId]);
    }

    public function removeAdmin(int $userId): bool
    {
        return $this->update('users', ['is_admin' => 0], ['id' => $userId]);
    }

    public function updateLastLogin(int $userId): bool
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");

            return $stmt->execute([$userId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne("
            SELECT id, email, first_name, last_name, role, is_admin, created_at
            FROM users 
            WHERE email = ?
        ", [$email]);
    }

    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

            return $stmt->execute([$hashedPassword, $userId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function userExists(int $userId): bool
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT 1 FROM users WHERE id = ?");
            $stmt->execute([$userId]);

            return $stmt->fetch() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function exportUsersToCSV(): array
    {
        $filePath = tempnam(sys_get_temp_dir(), 'users_export_') . '.csv';
        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';

        $file = fopen($filePath, 'w');

        fputcsv($file, ['ID', 'Email', 'First Name', 'Last Name', 'Role', 'Status']);

        $users = $this->getAllUsers();

        foreach ($users as $user) {
            fputcsv($file, [
                $user['id'],
                $user['email'],
                $user['first_name'],
                $user['last_name'],
                $user['is_admin'] ? 'Admin' : 'User',
                $user['is_banned'] ? 'Banned' : 'Active',
            ]);
        }

        fclose($file);

        return [
            'file_path' => $filePath,
            'filename' => $filename,
            'content_type' => 'text/csv; charset=utf-8',
            'success' => true,
        ];
    }

    public function getUsersWithPagination(int $offset, int $limit): array
    {
        try {
            $conn = $this->db->getConnection();

            $stmt = $conn->prepare("
                SELECT 
                    u.id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.is_admin,
                    u.is_banned,
                    u.created_at,
                    u.last_login,
                    COUNT(DISTINCT f.id) as files_count,
                    COALESCE(SUM(f.size), 0) as total_size
                FROM users u
                LEFT JOIN files f ON u.id = f.user_id
                GROUP BY u.id, u.email, u.first_name, u.last_name, u.is_admin, u.is_banned, u.created_at, u.last_login
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $users = $stmt->fetchAll() ?: [];

            $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
            $countStmt->execute();
            $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = (int)($totalResult['total'] ?? 0);

            return [
                'users' => $users,
                'total' => $total,
                'offset' => $offset,
                'limit' => $limit,
            ];
        } catch (\Exception $e) {
            return [
                'users' => [],
                'total' => 0,
                'offset' => $offset,
                'limit' => $limit,
            ];
        }
    }

    public function getActiveUsers(int $days): array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT id, email, first_name, last_name, last_login
                FROM users 
                WHERE last_login >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY last_login DESC
            ");
            $stmt->execute([$days]);

            return $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function countUsers(): int
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)($result['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function countAdmins(): int
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)($result['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function countWeakPasswords(): int
    {

        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM users 
                WHERE LENGTH(password) < 60 OR password IS NULL
            ");
            $stmt->execute();
            $result = $stmt->fetch();

            return (int)($result['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getInactiveAdmins(int $days): array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT id, email, first_name, last_name, last_login
                FROM users 
                WHERE is_admin = 1 
                AND (last_login IS NULL OR last_login < DATE_SUB(NOW(), INTERVAL ? DAY))
            ");
            $stmt->execute([$days]);

            return $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function savePasswordResetToken(int $userId, string $token, string $expiresAt): bool
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                UPDATE users 
                SET reset_token = ?, reset_token_expires = ? 
                WHERE id = ?
            ");

            return $stmt->execute([$token, $expiresAt, $userId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function findByPasswordResetToken(string $token): ?array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT id, email, reset_token_expires 
                FROM users 
                WHERE reset_token = ?
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function updatePasswordAndClearToken(int $userId, string $hashedPassword): bool
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                UPDATE users 
                SET password = ?, reset_token = NULL, reset_token_expires = NULL 
                WHERE id = ?
            ");

            return $stmt->execute([$hashedPassword, $userId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function deleteExpiredPasswordResetTokens(): int
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                UPDATE users 
                SET reset_token = NULL, reset_token_expires = NULL 
                WHERE reset_token_expires < NOW()
            ");
            $stmt->execute();

            return $stmt->rowCount();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function banUser(int $userId): bool
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");

            return $stmt->execute([$userId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function unbanUser(int $userId): bool
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
        $stmt->execute([$userId]);

        return $stmt->rowCount() > 0;
    }

    public function isUserBanned(int $userId): bool
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT is_banned FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (bool)($result['is_banned'] ?? false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUserActivity(int $userId, int $days): array
    {

        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT 
                    'file_upload' as activity_type,
                    created_at as activity_date,
                    filename as details
                FROM files 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                
                UNION ALL
                
                SELECT 
                    'directory_created' as activity_type,
                    created_at as activity_date,
                    name as details
                FROM directories 
                WHERE user_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                
                ORDER BY activity_date DESC
                LIMIT 100
            ");
            $stmt->execute([$userId, $days, $userId, $days]);

            return $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getInactiveUsers(int $days): array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT id, email, first_name, last_name, last_login, created_at
                FROM users 
                WHERE is_admin = 0 
                AND (
                    last_login IS NULL 
                    OR last_login < DATE_SUB(NOW(), INTERVAL ? DAY)
                )
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days, $days]);

            return $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAllUsersWithStats(): array
    {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT 
                    u.id,
                    u.email,
                    u.first_name,
                    u.last_name,
                    u.middle_name,
                    u.gender,
                    u.age,
                    u.is_admin,
                    u.is_banned,
                    u.created_at,
                    u.last_login,
                    CASE WHEN u.is_admin = 1 THEN 'admin' ELSE 'user' END AS role,
                    COUNT(DISTINCT f.id) as files_count,
                    COUNT(DISTINCT d.id) as directories_count,
                    COALESCE(SUM(f.size), 0) as total_size,
                    COUNT(DISTINCT si_shared.id) as shared_files_count,
                    COUNT(DISTINCT si_received.id) as received_shares_count
                FROM users u
                LEFT JOIN files f ON u.id = f.user_id
                LEFT JOIN directories d ON u.id = d.user_id
                LEFT JOIN shared_items si_shared ON u.id = si_shared.shared_by_user_id
                LEFT JOIN shared_items si_received ON u.id = si_received.shared_with_user_id
                GROUP BY u.id, u.email, u.first_name, u.last_name, u.middle_name, u.gender, u.age, u.is_admin, u.is_banned, u.created_at, u.last_login
                ORDER BY u.created_at DESC
            ");
            $stmt->execute();

            return $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getUsersByRole(string $role): array
    {
        try {
            $conn = $this->db->getConnection();
            if ($role === 'admin') {
                $stmt = $conn->prepare("
                    SELECT id, email, first_name, last_name, created_at, last_login
                    FROM users 
                    WHERE is_admin = 1
                    ORDER BY created_at DESC
                ");
            } else {
                $stmt = $conn->prepare("
                    SELECT id, email, first_name, last_name, created_at, last_login
                    FROM users 
                    WHERE is_admin = 0
                    ORDER BY created_at DESC
                ");
            }
            $stmt->execute();

            return $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAllFiles(): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM files ORDER BY created_at DESC");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
