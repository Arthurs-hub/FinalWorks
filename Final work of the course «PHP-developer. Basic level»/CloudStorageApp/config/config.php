<?php

return [
    'database' => [
        'host' => 'localhost',
        'dbname' => 'cloud_storage',
        'username' => 'root',
        'password' => 'mysqlpas123',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'name' => 'CloudStorageApp',
        'url' => 'http://localhost:8080',
        'upload_path' => __DIR__ . '/../uploads/',
        'max_file_size' => 104857600,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'],
        'timezone' => 'Europe/Moscow'
    ],
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'arthurznc@gmail.com',
        'password' => 'wltm aqpm kbtn broa',
        'from_email' => 'arthurznc@gmail.com',
        'from_name' => 'CloudStorageApp Support',
        'debug' => false
    ],
    'security' => [
        'session_lifetime' => 3600,
        'password_min_length' => 6,
        'max_login_attempts' => 5
    ]
];

