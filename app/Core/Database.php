<?php

declare(strict_types=1);

namespace ManhwaPortal\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = app_config();
        $db = $config['database'] ?? [];
        $driver = (string) ($db['driver'] ?? 'mysql');

        if ($driver === 'sqlite') {
            $path = (string) ($db['path'] ?? STORAGE_PATH . '/local.sqlite');
            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            return self::$pdo;
        }

        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (string) ($db['port'] ?? '3306');
        $name = (string) ($db['name'] ?? '');
        $user = (string) ($db['user'] ?? '');
        $pass = (string) ($db['password'] ?? '');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    public static function setting(string $key, ?string $default = null): ?string
    {
        static $settings = null;

        if ($settings === null) {
            $settings = [];
            $stmt = self::pdo()->query('SELECT setting_key, setting_value FROM settings');
            foreach ($stmt->fetchAll() as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }
}
