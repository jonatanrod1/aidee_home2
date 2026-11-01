<?php
declare(strict_types=1);
namespace App\Core;
use PDO; use RuntimeException;
final class Database {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $c = require __DIR__ . '/../config.php';
            try {
                self::$pdo = new PDO($c['dsn'], $c['user'], $c['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (\PDOException $e) { throw new RuntimeException('Sin PG: '.$e->getMessage()); }
        }
        return self::$pdo;
    }
    public static function reset(): void { self::$pdo = null; }
}
