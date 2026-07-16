<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require APP_PATH . '/Core/Database.php';
require APP_PATH . '/models/NotificationModel.php';

try {
    $deleted = (new NotificationModel())->purgeExpiredTrash(30);
    fwrite(STDOUT, "Notificaciones eliminadas: {$deleted}" . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'No fue posible limpiar la papelera: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
