<?php

namespace App\Repositories;

use App\Core\Db;
use DateTime;
use DateInterval;
use PDO;

class PasswordResetRepository implements IPasswordResetRepository
{
    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

   public function createToken(string $email): ?string
{
    $userId = $this->db->getConnection()->prepare("SELECT id FROM users WHERE email = :email");
    $userId->execute(['email' => $email]);
    $userId = $userId->fetchColumn();

    if (!$userId) {
        
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = new DateTime();
    $expiresAt->add(new DateInterval('PT1H')); 

    $stmt = $this->db->getConnection()->prepare("DELETE FROM password_reset_tokens WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);

    $stmt = $this->db->getConnection()->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
    $stmt->execute([
        'user_id' => $userId,
        'token' => $token,
        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
    ]);

    return $token;
}

    public function findUserByToken(string $token): ?array
    {
        $stmt = $this->db->getConnection()->prepare("SELECT u.*, prt.expires_at FROM password_reset_tokens prt JOIN users u ON prt.user_id = u.id WHERE prt.token = :token AND prt.expires_at > NOW()");
        $stmt->execute(['token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function deleteToken(string $token): bool
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = :token");
        return $stmt->execute(['token' => $token]);
    }

    public function cleanupExpiredTokens(): int
    {
        $stmt = $this->db->getConnection()->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}