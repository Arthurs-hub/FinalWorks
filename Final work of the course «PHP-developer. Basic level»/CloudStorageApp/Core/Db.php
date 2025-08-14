<?php

namespace App\Core;

use PDO;
use PDOException;

class Db
{
    private static ?PDO $instance = null;
    private static array $config;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$config = require __DIR__ . '/../config/config.php';

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::$config['database']['host'],
                self::$config['database']['dbname'],
                self::$config['database']['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    self::$config['database']['username'],
                    self::$config['database']['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                throw new PDOException("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public function getConnection()
    {
        return self::getInstance();
    }


    public static function prepare(string $sql)
    {
        return self::getInstance()->prepare($sql);
    }

    public static function query(string $sql)
    {
        return self::getInstance()->query($sql);
    }

    public static function exec(string $sql)
    {
        return self::getInstance()->exec($sql);
    }

    public static function lastInsertId()
    {
        return self::getInstance()->lastInsertId();
    }
}
