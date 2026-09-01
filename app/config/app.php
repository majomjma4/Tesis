<?php

declare(strict_types=1);

// Configuracion base de la aplicacion.
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');

$config = [
    'app_name' => 'Gestion Documental Academica',
    'base_url' => $baseUrl,
    'environment' => getenv('APP_ENV') ?: 'production',
    'dev_autoreload' => filter_var(getenv('DEV_AUTORELOAD') ?: 'false', FILTER_VALIDATE_BOOL),
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Guayaquil',
    'settings_encryption_key' => getenv('APP_SETTINGS_ENCRYPTION_KEY') ?: '',
    // Server dependency for private DOCX review previews. Never expose this path to browsers.
    'libreoffice_path' => getenv('LIBREOFFICE_PATH') ?: '',
    'libreoffice_timeout_seconds' => (int) (getenv('LIBREOFFICE_TIMEOUT_SECONDS') ?: 45),
    'app_url' => getenv('APP_URL') ?: 'http://localhost/TESIS',
    'mail_host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'mail_port' => (int) (getenv('MAIL_PORT') ?: 587),
    'mail_encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
    'mail_username' => getenv('MAIL_USERNAME') ?: '',
    'mail_password' => getenv('SMTP_PASSWORD') ?: (getenv('MAIL_PASSWORD') ?: ''),
    'mail_from_address' => getenv('MAIL_FROM_ADDRESS') ?: '',
    'mail_from_name' => getenv('MAIL_FROM_NAME') ?: 'Gestión Documental Académica',
    'password_reset_ttl_minutes' => (int) (getenv('PASSWORD_RESET_TTL_MINUTES') ?: 15),
];

$localFile = __DIR__ . '/app.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

return $config;
