<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
$options = getopt('', ['database:', 'db-host:', 'db-port:', 'db-user:', 'db-password:', 'student-code:', 'duplicate-code:']);
$databaseName = trim((string) ($options['database'] ?? ''));
if ($databaseName === '' || strcasecmp($databaseName, 'tesis') === 0 || !preg_match('/^tesis_qa_[a-z0-9_]+$/i', $databaseName)) {
    fwrite(STDERR, "Uso seguro: php scripts/test_password_recovery.php --database=tesis_qa_<nombre> [--student-code=...]\n");
    exit(2);
}

$GLOBALS['config'] = require APP_PATH . '/config/app.php';
require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();
connectToIsolatedDatabase($options);

if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

echo "=== INICIANDO PRUEBAS DE RECUPERACIÓN DE CONTRASEÑA POR CÉDULA ===\n\n";

try {
    $db = Database::connection();
    $model = new PasswordResetModel();
    $service = new PasswordResetService();
    $studentCode = trim((string) ($options['student-code'] ?? 'QA-PASSWORD-STUDENT'));
    $duplicateCode = trim((string) ($options['duplicate-code'] ?? 'QA-PASSWORD-DUPLICATE'));

    // 1. Obtener un usuario de prueba con cédula (estudiante activo)
    $studentQuery = $db->prepare('
        SELECT u.id, u.email, u.session_version, u.password_hash, sp.institutional_code
        FROM student_profiles sp
        INNER JOIN users u ON u.id = sp.user_id
        WHERE sp.institutional_code = :code
          AND u.status = "active" AND u.deleted_at IS NULL AND u.purged_at IS NULL
        LIMIT 1
    ');
    $studentQuery->execute(['code' => $studentCode]);
    $student = $studentQuery->fetch();

    if (!$student) {
        throw new RuntimeException("No hay estudiantes activos en la base de datos para realizar la prueba.");
    }
    $userId = (int)$student['id'];
    $email = (string)$student['email'];
    $code = (string)$student['institutional_code'];
    $ip = '127.0.0.1';

    echo "Estudiante de prueba seleccionado: {$email} (ID: {$userId}, Cédula: {$code})\n";

    // Limpiar tokens previos
    $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')->execute(['id' => $userId]);
    echo "1. Tokens previos limpiados.\n";

    // 2. Probar resolución por Cédula (Caso A: Cédula única activa)
    $resolvedSingle = $model->resolveUserByInstitutionalCode($code);
    if (count($resolvedSingle) !== 1) throw new RuntimeException('La cedula unica no devolvio exactamente un usuario.');
    echo "2. ¿Cédula única devuelve exactamente 1 registro? " . (count($resolvedSingle) === 1 ? "SÍ (Paso)" : "NO (Fallo)") . "\n";
    if (count($resolvedSingle) === 1) {
        echo "   ¿El ID coincide? " . ((int)$resolvedSingle[0]['user_id'] === $userId ? "SÍ (Paso)" : "NO (Fallo)") . "\n";
        echo "   ¿El Email coincide? " . ($resolvedSingle[0]['email'] === $email ? "SÍ (Paso)" : "NO (Fallo)") . "\n";
    }

    // Caso B: Cédula inexistente
    $resolvedNone = $model->resolveUserByInstitutionalCode('9999999999');
    if (count($resolvedNone) !== 0) throw new RuntimeException('La cedula inexistente devolvio un usuario.');
    echo "3. ¿Cédula inexistente devuelve 0 registros? " . (count($resolvedNone) === 0 ? "SÍ (Paso)" : "NO (Fallo)") . "\n";

    // Caso C: Cédula duplicada QA
    $resolvedDup = $model->resolveUserByInstitutionalCode($duplicateCode);
    if (count($resolvedDup) < 2) throw new RuntimeException('El codigo duplicado QA no devolvio al menos dos usuarios.');
    echo "4. ¿Cédula duplicada QA ({$duplicateCode}) devuelve >1 registros? " . (count($resolvedDup) > 1 ? "SÍ (Paso)" : "NO (Fallo)") . "\n";

    // 3. Crear token y verificar persistencia
    $tokenData = $model->createToken($userId, $ip);
    echo "5. Token generado: SÍ (Ocultado para seguridad)\n";
    echo "   Hash almacenado: presente (valor no mostrado)\n";

    // Verificar si se guardó en DB
    $saved = $db->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE id = :id');
    $saved->execute(['id' => $tokenData['id']]);
    $savedCount = (int) $saved->fetchColumn();
    if ($savedCount !== 1) throw new RuntimeException('El token QA no se guardo exactamente una vez.');
    echo "   ¿Guardado en DB? " . ($savedCount === 1 ? "SÍ (Paso)" : "NO (Fallo)") . "\n";

    // 4. Probar Rate Limit por IP / Usuario
    $isRateLimitedAfter = $model->isRateLimited($userId, $ip);
    if (!$isRateLimitedAfter) throw new RuntimeException('El limite de recuperacion no detecto el token QA.');
    echo "6. ¿Está limitado por tarifa después de crear token? " . ($isRateLimitedAfter ? "SÍ (Paso)" : "NO (Fallo)") . "\n";

    // 5. Validar búsqueda de token válido
    $found = $model->findValidToken($tokenData['raw_token']);
    if ($found === null) throw new RuntimeException('El token QA valido no pudo recuperarse.');
    echo "7. ¿Encontró el token válido? " . ($found !== null ? "SÍ (Paso)" : "NO (Fallo)") . "\n";

    // 6. Probar expiración
    $db->prepare('UPDATE password_reset_tokens SET expires_at = :expires WHERE id = :id')->execute([
        'expires' => gmdate('Y-m-d H:i:s', time() - 3600), // Expirado hace 1 hora
        'id' => $tokenData['id']
    ]);
    $foundExpired = $model->findValidToken($tokenData['raw_token']);
    if ($foundExpired !== null) throw new RuntimeException('El token expirado QA siguio siendo valido.');
    echo "8. ¿Rechazó token expirado? " . ($foundExpired === null ? "SÍ (Paso)" : "NO (Fallo)") . "\n";

    // Restaurar expiración
    $db->prepare('UPDATE password_reset_tokens SET expires_at = :expires WHERE id = :id')->execute([
        'expires' => gmdate('Y-m-d H:i:s', time() + 3600),
        'id' => $tokenData['id']
    ]);

    // 7. Probar consumo
    $consumed = $model->consumeToken((int)$tokenData['id']);
    if (!$consumed) throw new RuntimeException('El token QA valido no pudo consumirse.');
    echo "9. ¿Consumió el token exitosamente? " . ($consumed ? "SÍ (Paso)" : "NO (Fallo)") . "\n";

    $consumedAgain = $model->consumeToken((int)$tokenData['id']);
    if ($consumedAgain) throw new RuntimeException('El token QA pudo consumirse dos veces.');
    echo "   ¿Rechazó re-consumo del mismo token? " . (!$consumedAgain ? "SÍ (Paso)" : "NO (Fallo)") . "\n";

    // 8. Probar invalidación de anteriores
    $t1 = $model->createToken($userId, '127.0.0.2');
    $t2 = $model->createToken($userId, '127.0.0.3');
    $model->invalidatePreviousTokens($userId, (int)$t2['id']);
    $foundT1 = $model->findValidToken($t1['raw_token']);
    $foundT2 = $model->findValidToken($t2['raw_token']);
    if ($foundT1 !== null || $foundT2 === null) throw new RuntimeException('La invalidacion de tokens QA no produjo el estado esperado.');
    echo "10. ¿Se invalidó el token previo automáticamente? " . ($foundT1 === null ? "SÍ (Paso)" : "NO (Fallo)") . "\n";
    echo "    ¿El último token sigue siendo válido? " . ($foundT2 !== null ? "SÍ (Paso)" : "NO (Fallo)") . "\n";

    // 9. Probar cambio de contraseña e invalidación de sesión
    $prevVersion = (int)$student['session_version'];
    (new AuthModel())->resetPasswordWithoutCurrent($userId, 'NuevaContraFuerte123!');

    $updatedUser = $db->prepare('SELECT session_version, password_hash FROM users WHERE id = :id');
    $updatedUser->execute(['id' => $userId]);
    $updated = $updatedUser->fetch();
    $newVersion = (int)$updated['session_version'];
    if ($newVersion <= $prevVersion || !password_verify('NuevaContraFuerte123!', (string) $updated['password_hash'])) throw new RuntimeException('El restablecimiento QA no actualizo la contrasena y sesion esperadas.');

    echo "11. ¿Se incrementó la versión de sesión? " . ($newVersion > $prevVersion ? "SÍ (Paso)" : "NO (Fallo)") . "\n";

    // Restaurar contraseña anterior
    $db->prepare('UPDATE users SET password_hash = :hash, session_version = :version WHERE id = :id')->execute([
        'hash' => $student['password_hash'],
        'version' => $prevVersion,
        'id' => $userId
    ]);
    echo "    Contraseña y versión original restauradas.\n";

    // Limpiar tokens
    $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')->execute(['id' => $userId]);
    $remainingTokens = $db->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = :id');
    $remainingTokens->execute(['id' => $userId]);
    if ((int) $remainingTokens->fetchColumn() !== 0) throw new RuntimeException('La limpieza QA dejo tokens residuales.');
    echo "\n=== TODAS LAS PRUEBAS COMPLETADAS CON ÉXITO ===\n";

} catch (Throwable $e) {
    echo "\nERROR DURANTE LA PRUEBA: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

/** Connects the test process only to an explicitly named disposable QA database. */
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
}
