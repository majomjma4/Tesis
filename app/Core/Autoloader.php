<?php

declare(strict_types=1);

final class Autoloader
{
    private const DIRECTORIES = ['Core', 'controllers', 'models', 'services'];

    // Inicio de carga automática de clases
    // Busca clases propias en las capas MVC y servicios para evitar dependencias manuales en el Front Controller.
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
                return;
            }

            foreach (self::DIRECTORIES as $directory) {
                $file = APP_PATH . '/' . $directory . '/' . $class . '.php';
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }
    // Final de carga automática de clases
}
