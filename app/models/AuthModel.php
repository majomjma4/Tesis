<?php

declare(strict_types=1);

final class AuthModel
{
    public function findActiveUserByLogin(string $login): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, email, username, password_hash, full_name FROM users WHERE status = 'active' AND email = :email_login LIMIT 1"
        );
        $normalizedLogin = mb_strtolower(trim($login));
        $statement->execute(['email_login' => $normalizedLogin]);
        $user = $statement->fetch();
        if (!$user) return null;
        $roles = Database::connection()->prepare('SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :user_id ORDER BY r.id');
        $roles->execute(['user_id' => $user['id']]);
        $user['roles'] = array_column($roles->fetchAll(), 'code');
        return $user;
    }

    public function recordLogin(int $userId): void
    {
        $statement = Database::connection()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $userId]);
    }

    public function getAllowedRoles(): array
    {
        // Datos temporales: luego pueden venir desde la base de datos.
        return [
            ['icon' => 'fa-user-graduate', 'label' => 'Estudiantes'],
            ['icon' => 'fa-chalkboard-user', 'label' => 'Docentes'],
            
        ];
    }
}
