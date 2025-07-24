<?php

namespace App\Repositories;

use App\Core\Repository;
use PDO;


class PasswordResetRepository extends Repository
{
    private UserRepository $userRepository;

    public function __construct()
    {
        parent::__construct();
        $this->userRepository = new UserRepository();
    }

    public function createResetToken(int $userId, string $token, int $expiresAt): bool
    {
        if (!$this->exists('users', ['id' => $userId])) {
            error_log("User with id $userId does not exist");
            return false;
        }

        $deleteResult = $this->deleteUserTokens($userId);

        $sql = "INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at) 
            VALUES (:user_id, :token, :expires_at, NOW())";

        $stmt = $this->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare statement in createResetToken");
            return false;
        }

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_INT);

        $result = $stmt->execute();

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("Failed to insert password reset token: " . json_encode($errorInfo));
            return false;
        } else {
            error_log("Password reset token inserted successfully for user_id=$userId, token=$token");
        }

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("Failed to insert password reset token: " . json_encode($errorInfo));
            return false;
        } else {
            error_log("Password reset token inserted successfully");
        }

        return true;
    }

    public function findValidToken(string $token): ?array
    {
        $sql = "SELECT pr.*, u.email, u.first_name, u.last_name 
            FROM password_reset_tokens pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = :token 
            AND pr.expires_at > :current_time 
            AND pr.used_at IS NULL";

        $stmt = $this->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare statement in findValidToken");
            return null;
        }

        $stmt->bindValue(':token', $token, PDO::PARAM_STR);

        $currentTime = time();
        $stmt->bindValue(':current_time', $currentTime, PDO::PARAM_INT);

        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        $user = $this->userRepository->findById($result['user_id']);
        if (!$user) {
            return null;
        }

        return $result;
    }

    public function markTokenAsUsed(string $token): bool
    {
        $sql = "UPDATE password_reset_tokens 
                SET used_at = NOW() 
                WHERE token = :token";

        $stmt = $this->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare statement in markTokenAsUsed");
            return false;
        }

        $stmt->bindParam(':token', $token, PDO::PARAM_STR);

        $result = $stmt->execute();

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("Failed to mark token as used: " . json_encode($errorInfo));
        } else {
            error_log("Token marked as used: $token");
        }

        return $result;
    }

    public function deleteUserTokens(int $userId): bool
    {
        $sql = "DELETE FROM password_reset_tokens WHERE user_id = :user_id";

        $stmt = $this->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare statement in deleteUserTokens");
            return false;
        }

        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        $result = $stmt->execute();

        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("Failed to delete password reset tokens: " . json_encode($errorInfo));
        } else {
            error_log("Deleted password reset tokens for user_id: $userId");
        }

        return $result;
    }

    public function cleanupExpiredTokens(): int
    {
        $sql = "DELETE FROM password_reset_tokens WHERE expires_at < :current_time";

        $stmt = $this->prepare($sql);

        $currentTime = date('Y-m-d H:i:s');
        $stmt->bindParam(':current_time', $currentTime, PDO::PARAM_STR);

        $stmt->execute();

        return $stmt->rowCount();
    }
}
