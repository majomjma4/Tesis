<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$config = require APP_PATH . '/config/app.php';
$timezone = (string) ($config['timezone'] ?? 'America/Guayaquil');
if (in_array($timezone, timezone_identifiers_list(), true)) date_default_timezone_set($timezone);

$fileService = new SupportMaterialFileService();
$database = Database::connection();
$records = $database->query(
    "SELECT 'current' source,id,material_id,original_name,relative_path
     FROM support_material_files
     WHERE purged_at IS NULL
     UNION ALL
     SELECT 'version' source,id,material_id,original_name,relative_path
     FROM support_material_file_versions
     ORDER BY source,material_id,id"
)->fetchAll();

$registeredPaths = [];
$missingRecords = [];
foreach ($records as $record) {
    $relativePath = str_replace('\\', '/', (string) $record['relative_path']);
    $registeredPaths[$relativePath] = true;
    if (!$fileService->isAvailable($relativePath)) {
        $missingRecords[] = [
            'source' => (string) $record['source'],
            'id' => (int) $record['id'],
            'material_id' => (int) $record['material_id'],
            'original_name' => (string) $record['original_name'],
            'relative_path' => $relativePath,
        ];
    }
}

$storageRoot = ROOT_PATH . '/storage/support-materials';
$unregisteredFiles = [];
if (is_dir($storageRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $entry) {
        if (!$entry->isFile()) continue;
        $absolutePath = $entry->getPathname();
        $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($storageRoot) + 1));
        if ($relativePath === '.gitkeep' || isset($registeredPaths[$relativePath])) continue;
        $unregisteredFiles[] = [
            'relative_path' => $relativePath,
            'size_bytes' => $entry->getSize(),
        ];
    }
}

$result = [
    'checked_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'storage_directory_exists' => is_dir($storageRoot),
    'registered_records_checked' => count($records),
    'missing_records' => $missingRecords,
    'unregistered_files' => $unregisteredFiles,
    'changes_made' => false,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit(($missingRecords || $unregisteredFiles || !is_dir($storageRoot)) ? 2 : 0);
