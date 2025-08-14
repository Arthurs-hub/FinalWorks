<?php

namespace App\Validators;

class DirectoryValidator
{
    public function validateCreateDirectory(array $data): array
    {
        if (empty($data['name'])) {
            return ['valid' => false, 'message' => 'Имя папки обязательно'];
        }

        if (strlen($data['name']) > 255) {
            return ['valid' => false, 'message' => 'Имя папки слишком длинное'];
        }

        return ['valid' => true];
    }

    public function validateRenameDirectory(array $data): array
    {
        if (empty($data['id'])) {
            return ['valid' => false, 'message' => 'ID папки обязателен'];
        }

        if (empty($data['new_name'])) {
            return ['valid' => false, 'message' => 'Новое имя папки обязательно'];
        }

        return ['valid' => true];
    }
}
