<?php

namespace App\Validators;

class AuthValidator
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/config.php';
    }

    public function validateLoginData(array $data): array
    {
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return [
                'valid' => false,
                'message' => 'Email и пароль обязательны',
            ];
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Некорректный формат email',
            ];
        }

        $minLength = $this->config['security']['password_min_length'];
        if (strlen($password) < $minLength) {
            return [
                'valid' => false,
                'message' => "Пароль должен содержать минимум {$minLength} символов",
            ];
        }

        return ['valid' => true];
    }
}
