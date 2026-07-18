<?php

declare(strict_types=1);

// Inicio de escape de salida
// Protege los textos insertados en HTML frente a caracteres interpretables por el navegador.
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
// Final de escape de salida

// Inicio de construcción de URL internas
// Centraliza las rutas base, los recursos versionados y los enlaces hacia páginas del sistema.
function base_url(string $path = ''): string
{
    $config = $GLOBALS['config'] ?? ['base_url' => ''];
    $base = rtrim((string) $config['base_url'], '/');
    $path = ltrim($path, '/');

    return $path === '' ? ($base === '' ? '/' : $base) : $base . '/' . $path;
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $url = base_url('public/assets/' . $path);
    $filePath = defined('ROOT_PATH') ? ROOT_PATH . '/public/assets/' . $path : '';

    return is_file($filePath) ? $url . '?v=' . filemtime($filePath) : $url;
}

function route(string $page = 'dashboard'): string
{
    return base_url('index.php?page=' . urlencode($page));
}
// Final de construcción de URL internas
