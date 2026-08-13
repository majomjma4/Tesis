<?php

declare(strict_types=1);

final class AuthModel
{
    public function profile(int $userId):array
    {
        $q=Database::connection()->prepare("SELECT u.id,u.username,u.full_name,u.email,u.created_at,u.last_login_at,u.password_changed_at,u.avatar_path,u.avatar_updated_at,u.is_admin,u.is_initial_admin,sp.institutional_code student_institutional_code,tp.institutional_code teacher_institutional_code,se.semester current_semester FROM users u LEFT JOIN student_profiles sp ON sp.user_id=u.id LEFT JOIN teacher_profiles tp ON tp.user_id=u.id LEFT JOIN (SELECT id FROM academic_periods WHERE status='active' ORDER BY starts_on DESC,id DESC LIMIT 1) ap ON 1=1 LEFT JOIN student_enrollments se ON se.student_id=u.id AND se.academic_period_id=ap.id AND se.status='active' WHERE u.id=:id AND u.deleted_at IS NULL AND u.purged_at IS NULL");$q->execute(['id'=>$userId]);$profile=$q->fetch();if(!$profile)throw new RuntimeException('La cuenta no existe.');$profile['roles']=$this->effectiveRoles((int)$profile['id'],(bool)$profile['is_admin']);$profile['institutional_code']=in_array('student',$profile['roles'],true)?($profile['student_institutional_code']??null):(in_array('teacher',$profile['roles'],true)?($profile['teacher_institutional_code']??null):null);return $profile;
    }

    public function updateProfile(int $userId,string $name,string $email,string $username,string $password): array
    {
        if(mb_strlen($name)<3||mb_strlen($name)>180)throw new InvalidArgumentException('Ingresa tu nombre completo.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Ingresa un correo válido.');
        $username=trim($username);
        if($username!==''&&!preg_match('/^[a-zA-Z0-9._-]{3,80}$/',$username))throw new InvalidArgumentException('El usuario debe tener entre 3 y 80 caracteres (letras, números, punto, guion o guion bajo).');
        return Database::transaction(function(PDO $db)use($userId,$name,$email,$username,$password):array{
            $q=$db->prepare('SELECT username,full_name,email,password_hash FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');
            $q->execute(['id'=>$userId]);
            $before=$q->fetch();
            if(!$before||!password_verify($password,(string)$before['password_hash']))throw new InvalidArgumentException('La contraseña actual no es correcta.');
            $changes=[];
            if($before['full_name']!==$name)$changes['full_name']=['from'=>$before['full_name'],'to'=>$name];
            if($before['email']!==$email){
                $duplicate=$db->prepare('SELECT id FROM users WHERE email=:email AND id<>:id LIMIT 1');
                $duplicate->execute(['email'=>$email,'id'=>$userId]);
                if($duplicate->fetch())throw new InvalidArgumentException('Ya existe una cuenta registrada con ese correo.');
                $changes['email']=['from'=>$before['email'],'to'=>$email];
            }
            $currentUsername=(string)($before['username']??'');
            if($currentUsername!==$username){
                if($username!==''){
                    $dupUser=$db->prepare('SELECT id FROM users WHERE username=:u AND id<>:id LIMIT 1');
                    $dupUser->execute(['u'=>$username,'id'=>$userId]);
                    if($dupUser->fetch())throw new InvalidArgumentException('Ese nombre de usuario ya está registrado.');
                }
                $changes['username']=['from'=>$currentUsername,'to'=>$username];
            }
            if($changes===[])return [];
            $db->prepare('UPDATE users SET full_name=:name,email=:email,username=:username,session_version=session_version+1 WHERE id=:id')
                ->execute(['name'=>$name,'email'=>$email,'username'=>$username!==''?$username:null,'id'=>$userId]);

            $hasName = isset($changes['full_name']);
            $hasEmail = isset($changes['email']);
            $hasUsername = isset($changes['username']);

            if ($hasName && !$hasEmail && !$hasUsername) {
                $action = 'profile_name_changed';
                $actionLabel = 'Nombre del perfil actualizado';
            } elseif ($hasEmail && !$hasName && !$hasUsername) {
                $action = 'profile_email_changed';
                $actionLabel = 'Correo del perfil actualizado';
            } else {
                $action = 'profile_updated';
                $actionLabel = 'Perfil actualizado';
            }

            (new AdminActivityService($db))->record($userId, $action, $actionLabel, 'Cuenta', 'user', $userId, 'Cuenta personal', 'correct', ['changed_fields' => array_keys($changes)]);
            return $changes;
        });
    }

