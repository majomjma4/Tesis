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
    'auth_required' => filter_var(getenv('AUTH_REQUIRED') ?: 'false', FILTER_VALIDATE_BOOL),
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Guayaquil',
    'settings_encryption_key' => getenv('APP_SETTINGS_ENCRYPTION_KEY') ?: '',
    // Server dependency for private DOCX review previews. Never expose this path to browsers.
    'libreoffice_path' => getenv('LIBREOFFICE_PATH') ?: '',
    'libreoffice_timeout_seconds' => (int) (getenv('LIBREOFFICE_TIMEOUT_SECONDS') ?: 45),
];

$localFile = __DIR__ . '/app.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

return $config;
