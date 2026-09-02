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
 * Construye el nombre visible de un paquete ZIP de proyecto sin exponer el ID
 * salvo como último recurso. El nombre físico del paquete no depende de esta
 * función y puede continuar usando la clave técnica del almacenamiento.
 */
function project_download_filename(array $project): string
{
    $sanitize = static function (mixed $value, int $limit): string {
        $value = trim((string) $value);
        if ($value === '') return '';
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_C);
            if (is_string($normalized)) $value = $normalized;
        }
        $value = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        $value = (string) preg_replace('/[^\p{L}\p{N}_-]+/u', '_', $value);
        $value = (string) preg_replace('/_+/u', '_', $value);
        $value = trim($value, " ._-\t\r\n");
        if ($value === '') return '';
        return trim(mb_substr($value, 0, $limit, 'UTF-8'), " ._-");
    };

    $title = $sanitize($project['title'] ?? '', 140);
    if ($title === '') return 'Proyecto_' . max(0, (int) ($project['id'] ?? 0)) . '.zip';

    $code = $sanitize($project['code'] ?? '', 60);
    if ($code !== '') {
        $titleLimit = max(1, 180 - mb_strlen($code, 'UTF-8') - 1);
        $title = trim(mb_substr($title, 0, $titleLimit, 'UTF-8'), " ._-");
        $name = $title . '_' . $code;
    } else {
        $name = mb_substr($title, 0, 180, 'UTF-8');
    }

    return trim($name, " ._-") . '.zip';
}

/** Returns an ASCII fallback for clients that do not support filename*. */
function project_download_filename_fallback(string $filename, string $fallback = 'archivo'): string
{
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($filename, Normalizer::FORM_D);
        if (is_string($normalized)) {
            $filename = (string) preg_replace('/\p{Mn}+/u', '', $normalized);
        }
    }
    if (function_exists('iconv')) {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        if (is_string($transliterated) && $transliterated !== '') $filename = $transliterated;
    }
    $safe = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
    $safe = trim($safe, '._-');
    return $safe !== '' ? $safe : $fallback;
}

/** Builds a safe RFC 6266 Content-Disposition value for a project package. */
function project_download_content_disposition(array $project): string
{
    $filename = project_download_filename($project);
    return 'attachment; filename="' . project_download_filename_fallback($filename, 'Proyecto.zip') . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
}

/** Returns a safe same-origin path for post-action redirects. */
function safe_internal_redirect_target(string $candidate, string $fallback): string
{
    $candidate = trim($candidate);
    if ($candidate === '' || strlen($candidate) > 2048) return $fallback;
    if (preg_match('/[\x00-\x1F\x7F]/', $candidate) || str_contains($candidate, '\\')) return $fallback;

    $normalized = $candidate;
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $decoded = rawurldecode($normalized);
        if ($decoded === $normalized) break;
        $normalized = $decoded;
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $normalized) || str_contains($normalized, '\\')) return $fallback;
    if (!str_starts_with($normalized, '/') || str_starts_with($normalized, '//')) return $fallback;
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $normalized)) return $fallback;

    return $candidate;
}

function calendar_date_key(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) return null;
    return $value;
}

function calendar_date_route(?string $value): ?string
{
    $date = calendar_date_key($value);
    return $date === null ? null : route('calendar') . '&date=' . rawurlencode($date);
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
        'corrections_requested' => ['status' => 'Correcciones solicitadas', 'stage' => 'En proceso'],
        'changes_required' => ['status' => 'Correcciones solicitadas', 'stage' => 'En proceso'],
        'approved' => ['status' => 'Aprobado', 'stage' => 'Por publicar'],
        'defense' => ['status' => 'En tribunal', 'stage' => 'Por finalizar'],
        'tribunal_approved' => ['status' => 'Aprobado por el Tribunal', 'stage' => 'Por publicar'],
        'published' => ['status' => 'Publicado', 'stage' => 'Finalizado'],
        'completed' => ['status' => 'Completado', 'stage' => 'Finalizado'],
    ];

    return $labels[$status] ?? [
        'status' => $status !== '' ? $status : 'Estado no disponible',
        'stage' => 'Etapa no disponible',
    ];
}

/** Traduce resultados de entregas; conserva el token anterior solo para lectura histórica. */
function project_delivery_status_label(string $status): string
{
    return match ($status) {
        'submitted' => 'Enviada',
        'under_review' => 'En revisión',
        'corrections_requested', 'changes_required' => 'Correcciones solicitadas',
        'approved' => 'Aprobada',
        default => $status !== '' ? $status : 'Sin resultado',
    };
}

function utc_datetime(?string $value): ?DateTimeImmutable
{
    if (!$value) return null;
    try {
        $timezone = (string) (($GLOBALS['config']['timezone'] ?? 'America/Guayaquil'));
        if (!in_array($timezone, timezone_identifiers_list(), true)) $timezone = 'America/Guayaquil';
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone));
    } catch (Throwable) {
        return null;
    }
}

function format_utc_datetime(?string $value, bool $withTime = false): string
{
    if (!$value) return 'No disponible';
    $date = utc_datetime($value);
    return $date?->format($withTime ? 'd/m/Y H:i' : 'd/m/Y') ?? 'No disponible';
}
function app_is_development(): bool
{
    $config = $GLOBALS['config'] ?? [];
    return ($config['environment'] ?? 'production') === 'development';
}
// Final de construcción de URL internas
