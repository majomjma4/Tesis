<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$health = Database::healthCheck();
echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(($health['enabled'] ?? false) && !($health['connected'] ?? false) ? 1 : 0);
