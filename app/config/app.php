<?php

declare(strict_types=1);

// Configuracion base de la aplicacion.
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');

$config = [
    'app_name' => 'Gestion Documental Academica',
    'base_url' => $baseUrl,
    'environment' => getenv('APP_ENV') ?: 'development',
    'dev_autoreload' => filter_var(getenv('DEV_AUTORELOAD') ?: 'true', FILTER_VALIDATE_BOOL),
];

$localFile = __DIR__ . '/app.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

return $config;
