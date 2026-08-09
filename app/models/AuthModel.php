<?php

declare(strict_types=1);

final class AuthModel
{
    public function profile(int $userId):array
    {
        $q=Database::connection()->prepare('SELECT id,full_name,email,created_at,last_login_at,password_changed_at,is_admin,is_initial_admin FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL');$q->execute(['id'=>$userId]);$profile=$q->fetch();if(!$profile)throw new RuntimeException('La cuenta no existe.');$profile['roles']=$this->effectiveRoles((int)$profile['id'],(bool)$profile['is_admin']);return $profile;
    }

    public function updateProfile(int $userId,string $name,string $email,string $password):void
    {
        if(mb_strlen($name)<3||mb_strlen($name)>180)throw new InvalidArgumentException('Ingresa tu nombre completo.');if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Ingresa un correo válido.');
        Database::transaction(function(PDO $db)use($userId,$name,$email,$password):void{$q=$db->prepare('SELECT full_name,email,password_hash FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');$q->execute(['id'=>$userId]);$before=$q->fetch();if(!$before||!password_verify($password,(string)$before['password_hash']))throw new InvalidArgumentException('La contraseña actual no es correcta.');$duplicate=$db->prepare('SELECT id FROM users WHERE email=:email AND id<>:id LIMIT 1');$duplicate->execute(['email'=>$email,'id'=>$userId]);if($duplicate->fetch())throw new InvalidArgumentException('Ese correo ya está asociado a otra cuenta.');$db->prepare('UPDATE users SET full_name=:name,email=:email,session_version=session_version+1 WHERE id=:id')->execute(['name'=>$name,'email'=>$email,'id'=>$userId]);$audit=$db->prepare("INSERT INTO admin_audit_log(actor_user_id,action,entity_type,entity_id,details) VALUES(:id,'profile_updated','user',:id2,:details)");$audit->execute(['id'=>$userId,'id2'=>$userId,'details'=>json_encode(['previous_name'=>$before['full_name'],'previous_email'=>$before['email'],'new_name'=>$name,'new_email'=>$email],JSON_UNESCAPED_UNICODE)]);});
    }

    public function findActiveUserByLogin(string $login): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, email, username, password_hash, full_name, is_admin, is_initial_admin, must_change_password, password_warning_count, temporary_password_expires_at, temporary_password_last_warning_at, session_version FROM users WHERE status = 'active' AND deleted_at IS NULL AND purged_at IS NULL AND email = :email_login LIMIT 1"
        );
        $normalizedLogin = mb_strtolower(trim($login));
        $statement->execute(['email_login' => $normalizedLogin]);
        $user = $statement->fetch();
        if (!$user) return null;
        $user['roles'] = $this->effectiveRoles((int)$user['id'],(bool)$user['is_admin']);
        return $user;
    }

    public function recordLogin(int $userId): void
    {
        $statement = Database::connection()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $userId]);
    }

    public function registerTemporaryPasswordWarning(int $userId): int
    {
        // Compatibilidad: la vigencia ahora se rige 100% por fecha (temporary_password_expires_at).
        $read = Database::connection()->prepare('SELECT password_warning_count FROM users WHERE id=:id'); $read->execute(['id'=>$userId]);
        return (int)($read->fetchColumn() ?: 0);
    }

    public function checkLoginLockout(string $login, string $ip): array
    {
        $db = Database::connection();
        $key = 'lockout:' . md5(mb_strtolower(trim($login)) . ':' . $ip);
        $q = $db->prepare('SELECT attempts, locked_until FROM login_security_locks WHERE lock_key = :key');
        $q->execute(['key' => $key]);
        $row = $q->fetch();
        if (!$row) return ['is_locked' => false, 'remaining_seconds' => 0];

        $lockedUntil = $row['locked_until'] ? strtotime((string)$row['locked_until']) : 0;
        $now = time();
        if ($lockedUntil > $now) {
            return ['is_locked' => true, 'remaining_seconds' => $lockedUntil - $now];
        }

        // Si ya pasó el tiempo de bloqueo, limpiar el registro si estaba bloqueado
        if ($lockedUntil > 0) {
            $db->prepare('DELETE FROM login_security_locks WHERE lock_key = :key')->execute(['key' => $key]);
        }
        return ['is_locked' => false, 'remaining_seconds' => 0];
    }

    public function recordFailedLogin(string $login, string $ip): array
    {
        $db = Database::connection();
        $key = 'lockout:' . md5(mb_strtolower(trim($login)) . ':' . $ip);
        $db->prepare(
            'INSERT INTO login_security_locks (lock_key, attempts, updated_at)
             VALUES (:key, 1, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP'
        )->execute(['key' => $key]);

        $q = $db->prepare('SELECT attempts FROM login_security_locks WHERE lock_key = :key');
        $q->execute(['key' => $key]);
        $attempts = (int) $q->fetchColumn();

        if ($attempts >= 11) {
            $lockedUntil = date('Y-m-d H:i:s', time() + 60);
            $db->prepare('UPDATE login_security_locks SET locked_until = :until WHERE lock_key = :key')
               ->execute(['until' => $lockedUntil, 'key' => $key]);
            return ['is_locked' => true, 'remaining_seconds' => 60, 'attempts' => $attempts];
        }

        return ['is_locked' => false, 'remaining_seconds' => 0, 'attempts' => $attempts];
    }

    public function clearFailedLogins(string $login, string $ip): void
    {
        $key = 'lockout:' . md5(mb_strtolower(trim($login)) . ':' . $ip);
        Database::connection()->prepare('DELETE FROM login_security_locks WHERE lock_key = :key')->execute(['key' => $key]);
    }

    public function sessionIdentity(int $userId): ?array
    {
        $statement=Database::connection()->prepare("SELECT id,email,full_name,status,is_admin,is_initial_admin,must_change_password,password_warning_count,temporary_password_expires_at,temporary_password_last_warning_at,session_version FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL LIMIT 1");
        $statement->execute(['id'=>$userId]); $user=$statement->fetch();
        if(!$user)return null;
        $user['roles']=$this->effectiveRoles($userId,(bool)$user['is_admin']);
        return $user;
    }

    public function recordWarningDismissedToday(int $userId): void
    {
        $statement = Database::connection()->prepare('UPDATE users SET temporary_password_last_warning_at = CURRENT_DATE WHERE id = :id');
        $statement->execute(['id' => $userId]);
    }

    private function effectiveRoles(int $userId,bool $isAdmin):array
    {
        $roles=Database::connection()->prepare('SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=:id ORDER BY r.id');
        $roles->execute(['id'=>$userId]);$result=array_column($roles->fetchAll(),'code');
        if($isAdmin&&!in_array('administrator',$result,true))$result[]='administrator';
        return array_values(array_unique($result));
    }

    public function changePassword(int $userId,string $current,string $new): bool
    {
        $read=Database::connection()->prepare('SELECT password_hash FROM users WHERE id=:id AND status=\'active\'');$read->execute(['id'=>$userId]);$hash=$read->fetchColumn();
        if(!$hash||!password_verify($current,(string)$hash))return false;
        $update=Database::connection()->prepare('UPDATE users SET password_hash=:hash,must_change_password=0,password_warning_count=0,temporary_password_expires_at=NULL,temporary_password_last_warning_at=NULL,password_changed_at=CURRENT_TIMESTAMP,session_version=session_version+1 WHERE id=:id');
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
