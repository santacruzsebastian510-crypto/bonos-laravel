<?php
namespace App\Support;

use PDO;

/**
 * Conexión PDO para los modelos. Lee los datos de .env a través de config/database.php
 * (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD), así que ya no hay credenciales en el código.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config('database.connections.mysql');
            self::$pdo = new PDO(
                'mysql:host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['database'] . ';charset=utf8mb4',
                $c['username'],
                $c['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            self::$pdo->exec("SET time_zone = '-05:00'"); // CURDATE()/NOW() = hora de Colombia
        }
        return self::$pdo;
    }
}
