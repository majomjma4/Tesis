<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    // Inicio de conexión compartida a MySQL
    // Crea una sola instancia PDO y configura errores, resultados y consultas preparadas.
    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require APP_PATH . '/config/database.php';

        if (!$config['enabled']) {
            throw new RuntimeException('La conexion de base de datos esta deshabilitada en este entorno.');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['name'], $config['charset']);

        self::$connection = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]);

        return self::$connection;
    }

    public static function isEnabled(): bool
    {
        $config = require APP_PATH . '/config/database.php';
        return (bool) ($config['enabled'] ?? false);
    }

    public static function transaction(callable $operation): mixed
    {
        $connection = self::connection();
        $connection->beginTransaction();
        register_shutdown_function(static function () use ($connection): void {
            if ($connection instanceof PDO && $connection->inTransaction()) {
                try {
                    $connection->rollBack();
                } catch (Throwable $exception) {
                    // No lanzar excepciones durante shutdown.
                }
            }
        });
        try {
            $result = $operation($connection);
            $connection->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            throw $exception;
        }
    }

    public static function healthCheck(): array
    {
        if (!self::isEnabled()) return ['enabled' => false, 'connected' => false, 'message' => 'Base de datos deshabilitada.'];
        try {
            $version = (string) self::connection()->query('SELECT VERSION()')->fetchColumn();
            return ['enabled' => true, 'connected' => true, 'version' => $version, 'message' => 'Conexión disponible.'];
        } catch (Throwable $exception) {
            return ['enabled' => true, 'connected' => false, 'message' => 'No fue posible conectar con MariaDB.'];
        }
    }
    // Final de conexión compartida a MySQL
}
