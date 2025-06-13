<?php

namespace App\Core;

use PDO;
use PDOException;

class Db
{
    private $connection;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/config.php';

        try {
            $this->connection = new PDO(
                $config['db_dsn'],
                $config['db_user'],
                $config['db_pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
