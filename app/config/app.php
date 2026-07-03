<?php

declare(strict_types=1);

// Configuracion base de la aplicacion.
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');

return [
    'app_name' => 'Gestion Documental Academica',
    'base_url' => $baseUrl,
    'dev_autoreload' => true,
];
