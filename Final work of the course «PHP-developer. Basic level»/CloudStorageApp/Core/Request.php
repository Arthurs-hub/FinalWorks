<?php

namespace App\Core;

class Request
{
    private array $data;
    public array $routeParams = [];

    public function __construct()
    {
        $this->data = $this->parseRequestData();
    }

    private function parseRequestData(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        switch ($method) {
            case 'GET':
                return $_GET;
            case 'POST':
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                if (strpos($contentType, 'application/json') !== false) {
                    $input = file_get_contents('php://input');
                    $decoded = json_decode($input, true);
                    return $decoded ?? [];
                }
                return $_POST;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                if (strpos($contentType, 'application/json') !== false) {
                    $input = file_get_contents('php://input');
                    $decoded = json_decode($input, true);
                    return $decoded ?? [];
                }

                parse_str(file_get_contents('php://input'), $data);
                return $data;
            default:
                return [];
        }
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function getUri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '';
    }

    public function getQueryParam(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public function getHeader(string $name): ?string
    {
        $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$headerName] ?? null;
    }

    public function isJson(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return strpos($contentType, 'application/json') !== false;
    }

    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    public function getClientIp(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];

                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }

        return 'unknown';
    }

    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    public function getRoute(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if (strpos($path, $scriptName) === 0) {
            $path = substr($path, strlen($scriptName));
            if ($path === '') {
                $path = '/';
            }
        }

        return $path;
    }
}
