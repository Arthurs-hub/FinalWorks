<?php

namespace App\Core;

class Logger
{
    private static ?Logger $instance = null;

    private function __construct()
    {
        // Приватный конструктор для реализации синглтона
    }

    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function info(string $message, array $context = []): void
    {
        $logMessage = "[INFO] " . date("Y-m-d H:i:s") . " - " . $message;
        if (!empty($context)) {
            $logMessage .= " Context: " . json_encode($context);
        }

        file_put_contents("php://stderr", $logMessage . PHP_EOL, FILE_APPEND);
    }

    public static function error(string $message, array $context = []): void
    {
        $logMessage = "[ERROR] " . date("Y-m-d H:i:s") . " - " . $message;
        if (!empty($context)) {
            $logMessage .= " Context: " . json_encode($context);
        }
        file_put_contents("php://stderr", $logMessage . PHP_EOL, FILE_APPEND);
    }

    public static function accessLog(string $method, string $uri, int $responseCode, float $executionTime): void
    {
        $logMessage = "[ACCESS] " . date("Y-m-d H:i:s") . " - Method: {$method}, URI: {$uri}, Response Code: {$responseCode}, Execution Time: " . round($executionTime, 4) . "s";
        file_put_contents("php://stderr", $logMessage . PHP_EOL, FILE_APPEND);
    }
}
