<?php
declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

final class PdoFactory
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
            throw new RuntimeException('Constantes de base de datos no definidas');
        }

        $port = defined('DB_PORT') ? (string)DB_PORT : '3306';
        $charset = defined('DB_CHARSET') ? (string)DB_CHARSET : 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            $port,
            DB_NAME,
            $charset
        );

        try {
            self::$pdo = new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('No se pudo conectar a la base de datos', 0, $e);
        }

        // Alinear NOW()/CURRENT_TIMESTAMP en SQL con la zona por defecto de PHP (config.php / TIMEZONE).
        try {
            $name = @date_default_timezone_get();
            if (is_string($name) && $name !== '') {
                $offset = (new \DateTimeImmutable('now', new \DateTimeZone($name)))->format('P');
                self::$pdo->exec('SET time_zone = ' . self::$pdo->quote($offset));
            }
        } catch (\Throwable) {
            // Si MySQL rechaza el offset, se mantiene el time_zone del servidor.
        }

        return self::$pdo;
    }
}
