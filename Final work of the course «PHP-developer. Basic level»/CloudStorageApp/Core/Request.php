<?php

namespace App\Core;

class Request
{
    public array $routeParams = [];
    private array $data;
    private string $route;
    private string $method;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (in_array($this->method, ['POST', 'PUT'])) {
            $input = file_get_contents('php://input');
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->data = $decoded;
            } else {
                $this->data = $_POST;
            }
        } else {
            $this->data = $_GET;
        }
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
