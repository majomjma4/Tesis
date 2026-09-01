<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

$options = getopt('', [
    'worker',
    'database:',
    'db-host:',
    'db-port:',
    'db-user:',
    'db-password:',
    'project-id:',
    'actor-id:',
    'session-dir:',
    'action64:',
    'omit-action',
    'invalid-csrf',
    'no-admin-mode',
    'availability:',
]);
$databaseName = trim((string) ($options['database'] ?? ''));
if ($databaseName === '' || strcasecmp($databaseName, 'tesis') === 0 || !preg_match('/^tesis_qa_[a-z0-9_]+$/i', $databaseName)) {
    fwrite(STDERR, "Uso seguro: php scripts/test_admin_repository_publish_actions.php --database=tesis_qa_<nombre>\n");
    exit(2);
}

$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();
connectToIsolatedDatabase($options);

ini_set('display_errors', '0');

if (isset($options['worker'])) {
    runWorker($options);
    exit(0);
}

$database = Database::connection();
$projectId = 0;
$sessionDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tesis-admin-repository-' . bin2hex(random_bytes(8));
$results = [];
$failure = null;

try {
    if (!mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
        throw new RuntimeException('No fue posible preparar el directorio temporal de sesión QA.');
    }
    requireColumns($database);

    $actor = $database->query(
        "SELECT id FROM users
         WHERE is_admin=1 AND status='active' AND deleted_at IS NULL AND purged_at IS NULL
         ORDER BY id LIMIT 1"
    )->fetchColumn();
    $type = $database->query(
        "SELECT id FROM project_types WHERE is_active=1 AND code<>'thesis' ORDER BY id LIMIT 1"
    )->fetchColumn();
    $career = $database->query('SELECT id FROM careers WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
    $period = $database->query(
        "SELECT id FROM academic_periods WHERE status='active' ORDER BY id LIMIT 1"
    )->fetchColumn();
    if (!$actor || !$type || !$career || !$period) {
        throw new RuntimeException('No existen catálogos QA suficientes para la prueba.');
    }

    $actorId = (int) $actor;
    $tag = 'QA_ADMIN_REPOSITORY_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(4));
    $insert = $database->prepare(
        "INSERT INTO projects
            (code,project_type_id,career_id,academic_period_id,title,status,published_at,is_available,created_by)
         VALUES
            (:code,:type,:career,:period,:title,'published',UTC_TIMESTAMP(),1,:actor)"
    );
    $insert->execute([
        'code' => $tag,
        'type' => (int) $type,
        'career' => (int) $career,
        'period' => (int) $period,
        'title' => $tag,
        'actor' => $actorId,
    ]);
    $projectId = (int) $database->lastInsertId();
    $baseline = projectSnapshot($database, $projectId);
    if ($baseline === null) throw new RuntimeException('No fue posible leer el proyecto QA creado.');

    $response = invokeEndpoint($projectId, $actorId, $sessionDirectory, ['action' => 'unpublish']);
    expectResponse($response, 200, true, 'unpublish');
    $withdrawn = projectSnapshot($database, $projectId);
    expect($withdrawn !== null, 'unpublish eliminó inesperadamente el proyecto QA.');
    expect($withdrawn['status'] === $baseline['status'], 'unpublish alteró el estado académico.');
    expect($withdrawn['published_at'] === $baseline['published_at'], 'unpublish alteró published_at.');
    expect((int) $withdrawn['is_available'] === (int) $baseline['is_available'], 'unpublish alteró is_available.');
    expect($withdrawn['withdrawn_at'] !== null && $withdrawn['withdrawn_at'] !== '', 'unpublish no actualizó withdrawn_at.');
    expect((int) $withdrawn['withdrawn_by'] === $actorId, 'unpublish no registró withdrawn_by correctamente.');
    expect(latestAuditAction($database, $projectId) === 'project_withdrawn', 'unpublish no generó project_withdrawn.');
    $results[] = 'unpublish:ok';

    $response = invokeEndpoint($projectId, $actorId, $sessionDirectory, ['action' => 'restore']);
    expectResponse($response, 200, true, 'restore');
    $restored = projectSnapshot($database, $projectId);
    expect($restored !== null, 'restore eliminó inesperadamente el proyecto QA.');
    expect($restored['withdrawn_at'] === null && $restored['withdrawn_by'] === null, 'restore no limpió el retiro.');
    expect($restored['status'] === $baseline['status'], 'restore alteró el estado académico.');
    expect($restored['published_at'] === $baseline['published_at'], 'restore alteró published_at.');
    expect((int) $restored['is_available'] === (int) $baseline['is_available'], 'restore alteró is_available.');
    expect(latestAuditAction($database, $projectId) === 'project_reincorporated', 'restore no generó project_reincorporated.');
    $results[] = 'restore:ok';

    $response = invokeEndpoint($projectId, $actorId, $sessionDirectory, [
        'action' => 'availability',
        'availability' => '0',
    ]);
    expectResponse($response, 200, true, 'availability off');
    $unavailable = projectSnapshot($database, $projectId);
    expect($unavailable !== null, 'availability off eliminó inesperadamente el proyecto QA.');
    expect((int) $unavailable['is_available'] === 0, 'availability off no cambió disponibilidad.');
    expect($unavailable['withdrawn_at'] === null, 'availability off retiró el proyecto.');
    expect($unavailable['status'] === $baseline['status'], 'availability off alteró el estado académico.');
    expect($unavailable['published_at'] === $baseline['published_at'], 'availability off alteró published_at.');
    expect(latestAuditAction($database, $projectId) === 'project_availability_changed', 'availability off no generó auditoría.');
    $results[] = 'availability-off:ok';

    $response = invokeEndpoint($projectId, $actorId, $sessionDirectory, [
        'action' => 'availability',
        'availability' => '1',
    ]);
    expectResponse($response, 200, true, 'availability on');
    expect(projectSnapshot($database, $projectId) === $baseline, 'availability on no restauró el estado base.');
    $results[] = 'availability-on:ok';

    $beforePublish = projectSnapshot($database, $projectId);
    $beforePublishAudit = latestAuditId($database, $projectId);
    $response = invokeEndpoint($projectId, $actorId, $sessionDirectory, ['action' => 'publish']);
    expectResponse($response, 403, false, 'publish');
    expect(projectSnapshot($database, $projectId) === $beforePublish, 'publish 403 modificó el proyecto.');
    expect(latestAuditId($database, $projectId) === $beforePublishAudit, 'publish 403 generó auditoría.');
    $results[] = 'publish-forbidden:ok';

    $invalidActions = [
        'missing' => null,
        'empty' => '',
        'unknown' => 'foo',
        'uppercase' => 'UNPUBLISH',
        'trailing-space' => 'unpublish ',
        'legacy-publication' => 'publication',
    ];
    foreach ($invalidActions as $label => $action) {
        $before = projectSnapshot($database, $projectId);
        $beforeAudit = latestAuditId($database, $projectId);
        $arguments = $action === null ? ['omit_action' => true] : ['action' => $action];
        $response = invokeEndpoint($projectId, $actorId, $sessionDirectory, $arguments);
        expectResponse($response, 422, false, $label);
        expect((string) ($response['body']['message'] ?? '') === 'La acción solicitada no es válida.', $label . ' no devolvió el mensaje esperado.');
        expect(projectSnapshot($database, $projectId) === $before, $label . ' modificó el proyecto.');
        expect(latestAuditId($database, $projectId) === $beforeAudit, $label . ' generó auditoría.');
        $results[] = $label . ':ok';
    }

    $beforeCsrf = projectSnapshot($database, $projectId);
    $beforeCsrfAudit = latestAuditId($database, $projectId);
    $response = invokeEndpoint($projectId, $actorId, $sessionDirectory, [
        'action' => 'unpublish',
        'invalid_csrf' => true,
    ]);
    expectResponse($response, 419, false, 'invalid csrf');
    expect(projectSnapshot($database, $projectId) === $beforeCsrf, 'CSRF inválido modificó el proyecto.');
    expect(latestAuditId($database, $projectId) === $beforeCsrfAudit, 'CSRF inválido generó auditoría.');
    $results[] = 'invalid-csrf:ok';

    $beforeAuthorization = projectSnapshot($database, $projectId);
    $beforeAuthorizationAudit = latestAuditId($database, $projectId);
    $response = invokeEndpoint($projectId, $actorId, $sessionDirectory, [
        'action' => 'unpublish',
        'no_admin_mode' => true,
    ]);
    expectResponse($response, 403, false, 'authorization');
    expect(projectSnapshot($database, $projectId) === $beforeAuthorization, 'autorización insuficiente modificó el proyecto.');
    expect(latestAuditId($database, $projectId) === $beforeAuthorizationAudit, 'autorización insuficiente generó auditoría.');
    $results[] = 'authorization:ok';
} catch (Throwable $error) {
    $failure = $error;
}

try {
    if ($projectId > 0) {
        $database->prepare('DELETE FROM project_audit_log WHERE project_id=:id')->execute(['id' => $projectId]);
        $database->prepare('DELETE FROM projects WHERE id=:id')->execute(['id' => $projectId]);
    }
} catch (Throwable $cleanupError) {
    $failure ??= new RuntimeException('La limpieza QA falló: ' . $cleanupError->getMessage(), 0, $cleanupError);
}
removeDirectory($sessionDirectory);

if ($failure !== null) {
    fwrite(STDERR, $failure->getMessage() . PHP_EOL);
    echo json_encode(['ok' => false, 'error' => $failure->getMessage()], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

echo json_encode(['ok' => true, 'cases' => $results], JSON_UNESCAPED_UNICODE) . PHP_EOL;

/** @param array<string,mixed> $options */
function runWorker(array $options): void
{
    $sessionDirectory = (string) ($options['session-dir'] ?? '');
    if ($sessionDirectory === '' || !is_dir($sessionDirectory)) {
        throw new RuntimeException('Directorio de sesión QA no disponible.');
    }
    session_save_path($sessionDirectory);
    session_name('QA_ADMIN_REPOSITORY');
    session_id('qa' . bin2hex(random_bytes(12)));
    session_start();
    $_SESSION = [
        'user_id' => (int) ($options['actor-id'] ?? 0),
        'roles' => isset($options['no-admin-mode']) ? ['administrator', 'teacher'] : ['administrator'],
        'role' => isset($options['no-admin-mode']) ? 'administrator' : 'administrator',
        'is_admin' => true,
        'admin_mode' => !isset($options['no-admin-mode']),
        'session_version' => 1,
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'QA admin repository action test';

    $session = new AuthSessionService();
    $_POST = [
        '_csrf' => isset($options['invalid-csrf']) ? 'invalid-token' : $session->csrfToken('admin_repository'),
        'id' => (string) ($options['project-id'] ?? 0),
    ];
    if (!isset($options['omit-action'])) {
        $encodedAction = (string) ($options['action64'] ?? '');
        $_POST['action'] = base64_decode($encodedAction, true);
    }
    if (isset($options['availability'])) {
        $_POST['is_available'] = (string) $options['availability'];
    }

    http_response_code(200);
    ob_start();
    register_shutdown_function(static function (): void {
        $body = ob_get_clean();
        echo json_encode([
            'status' => (int) (http_response_code() ?: 200),
            'body' => $body,
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    });
    (new AdminController())->publishProject();
}

function requireColumns(PDO $database): void
{
    foreach (['withdrawn_at', 'withdrawn_by', 'published_at', 'is_available'] as $column) {
        $statement = $database->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='projects' AND COLUMN_NAME=:column"
        );
        $statement->execute(['column' => $column]);
        if (!$statement->fetch()) throw new RuntimeException('Falta la columna requerida en projects: ' . $column);
    }
}

function invokeEndpoint(int $projectId, int $actorId, string $sessionDirectory, array $arguments): array
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
        . ' --worker'
        . ' --database=' . escapeshellarg((string) ($GLOBALS['qa_database_name'] ?? ''))
        . ' --project-id=' . escapeshellarg((string) $projectId)
        . ' --actor-id=' . escapeshellarg((string) $actorId)
        . ' --session-dir=' . escapeshellarg($sessionDirectory);
    if (array_key_exists('action', $arguments)) {
        $command .= ' --action64=' . escapeshellarg(base64_encode((string) $arguments['action']));
    }
    if (!empty($arguments['omit_action'])) $command .= ' --omit-action';
    if (!empty($arguments['invalid_csrf'])) $command .= ' --invalid-csrf';
    if (!empty($arguments['no_admin_mode'])) $command .= ' --no-admin-mode';
    if (array_key_exists('availability', $arguments)) {
        $command .= ' --availability=' . escapeshellarg((string) $arguments['availability']);
    }

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, ROOT_PATH);
    if (!is_resource($process)) throw new RuntimeException('No fue posible iniciar el worker endpoint-level.');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $packet = json_decode(trim((string) $stdout), true);
    if (!is_array($packet)) {
        throw new RuntimeException('Respuesta inválida del worker: ' . trim((string) $stderr));
    }
    $body = json_decode((string) ($packet['body'] ?? ''), true);
    if (!is_array($body)) throw new RuntimeException('El endpoint no devolvió JSON válido.');
    return [
        'status' => (int) ($packet['status'] ?? 0),
        'body' => $body,
        'exit_code' => $exitCode,
        'stderr' => (string) $stderr,
    ];
}

function expectResponse(array $response, int $status, bool $success, string $case): void
{
    expect((int) $response['status'] === $status, $case . ' devolvió HTTP ' . (int) $response['status'] . ', esperado ' . $status . '.');
    expect((bool) ($response['body']['success'] ?? null) === $success, $case . ' devolvió un valor success inesperado.');
}

function projectSnapshot(PDO $database, int $projectId): ?array
{
    $statement = $database->prepare(
        'SELECT status,published_at,is_available,withdrawn_at,withdrawn_by,presentation_file_id
         FROM projects WHERE id=:id'
    );
    $statement->execute(['id' => $projectId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function latestAuditId(PDO $database, int $projectId): int
{
    $statement = $database->prepare('SELECT COALESCE(MAX(id),0) FROM project_audit_log WHERE project_id=:id');
    $statement->execute(['id' => $projectId]);
    return (int) $statement->fetchColumn();
}

function latestAuditAction(PDO $database, int $projectId): ?string
{
    $statement = $database->prepare('SELECT action FROM project_audit_log WHERE project_id=:id ORDER BY id DESC LIMIT 1');
    $statement->execute(['id' => $projectId]);
    $action = $statement->fetchColumn();
    return $action === false ? null : (string) $action;
}

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function removeDirectory(string $directory): void
{
    if (!is_dir($directory)) return;
    $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $entry) {
        if ($entry->isDir()) removeDirectory($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($directory);
}

/** Connects the test process to an explicitly named disposable QA database. */
function connectToIsolatedDatabase(array $options): void
{
    $name = trim((string) ($options['database'] ?? ''));
    $host = trim((string) ($options['db-host'] ?? '127.0.0.1'));
    $port = trim((string) ($options['db-port'] ?? '3306'));
    $user = (string) ($options['db-user'] ?? 'root');
    $password = (string) ($options['db-password'] ?? '');
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]
    );
    $property = (new ReflectionClass('Database'))->getProperty('connection');
    $property->setValue(null, $pdo);
    $GLOBALS['qa_database_name'] = $name;
}
