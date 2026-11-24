<?php

namespace VanillIA\Core;

use PDO;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    public static function reconnect(): PDO
    {
        if (self::$instance !== null) {
            try {
                self::$instance->query('SELECT 1');
                return self::$instance;
            } catch (\Throwable $e) {
                self::$instance = null;
            }
        }
        return self::connect();
    }

    private static function connect(): PDO
    {
        $host    = Env::get('DB_HOST', '127.0.0.1');
        $port    = Env::get('DB_PORT', '3306');
        $dbName  = Env::get('DB_NAME', 'vanillia_db');
        $user    = Env::get('DB_USER', 'root');
        $pass    = Env::get('DB_PASS', '');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $dbName . ";charset=" . $charset;
        self::$instance = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$instance;
    }
}