    public function findActiveUserByLogin(string $login): ?array
    {
        $statement = Database::connection()->prepare(
            "SELECT id, email, username, password_hash, full_name, is_admin, is_initial_admin, must_change_password, password_warning_count, temporary_password_expires_at, temporary_password_last_warning_at, session_version, avatar_path, avatar_updated_at FROM users WHERE status = 'active' AND deleted_at IS NULL AND purged_at IS NULL AND email = :email_login LIMIT 1"
        );
        $normalizedLogin = mb_strtolower(trim($login));
        $statement->execute(['email_login' => $normalizedLogin]);
        $user = $statement->fetch();
        if (!$user) return null;
        $user['roles'] = $this->effectiveRoles((int)$user['id'],(bool)$user['is_admin']);
        if ($this->isGraduatedStudentOnly((int) $user['id'], $user['roles'])) return null;
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
        $statement=Database::connection()->prepare("SELECT id,email,full_name,status,is_admin,is_initial_admin,must_change_password,password_warning_count,temporary_password_expires_at,temporary_password_last_warning_at,session_version,avatar_path,avatar_updated_at FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL LIMIT 1");
        $statement->execute(['id'=>$userId]); $user=$statement->fetch();
        if(!$user)return null;
        $user['roles']=$this->effectiveRoles($userId,(bool)$user['is_admin']);
        if($this->isGraduatedStudentOnly($userId,$user['roles']))return null;
        return $user;
    }

    public function recordWarningDismissedToday(int $userId): void
    {
        $statement = Database::connection()->prepare('UPDATE users SET temporary_password_last_warning_at = CURRENT_DATE WHERE id = :id');
        $statement->execute(['id' => $userId]);
    }

    public function replaceAvatar(int $userId, string $avatarPath): ?string
    {
        return Database::transaction(function (PDO $db) use ($userId, $avatarPath): ?string {
            $read = $db->prepare('SELECT avatar_path FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');
            $read->execute(['id' => $userId]);
            $previous = $read->fetchColumn();
            if ($previous === false) throw new RuntimeException('La cuenta no está disponible.');
            $db->prepare('UPDATE users SET avatar_path=:path,avatar_updated_at=UTC_TIMESTAMP() WHERE id=:id')
                ->execute(['path' => $avatarPath, 'id' => $userId]);
            (new AdminActivityService($db))->record($userId, 'avatar_updated', 'Fotografía de perfil actualizada', 'Cuenta', 'user', $userId, 'Cuenta personal', 'correct', []);
            return $previous !== null ? (string) $previous : null;
        });
    }

    public function removeAvatar(int $userId): string
    {
        return Database::transaction(function (PDO $db) use ($userId): string {
            $read = $db->prepare('SELECT avatar_path FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');
            $read->execute(['id' => $userId]);
            $previous = $read->fetchColumn();
            if ($previous === false) throw new RuntimeException('La cuenta no está disponible.');
            if ($previous === null || $previous === '') throw new InvalidArgumentException('No existe una fotografía personalizada para eliminar.');
            $db->prepare('UPDATE users SET avatar_path=NULL,avatar_updated_at=NULL WHERE id=:id')->execute(['id' => $userId]);
            (new AdminActivityService($db))->record($userId, 'avatar_removed', 'Fotografía de perfil eliminada', 'Cuenta', 'user', $userId, 'Cuenta personal', 'correct', []);
            return (string) $previous;
        });
    }

    public function avatarPath(int $userId): ?string
    {
        $read = Database::connection()->prepare('SELECT avatar_path FROM users WHERE id=:id AND deleted_at IS NULL AND purged_at IS NULL LIMIT 1');
        $read->execute(['id' => $userId]);
        $path = $read->fetchColumn();
        return $path === false || $path === null || $path === '' ? null : (string) $path;
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
        $this->assertPasswordPolicy($new);
        return Database::transaction(function(PDO $db)use($userId,$current,$new):bool{$read=$db->prepare('SELECT password_hash FROM users WHERE id=:id AND status=\'active\' AND deleted_at IS NULL AND purged_at IS NULL FOR UPDATE');$read->execute(['id'=>$userId]);$hash=$read->fetchColumn();if(!$hash||!password_verify($current,(string)$hash))return false;$update=$db->prepare('UPDATE users SET password_hash=:hash,must_change_password=0,password_warning_count=0,temporary_password_expires_at=NULL,temporary_password_last_warning_at=NULL,password_changed_at=CURRENT_TIMESTAMP,session_version=session_version+1 WHERE id=:id');$update->execute(['hash'=>password_hash($new,PASSWORD_DEFAULT),'id'=>$userId]);(new AdminActivityService($db))->record($userId,'password_changed','Contraseña actualizada','Cuenta','user',$userId,'Cuenta personal','correct',[]);return true;});
    }

    public function assertPasswordPolicy(string $password): void
    {
        if(mb_strlen($password,'UTF-8')<8||!preg_match('/[A-Z]/',$password)||!preg_match('/[a-z]/',$password)||!preg_match('/\d/',$password)||!preg_match('/[^A-Za-z0-9]/',$password))throw new InvalidArgumentException('La nueva contraseña debe tener al menos 8 caracteres e incluir mayúscula, minúscula, número y símbolo.');
    }

    private function isGraduatedStudentOnly(int $userId,array $roles): bool
    {
        $roles=array_values(array_unique(array_map('strval',$roles)));
        if($roles!==['student'])return false;
        $statement=Database::connection()->prepare("SELECT 1 FROM student_enrollments WHERE student_id=:id AND status='graduated' LIMIT 1");
        $statement->execute(['id'=>$userId]);return (bool)$statement->fetchColumn();
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
