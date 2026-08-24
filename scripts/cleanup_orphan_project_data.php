<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script sólo puede ejecutarse desde CLI.\n");
    exit(1);
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$action = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($action, ['dry-run', 'cleanup', 'verify'], true)) {
    fwrite(STDERR, "Uso: php scripts/cleanup_orphan_project_data.php [dry-run|cleanup|verify]\n");
    exit(1);
}

$db = Database::connection();
$orphanProject = static fn(string $alias): string => "NOT EXISTS (SELECT 1 FROM projects p0 WHERE p0.id={$alias}.project_id)";
$definitions = [
    'project_participants' => [
        'sql' => static function () use ($orphanProject): string { return 'SELECT id,project_id,user_id,role_code,permission_level,is_leader,status,assigned_at,removed_at FROM project_participants pp WHERE ' . $orphanProject('pp') . ' ORDER BY id'; },
        'delete' => 'DELETE FROM project_participants WHERE NOT EXISTS (SELECT 1 FROM projects p0 WHERE p0.id=project_participants.project_id)',
    ],
    'project_deliveries' => [
        'sql' => static function () use ($orphanProject): string { return 'SELECT id,project_id,stage_id,version_number,title,comment,status,submitted_by,submitted_at FROM project_deliveries d WHERE ' . $orphanProject('d') . ' ORDER BY id'; },
        'delete' => 'DELETE FROM project_deliveries WHERE NOT EXISTS (SELECT 1 FROM projects p0 WHERE p0.id=project_deliveries.project_id)',
    ],
    'project_stages' => [
        'sql' => static function () use ($orphanProject): string { return 'SELECT id,project_id,stage_code,label,position,status,completed_at FROM project_stages s WHERE ' . $orphanProject('s') . ' ORDER BY id'; },
        'delete' => 'DELETE FROM project_stages WHERE NOT EXISTS (SELECT 1 FROM projects p0 WHERE p0.id=project_stages.project_id)',
    ],
    'project_observations' => [
        'sql' => static function () use ($orphanProject): string { return 'SELECT id,project_id,delivery_id,file_id,file_checksum_sha256,project_file_version_id,author_id,category,location_reference,selection_anchor,body,status,created_at,resolved_at FROM project_observations o WHERE ' . $orphanProject('o') . ' ORDER BY id'; },
        'delete' => 'DELETE FROM project_observations WHERE NOT EXISTS (SELECT 1 FROM projects p0 WHERE p0.id=project_observations.project_id)',
    ],
    'project_comments' => [
        'sql' => static function () use ($orphanProject): string { return 'SELECT id,project_id,author_id,parent_id,delivery_id,file_id,observation_id,body,created_at,updated_at,deleted_at FROM project_comments c WHERE ' . $orphanProject('c') . ' ORDER BY id'; },
        'delete' => 'DELETE FROM project_comments WHERE NOT EXISTS (SELECT 1 FROM projects p0 WHERE p0.id=project_comments.project_id)',
    ],
    'observation_responses' => [
        'sql' => static function () use ($orphanProject): string { return 'SELECT r.id,r.observation_id,r.author_id,r.body,r.created_at FROM observation_responses r INNER JOIN project_observations o ON o.id=r.observation_id WHERE ' . $orphanProject('o') . ' ORDER BY r.id'; },
        'delete' => 'DELETE r FROM observation_responses r INNER JOIN project_observations o ON o.id=r.observation_id WHERE NOT EXISTS (SELECT 1 FROM projects p0 WHERE p0.id=o.project_id)',
    ],
    'project_file_version_addressed_observations' => [
        'sql' => static function () use ($orphanProject): string { return 'SELECT a.change_id,a.observation_id FROM project_file_version_addressed_observations a INNER JOIN project_observations o ON o.id=a.observation_id WHERE ' . $orphanProject('o') . ' ORDER BY a.change_id,a.observation_id'; },
        'delete' => 'DELETE a FROM project_file_version_addressed_observations a INNER JOIN project_observations o ON o.id=a.observation_id WHERE NOT EXISTS (SELECT 1 FROM projects p0 WHERE p0.id=o.project_id)',
    ],
];

$collect = static function (PDO $connection) use ($definitions): array {
    $result = [];
    foreach ($definitions as $table => $definition) {
        $result[$table] = $connection->query($definition['sql']())->fetchAll();
    }
    return $result;
};

if ($action === 'verify') {
    foreach ($collect($db) as $table => $rows) echo 'orphan_' . $table . '=' . count($rows) . PHP_EOL;
    exit(0);
}

$rowsByTable = $collect($db);
if ($action === 'dry-run') {
    echo "mode=dry-run\n";
    foreach ($rowsByTable as $table => $rows) {
        echo $table . '=' . count($rows) . PHP_EOL;
        foreach ($rows as $row) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    echo 'total=' . array_sum(array_map('count', $rowsByTable)) . PHP_EOL;
    exit(0);
}

$db->beginTransaction();
try {
    $deleted = [];
    foreach (['observation_responses','project_file_version_addressed_observations','project_comments','project_observations','project_deliveries','project_stages','project_participants'] as $table) {
        $statement = $db->prepare($definitions[$table]['delete']);
        $statement->execute();
        $deleted[$table] = $statement->rowCount();
    }
    $db->commit();
    foreach ($deleted as $table => $count) echo 'cleaned_' . $table . '=' . $count . PHP_EOL;
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
}
