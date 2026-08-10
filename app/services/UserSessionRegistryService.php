<?php
declare(strict_types=1);

final class UserSessionRegistryService
{
    public function issue(int $userId, int $sessionVersion): string
    {
        if ($userId < 1 || $sessionVersion < 1) throw new InvalidArgumentException('La sesión no es válida.');
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $statement = Database::connection()->prepare(
            'INSERT INTO user_sessions (user_id,session_token_hash,session_version,ip_address,user_agent,device_label) VALUES (:user,:hash,:version,:ip,:agent,:device)'
        );
        $agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $statement->execute([
            'user' => $userId,
            'hash' => hash('sha256', $token),
            'version' => $sessionVersion,
            'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'agent' => $agent ?: null,
            'device' => $this->deviceLabel($agent),
        ]);
        return $token;
    }

    public function openIfNoActiveSession(int $userId, int $sessionVersion): array
    {
        return Database::transaction(function (PDO $db) use ($userId, $sessionVersion): array {
            $this->lockUser($db, $userId);
            $active = $this->activeSession($db, $userId, $sessionVersion, true);
            if ($active !== null) return ['token' => null, 'conflict' => $active];
            return ['token' => $this->insertSession($db, $userId, $sessionVersion), 'conflict' => null];
        });
    }

    public function replaceActiveSessionsAndOpen(int $userId, int $sessionVersion): string
    {
        return Database::transaction(function (PDO $db) use ($userId, $sessionVersion): string {
            $this->lockUser($db, $userId);
            $revoke = $db->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=:user AND session_version=:version AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())');
            $revoke->execute(['user' => $userId, 'version' => $sessionVersion]);
            if ($revoke->rowCount() > 0) {
                (new AdminActivityService($db))->record($userId, 'session_replaced', 'Sesión activa reemplazada', 'Seguridad', 'user', $userId, 'Cuenta personal', 'correct', []);
            }
            return $this->insertSession($db, $userId, $sessionVersion);
        });
    }

    public function isValid(int $userId, string $token, int $sessionVersion): bool
    {
        if ($userId < 1 || $sessionVersion < 1 || !$this->isTokenShapeValid($token)) return false;
        $parameters = ['user' => $userId, 'hash' => hash('sha256', $token), 'version' => $sessionVersion];
        $read = Database::connection()->prepare(
            'SELECT id FROM user_sessions WHERE user_id=:user AND session_token_hash=:hash AND session_version=:version AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) LIMIT 1'
        );
        $read->execute($parameters);
        $id = $read->fetchColumn();
        if ($id === false) return false;
        Database::connection()->prepare('UPDATE user_sessions SET last_activity_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $id]);
        return true;
    }

    public function validateActivity(int $userId, string $token, int $sessionVersion, bool $touchActivity): string
    {
        if ($userId < 1 || $sessionVersion < 1 || !$this->isTokenShapeValid($token)) return 'invalid';
        $minutes = (new SystemSettingModel())->sessionInactivityMinutes();
        return Database::transaction(function (PDO $db) use ($userId, $token, $sessionVersion, $touchActivity, $minutes): string {
            $query = 'SELECT id,last_activity_at FROM user_sessions WHERE user_id=:user AND session_token_hash=:hash AND session_version=:version AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) LIMIT 1 FOR UPDATE';
            $read = $db->prepare($query);
            $read->execute(['user' => $userId, 'hash' => hash('sha256', $token), 'version' => $sessionVersion]);
            $row = $read->fetch();
            if (!is_array($row)) return 'invalid';
            $expired = $db->prepare('SELECT :last <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$minutes.' MINUTE)');
            $expired->execute(['last' => $row['last_activity_at']]);
            if ((bool) $expired->fetchColumn()) {
                $db->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE id=:id AND revoked_at IS NULL')->execute(['id' => $row['id']]);
                (new AdminActivityService($db))->record($userId, 'session_expired_inactivity', 'Sesión expirada por inactividad', 'Seguridad', 'user', $userId, 'Cuenta personal', 'correct', []);
                return 'inactivity';
            }
            if ($touchActivity) $db->prepare('UPDATE user_sessions SET last_activity_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id' => $row['id']]);
            return 'valid';
        });
    }

    public function revoke(int $userId, string $token): void
    {
        if ($userId < 1 || !$this->isTokenShapeValid($token)) return;
        $statement = Database::connection()->prepare('UPDATE user_sessions SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()) WHERE user_id=:user AND session_token_hash=:hash');
        $statement->execute(['user' => $userId, 'hash' => hash('sha256', $token)]);
    }

    public function isRevoked(int $userId, string $token): bool
    {
        if ($userId < 1 || !$this->isTokenShapeValid($token)) return false;
        $statement = Database::connection()->prepare('SELECT 1 FROM user_sessions WHERE user_id=:user AND session_token_hash=:hash AND revoked_at IS NOT NULL LIMIT 1');
        $statement->execute(['user' => $userId, 'hash' => hash('sha256', $token)]);
        return (bool) $statement->fetchColumn();
    }

    public function synchronizeVersion(int $userId, string $token, int $sessionVersion): void
    {
        if ($userId < 1 || $sessionVersion < 1 || !$this->isTokenShapeValid($token)) return;
        $statement = Database::connection()->prepare('UPDATE user_sessions SET session_version=:version,last_activity_at=UTC_TIMESTAMP() WHERE user_id=:user AND session_token_hash=:hash AND revoked_at IS NULL');
        $statement->execute(['user' => $userId, 'hash' => hash('sha256', $token), 'version' => $sessionVersion]);
    }

    private function isTokenShapeValid(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/D', $token) === 1;
    }

    private function lockUser(PDO $db, int $userId): void
    {
        if ($userId < 1) throw new InvalidArgumentException('La sesión no es válida.');
        $lock = $db->prepare('SELECT id FROM users WHERE id=:id FOR UPDATE');
        $lock->execute(['id' => $userId]);
        if (!$lock->fetchColumn()) throw new RuntimeException('La cuenta no está disponible.');
    }

    private function activeSession(PDO $db, int $userId, int $sessionVersion, bool $forUpdate): ?array
    {
        $minutes=(new SystemSettingModel())->sessionInactivityMinutes();
        $db->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=:user AND session_version=:version AND revoked_at IS NULL AND last_activity_at<=DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$minutes.' MINUTE)')->execute(['user'=>$userId,'version'=>$sessionVersion]);
        $query = 'SELECT id,device_label FROM user_sessions WHERE user_id=:user AND session_version=:version AND revoked_at IS NULL AND last_activity_at>DATE_SUB(UTC_TIMESTAMP(), INTERVAL '.$minutes.' MINUTE) AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) ORDER BY last_activity_at DESC,id DESC LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = $db->prepare($query);
        $statement->execute(['user' => $userId, 'version' => $sessionVersion]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    private function insertSession(PDO $db, int $userId, int $sessionVersion): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $agent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $statement = $db->prepare('INSERT INTO user_sessions (user_id,session_token_hash,session_version,ip_address,user_agent,device_label) VALUES (:user,:hash,:version,:ip,:agent,:device)');
        $statement->execute(['user' => $userId, 'hash' => hash('sha256', $token), 'version' => $sessionVersion, 'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, 'agent' => $agent ?: null, 'device' => $this->deviceLabel($agent)]);
        return $token;
    }

    private function deviceLabel(string $agent): string
    {
        $browser = str_contains($agent, 'Edg/') ? 'Edge' : (str_contains($agent, 'Firefox/') ? 'Firefox' : (str_contains($agent, 'Chrome/') ? 'Chrome' : (str_contains($agent, 'Safari/') ? 'Safari' : 'Navegador')));
        $platform = str_contains($agent, 'Windows') ? 'Windows' : (str_contains($agent, 'Android') ? 'Android' : (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') ? 'iOS' : (str_contains($agent, 'Mac OS') ? 'macOS' : (str_contains($agent, 'Linux') ? 'Linux' : 'dispositivo desconocido'))));
        return $browser . ' en ' . $platform;
    }
}
