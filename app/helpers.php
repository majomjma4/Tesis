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

/**
 * Etiquetas institucionales del estado y del momento del ciclo académico.
 *
 * @return array{status:string,stage:string}
 */
function project_academic_labels(string $status): array
{
    static $labels = [
        'development' => ['status' => 'En desarrollo', 'stage' => 'En proceso'],
        'under_review' => ['status' => 'En revisión', 'stage' => 'En proceso'],
        'changes_required' => ['status' => 'Requiere cambios', 'stage' => 'En proceso'],
        'approved' => ['status' => 'Aprobado', 'stage' => 'Por publicar'],
        'defense' => ['status' => 'En tribunal', 'stage' => 'Por finalizar'],
        'tribunal_approved' => ['status' => 'Aprobado por el Tribunal', 'stage' => 'Por publicar'],
        'published' => ['status' => 'Publicado', 'stage' => 'Finalizado'],
    ];

    return $labels[$status] ?? [
        'status' => $status !== '' ? $status : 'Estado no disponible',
        'stage' => 'Etapa no disponible',
    ];
}
// Final de construcción de URL internas
