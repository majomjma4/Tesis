<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse desde CLI.\n");
    exit(1);
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$action = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($action, ['dry-run', 'apply', 'verify'], true)) {
    fwrite(STDERR, "Uso: php scripts/fix_direct_repository_tutor_participants.php [dry-run|apply|verify]\n");
    exit(1);
}

$db = Database::connection();
$candidateQuery = static function (PDO $connection): array {
    $query = $connection->query(
        "SELECT p.id AS project_id,p.code,p.publication_origin,p.tutor_id,
                u.id AS tutor_user_id,u.status AS user_status,u.deleted_at,u.purged_at,
                tp.user_id AS teacher_profile_user_id,tp.can_tutor,
                (SELECT COUNT(*) FROM project_participants existing_exact
                 WHERE existing_exact.project_id=p.id AND existing_exact.user_id=p.tutor_id
                   AND LOWER(existing_exact.role_code)='tutor') AS existing_exact_rows,
                (SELECT COUNT(*) FROM project_participants existing_role
                 WHERE existing_role.project_id=p.id AND existing_role.user_id=p.tutor_id
                   AND existing_role.status='active' AND existing_role.removed_at IS NULL
                   AND LOWER(existing_role.role_code) IN ('tutor','cotutor','co_tutor','co-tutor')) AS active_tutor_rows
         FROM projects p
         INNER JOIN users u ON u.id=p.tutor_id
         INNER JOIN teacher_profiles tp ON tp.user_id=u.id
         WHERE p.id IN (250,251)
           AND p.publication_origin='direct_repository'
           AND p.tutor_id IS NOT NULL
           AND u.status='active' AND u.deleted_at IS NULL AND u.purged_at IS NULL
           AND tp.can_tutor=1
           AND NOT EXISTS (
               SELECT 1 FROM project_participants pp
               WHERE pp.project_id=p.id AND pp.user_id=p.tutor_id
                 AND pp.status='active' AND pp.removed_at IS NULL
                 AND LOWER(pp.role_code) IN ('tutor','cotutor','co_tutor','co-tutor')
           )
           AND NOT EXISTS (
               SELECT 1 FROM project_participants pp_exact
               WHERE pp_exact.project_id=p.id AND pp_exact.user_id=p.tutor_id
                 AND LOWER(pp_exact.role_code)='tutor'
           )
         ORDER BY p.id"
    );
    return $query->fetchAll();
};

$expected = [250 => 27, 251 => 70];

if ($action === 'verify') {
    foreach ($expected as $projectId => $userId) {
        $query = $db->prepare(
            "SELECT COUNT(*) FROM project_participants
             WHERE project_id=:project AND user_id=:user AND LOWER(role_code)='tutor'
               AND status='active' AND removed_at IS NULL"
        );
        $query->execute(['project' => $projectId, 'user' => $userId]);
        echo 'project=' . $projectId . ' tutor_user=' . $userId . ' active_tutor_participants=' . (int) $query->fetchColumn() . PHP_EOL;
    }
    exit(0);
}

$candidates = $candidateQuery($db);
$candidateKeys = [];
foreach ($candidates as $candidate) {
    $candidateKeys[(int) $candidate['project_id']] = (int) $candidate['tutor_user_id'];
}

if ($action === 'dry-run') {
    echo 'mode=dry-run' . PHP_EOL;
    echo 'rows_to_insert=' . count($candidates) . PHP_EOL;
    foreach ($candidates as $candidate) {
        echo json_encode($candidate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    exit(0);
}

if ($candidateKeys !== $expected) {
    throw new RuntimeException('Aplicación abortada: los candidatos ya no coinciden exactamente con 250→27 y 251→70.');
}

$db->beginTransaction();
try {
    $insert = $db->prepare(
        "INSERT INTO project_participants
            (project_id,user_id,role_code,permission_level,status)
         VALUES (:project,:user,'tutor','review','active')"
    );
    $inserted = 0;
    foreach ($candidateKeys as $projectId => $userId) {
        $insert->execute(['project' => $projectId, 'user' => $userId]);
        $inserted += $insert->rowCount();
    }
    $db->commit();
    echo 'rows_inserted=' . $inserted . PHP_EOL;
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $error;
}
