<?php

declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

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
