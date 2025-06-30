<?php

namespace App\Validators;

class AuthValidator
{
    public function validateLoginData(array $data): array
    {
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return [
                'valid' => false,
                'message' => 'Email и пароль обязательны'
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => 'Некорректный формат email'
            ];
        }

        if (strlen($password) < 6) {
            return [
                'valid' => false,
                'message' => 'Пароль должен содержать минимум 6 символов'
            ];
        }

        return ['valid' => true];
    }
}
