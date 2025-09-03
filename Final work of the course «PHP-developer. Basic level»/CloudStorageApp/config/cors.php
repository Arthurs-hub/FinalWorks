<?php

/**
 * Этот файл обрабатывает предварительные запросы CORS (Cross-Origin Resource Sharing).
 * Он позволяет вашему фронтенду (например, http://localhost) взаимодействовать с API (например, http://localhost:8080).
 */

// Список разрешенных источников. Ваш фронтенд, скорее всего, работает на http://localhost через XAMPP.
$allowed_origins = [
    'http://localhost',
    'http://127.0.0.1',
    // Если вы используете другой адрес для фронтенда, добавьте его сюда.
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
}

// Указываем, какие HTTP-методы разрешены.
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Указываем, какие HTTP-заголовки могут использоваться во время фактического запроса.
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control, Pragma, Expires");

// Разрешаем отправку cookie и других учетных данных с запросом.
header("Access-Control-Allow-Credentials: true");

// Обрабатываем предварительный запрос 'OPTIONS' от браузера.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}
