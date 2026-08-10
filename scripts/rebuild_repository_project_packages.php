<?php

declare(strict_types=1);

/**
 * rebuild_repository_project_packages.php
 *
 * Script de mantenimiento: genera o regenera el paquete ZIP institucional
 * para todos los proyectos publicados que aún no lo tienen en disco
 * (o para todos, si se pasa el flag --force).
 *
 * Uso (CLI desde el directorio raíz del proyecto):
 *   php scripts/rebuild_repository_project_packages.php
 *   php scripts/rebuild_repository_project_packages.php --force
 *   php scripts/rebuild_repository_project_packages.php --project-id=27
 *
 * Opciones:
 *   --force         Regenera el ZIP aunque ya exista en disco.
 *   --project-id=N  Procesa únicamente el proyecto con ese ID.
 *   --dry-run       Solo reporta qué haría, sin generar nada.
 *
 * No modifica ningún registro de MariaDB.
 * Seguro para ejecutar en producción sobre proyectos ya publicados.
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

// ── Parsear argumentos ───────────────────────────────────────────────────────

$force     = in_array('--force', $argv ?? [], true);
$dryRun    = in_array('--dry-run', $argv ?? [], true);
$projectId = null;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--project-id=(\d+)$/', $arg, $m)) {
        $projectId = (int) $m[1];
    }
}

// ── Obtener proyectos publicados ─────────────────────────────────────────────

$db = Database::connection();

if ($projectId !== null) {
    $stmt = $db->prepare(
        "SELECT p.id, p.title, p.status
         FROM projects p
         WHERE p.id = :id AND p.deleted_at IS NULL AND p.status = 'published'"
    );
    $stmt->execute(['id' => $projectId]);
} else {
    $stmt = $db->query(
        "SELECT p.id, p.title, p.status
         FROM projects p
         WHERE p.deleted_at IS NULL AND p.status = 'published'
         ORDER BY p.id ASC"
    );
}
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($projects)) {
    echo "No se encontraron proyectos publicados.\n";
    exit(0);
}

$packageService = new ProjectRepositoryPackageService();

$stats = [
    'total'     => count($projects),
    'skipped'   => 0,
    'built'     => 0,
    'failed'    => 0,
    'dry_run'   => 0,
];

$failures = [];

echo sprintf("Proyectos publicados encontrados: %d\n", count($projects));
echo str_repeat('-', 60) . "\n";

foreach ($projects as $project) {
    $id    = (int) $project['id'];
    $title = (string) $project['title'];
    $path  = ProjectRepositoryPackageService::packagePath($id);
    $exists = is_file($path) && filesize($path) > 0;

    if ($exists && !$force) {
        $size = ArchiveService::formatBytes((int) filesize($path));
        echo "[SKIP]  ID {$id} — ZIP ya existe ({$size}): {$title}\n";
        $stats['skipped']++;
        continue;
    }

    if ($dryRun) {
        $tag = $exists ? '[DRY/REGENERAR]' : '[DRY/GENERAR  ]';
        echo "{$tag}  ID {$id} — {$title}\n";
        $stats['dry_run']++;
        continue;
    }

    echo ($exists ? '[REGEN] ' : '[BUILD] ') . " ID {$id} — {$title} ... ";

    try {
        $result = $packageService->buildForProject($id);
        if ($result === null) {
            echo "SIN ARCHIVOS (omitido)\n";
            $stats['skipped']++;
        } else {
            echo "OK ({$result['size']}, {$result['file_count']} archivo(s))\n";
            $stats['built']++;
        }
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $failures[] = ['id' => $id, 'title' => $title, 'error' => $e->getMessage()];
        $stats['failed']++;
    }
}

// ── Resumen ──────────────────────────────────────────────────────────────────

echo str_repeat('-', 60) . "\n";
echo sprintf(
    "Resumen: %d total | %d generados | %d omitidos | %d errores%s\n",
    $stats['total'],
    $stats['built'],
    $stats['skipped'],
    $stats['failed'],
    $dryRun ? ' | [DRY-RUN]' : ''
);

if (!empty($failures)) {
    echo "\nProyectos con error:\n";
    foreach ($failures as $f) {
        echo sprintf("  ID %d — %s\n    %s\n", $f['id'], $f['title'], $f['error']);
    }
}

// ── Verificar ZIPs generados ─────────────────────────────────────────────────

if (!$dryRun && $stats['built'] > 0) {
    echo "\nVerificando integridad de los ZIPs generados en storage/repository:\n";
    $repoDir = ROOT_PATH . '/storage/repository';
    $zipFiles = glob($repoDir . '/project_*.zip') ?: [];
    echo sprintf("  Archivos ZIP en disco: %d\n", count($zipFiles));
    $totalSize = 0;
    foreach ($zipFiles as $zf) {
        $totalSize += (int) filesize($zf);
    }
    echo sprintf("  Tamaño total: %s\n", ArchiveService::formatBytes($totalSize));
}

exit($stats['failed'] > 0 ? 2 : 0);
