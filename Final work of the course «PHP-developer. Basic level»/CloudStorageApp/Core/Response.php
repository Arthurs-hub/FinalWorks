<?php

namespace App\Core;

class Response
{
    private $data;
    private $status;
    private $headers = [];

    public function __construct($data, $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }

    public function send()
    {
        http_response_code($this->status);
        header('Content-Type: application/json');
        error_log(json_encode($this->data));
        echo json_encode($this->data);
    }

    public function getData()
    {
        return $this->data;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function setStatusCode(int $status): self
    {
        $this->status = $status;
        return $this;
    }


    public function setData($data): self
    {
        $this->data = $data;
        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function isServerError(): bool
    {
        return $this->status >= 500 && $this->status < 600;
    }

    public function addHeader(string $name, string $value): self
    {

        if (!isset($this->headers)) {
            $this->headers = [];
        }
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers ?? [];
    }
}
