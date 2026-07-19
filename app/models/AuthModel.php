<?php

declare(strict_types=1);

final class AuthModel
{
    public function findActiveUserByLogin(string $login): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, email, username, password_hash, full_name, must_change_password, password_warning_count, temporary_password_expires_at, session_version FROM users WHERE status = 'active' AND email = :email_login LIMIT 1"
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

    public function registerTemporaryPasswordWarning(int $userId): int
    {
        $statement = Database::connection()->prepare('UPDATE users SET password_warning_count=LEAST(password_warning_count+1,3) WHERE id=:id AND must_change_password=1');
        $statement->execute(['id'=>$userId]);
        $read = Database::connection()->prepare('SELECT password_warning_count FROM users WHERE id=:id'); $read->execute(['id'=>$userId]);
        return (int)$read->fetchColumn();
    }

    public function sessionIdentity(int $userId): ?array
    {
        $statement=Database::connection()->prepare("SELECT id,email,full_name,status,must_change_password,password_warning_count,temporary_password_expires_at,session_version FROM users WHERE id=:id LIMIT 1");
        $statement->execute(['id'=>$userId]); $user=$statement->fetch();
        if(!$user)return null;
        $roles=Database::connection()->prepare('SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=:id');$roles->execute(['id'=>$userId]);$user['roles']=array_column($roles->fetchAll(),'code');
        return $user;
    }

    public function changePassword(int $userId,string $current,string $new): bool
    {
        $read=Database::connection()->prepare('SELECT password_hash FROM users WHERE id=:id AND status=\'active\'');$read->execute(['id'=>$userId]);$hash=$read->fetchColumn();
        if(!$hash||!password_verify($current,(string)$hash))return false;
        $update=Database::connection()->prepare('UPDATE users SET password_hash=:hash,must_change_password=0,password_warning_count=0,temporary_password_expires_at=NULL,password_changed_at=CURRENT_TIMESTAMP,session_version=session_version+1 WHERE id=:id');
        $update->execute(['hash'=>password_hash($new,PASSWORD_DEFAULT),'id'=>$userId]);return true;
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
