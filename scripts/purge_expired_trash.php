<?php
declare(strict_types=1);

// CLI only: intended for a daily cron/task scheduler, never a public endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$arguments = array_slice($_SERVER['argv'] ?? [], 1);
if (array_diff($arguments, ['--dry-run'])) {
    fwrite(STDERR, "Uso: php scripts/purge_expired_trash.php [--dry-run]" . PHP_EOL);
    exit(64);
}

try {
    $result = (new AdminTrashModel())->purgeExpiredAutomatically(in_array('--dry-run', $arguments, true));
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
    exit($result['failed'] > 0 ? 2 : 0);
} catch (Throwable $exception) {
    error_log('Automatic trash purge failed: ' . $exception->getMessage());
    fwrite(STDERR, 'No fue posible ejecutar la eliminación automática de Papelera.' . PHP_EOL);
    exit(1);
}
