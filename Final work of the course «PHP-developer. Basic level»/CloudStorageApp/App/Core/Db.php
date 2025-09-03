<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Db
{
    private PDO $connection;

    public function __construct(array $config)
    {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $this->connection = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            // В реальном приложении здесь должно быть логирование и общее сообщение об ошибке
            throw new PDOException($e->getMessage(), (int) $e->getCode());
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
