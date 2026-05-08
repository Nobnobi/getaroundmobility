<?php
namespace App\Utils;

use PDO;
class Database
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            // Load .env if not already loaded
            if (!isset($_ENV['DB_HOST'])) {
                $dotenvPath = dirname(__DIR__, 2) . '/.env';
                if (file_exists($dotenvPath)) {
                    $dotenv = \Dotenv\Dotenv::createImmutable(dirname($dotenvPath));
                    $dotenv->load();
                }
            }
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $db   = $_ENV['DB_NAME'] ?? '';
            $user = $_ENV['DB_USER'] ?? '';
            $pass = $_ENV['DB_PASS'] ?? '';

            self::$instance = new PDO(
                "mysql:host=$host;dbname=$db;charset=utf8",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        }
        return self::$instance;
    }
}