<?php

namespace App\Core;

class Validator
{
    public static function required($value, string $fieldName): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException("{$fieldName} обязательно для заполнения");
        }
    }

    public static function email(string $email): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Некорректный email");
        }
    }

    public static function maxLength(string $value, int $max, string $fieldName): void
    {
        if (strlen($value) > $max) {
            throw new \InvalidArgumentException("{$fieldName} слишком длинное (максимум {$max} символов)");
        }
    }

    public static function noSpecialChars(string $value, string $fieldName): void
    {
        if (preg_match('/[\/\\\:\*\?"<>\|]/', $value)) {
            throw new \InvalidArgumentException("{$fieldName} содержит недопустимые символы");
        }
    }
}
