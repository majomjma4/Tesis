<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$config = require APP_PATH . '/config/app.php';
$timezone = (string) ($config['timezone'] ?? 'America/Guayaquil');
if (in_array($timezone, timezone_identifiers_list(), true)) date_default_timezone_set($timezone);

$apply = !in_array('--dry-run', $argv ?? [], true);
$database = Database::connection();
$fileService = new SupportMaterialFileService();
$records = $database->query(
    "SELECT 'current' source,id,material_id,original_name,relative_path,sha256
     FROM support_material_files
     WHERE purged_at IS NULL
     UNION ALL
     SELECT 'version' source,id,material_id,original_name,relative_path,sha256
     FROM support_material_file_versions
     ORDER BY source,material_id,id"
)->fetchAll();

$updates = [];
$missing = [];
$invalidExisting = [];
$alreadyHashed = 0;
foreach ($records as $record) {
    $existing = strtolower(trim((string) ($record['sha256'] ?? '')));
    if ($existing !== '') {
        if (preg_match('/^[a-f0-9]{64}$/', $existing) === 1) {
            $alreadyHashed++;
        } else {
            $invalidExisting[] = [
                'source' => (string) $record['source'],
                'id' => (int) $record['id'],
                'sha256' => $existing,
            ];
        }
        continue;
    }
    $path = $fileService->resolveRelativePath((string) $record['relative_path']);
    if ($path === null) {
        $missing[] = [
            'source' => (string) $record['source'],
            'id' => (int) $record['id'],
            'material_id' => (int) $record['material_id'],
            'original_name' => (string) $record['original_name'],
            'relative_path' => str_replace('\\', '/', (string) $record['relative_path']),
        ];
        continue;
    }
    $hash = hash_file('sha256', $path);
    if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
        $missing[] = [
            'source' => (string) $record['source'],
            'id' => (int) $record['id'],
            'material_id' => (int) $record['material_id'],
            'original_name' => (string) $record['original_name'],
            'relative_path' => str_replace('\\', '/', (string) $record['relative_path']),
            'error' => 'No fue posible calcular SHA-256.',
        ];
        continue;
    }
    $updates[] = ['source' => (string) $record['source'], 'id' => (int) $record['id'], 'sha256' => $hash];
}

$updated = 0;
if ($apply && $updates !== []) {
    Database::transaction(function (PDO $connection) use ($updates, &$updated): void {
        $current = $connection->prepare(
            "UPDATE support_material_files SET sha256=:sha256
             WHERE id=:id AND (sha256 IS NULL OR sha256='')"
        );
        $version = $connection->prepare(
            "UPDATE support_material_file_versions SET sha256=:sha256
             WHERE id=:id AND (sha256 IS NULL OR sha256='')"
        );
        foreach ($updates as $item) {
            $statement = $item['source'] === 'current' ? $current : $version;
            $statement->execute(['sha256' => $item['sha256'], 'id' => $item['id']]);
            $updated += $statement->rowCount();
        }
    });
}

$result = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'records_checked' => count($records),
    'already_hashed' => $alreadyHashed,
    'hashes_calculated' => count($updates),
    'records_updated' => $updated,
    'missing_or_unreadable' => $missing,
    'invalid_existing_hashes' => $invalidExisting,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit(($missing !== [] || $invalidExisting !== []) ? 2 : 0);
