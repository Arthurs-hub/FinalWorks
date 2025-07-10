<?php

namespace App\Core;

use PDO;
use PDOException;

class Db
{
    private ?PDO $connection = null;
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/config.php';
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            try {
                $this->connection = new PDO(
                    $this->config['db_dsn'],
                    $this->config['db_user'],
                    $this->config['db_pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                    ]
                );
            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());

                throw new \RuntimeException("Ошибка подключения к базе данных");
            }
        }

        return $this->connection;
    }

    public function closeConnection(): void
    {
        $this->connection = null;
    }
}
