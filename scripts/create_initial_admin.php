<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

$db = Database::connection();
$existing = (int) $db->query(
    "SELECT COUNT(*) FROM users
     WHERE is_admin=1 AND purged_at IS NULL"
)->fetchColumn();
if ($existing > 0) {
    fwrite(STDOUT, "Ya existe una cuenta con acceso administrativo. No se creó otra.\n");
    exit(0);
}

$email = mb_strtolower(trim((string) (getenv('INITIAL_ADMIN_EMAIL') ?: 'admin.inicial@institucion.local')));
$password = (string) (getenv('INITIAL_ADMIN_PASSWORD') ?: bin2hex(random_bytes(10)));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
    fwrite(STDERR, "Configura INITIAL_ADMIN_EMAIL y una INITIAL_ADMIN_PASSWORD de al menos 12 caracteres.\n");
    exit(1);
}

Database::transaction(static function (PDO $connection) use ($email, $password): void {
    $role = $connection->query("SELECT id FROM roles WHERE code='administrator' LIMIT 1")->fetchColumn();
    if (!$role) throw new RuntimeException('No existe el rol heredado administrator.');

    $statement = $connection->prepare(
        "INSERT INTO users
         (email,password_hash,must_change_password,full_name,is_admin,is_initial_admin,status)
         VALUES(:email,:hash,1,'Cuenta administrativa inicial',1,1,'active')"
    );
    $statement->execute(['email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $connection->lastInsertId();
    $connection->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(:user,:role)')
        ->execute(['user' => $userId, 'role' => $role]);
});

fwrite(STDOUT, "Cuenta administrativa inicial creada.\nCorreo: {$email}\nContraseña temporal: {$password}\n");
fwrite(STDOUT, "Guarda esta contraseña ahora: no volverá a mostrarse y está almacenada únicamente como hash.\n");
