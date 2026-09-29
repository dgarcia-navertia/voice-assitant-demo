<?php

namespace App;

use PDO;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', 'db'),
                Env::get('DB_DATABASE', 'navertia')
            );
            self::$connection = new PDO(
                $dsn,
                Env::get('DB_USER', 'navertia'),
                Env::get('DB_PASSWORD', 'password'),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }
        return self::$connection;
    }
}
