<?php
declare(strict_types=1);

final class PasswordResetModel
{
    private const COOLDOWN_SECONDS = 60;

    public static function tokenTtlMinutes(): int
    {
        $config = require APP_PATH . '/config/app.php';
        return max(1, (int) $config['password_reset_ttl_minutes']);
    }

    public static function isTokenExpired(array $row): bool
    {
        $now = time();
        $expiresAt = strtotime((string) ($row['expires_at'] ?? '') . ' UTC');
        $createdAt = strtotime((string) ($row['created_at'] ?? '') . ' UTC');

        return $expiresAt === false
            || $createdAt === false
            || $expiresAt <= $now
            || $createdAt + (self::tokenTtlMinutes() * 60) <= $now;
    }

    public function consumeIpRateLimit(string $ip): array
    {
        $ip = mb_substr(trim($ip), 0, 45);
        if ($ip === '') {
            $ip = 'unknown';
        }

        return Database::transaction(function (PDO $db) use ($ip): array {
            $db->exec("DELETE FROM password_recovery_rate_limits WHERE last_requested_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR");

            $insert = $db->prepare(
                'INSERT IGNORE INTO password_recovery_rate_limits
                 (ip_address, window_started_at, last_requested_at, request_count)
                 VALUES (:ip, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1)'
            );
            $insert->execute(['ip' => $ip]);
            $inserted = $insert->rowCount() === 1;

            $read = $db->prepare(
                'SELECT window_started_at, last_requested_at, request_count
                 FROM password_recovery_rate_limits
                 WHERE ip_address = :ip
                 FOR UPDATE'
            );
            $read->execute(['ip' => $ip]);
            $row = $read->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('No fue posible registrar el límite de recuperación.');
            }

            if ($inserted) {
                return ['allowed' => true, 'reason' => null, 'remaining_seconds' => 0];
            }

            $now = time();
            $lastRequestedAt = strtotime((string) $row['last_requested_at'] . ' UTC');
            $windowStartedAt = strtotime((string) $row['window_started_at'] . ' UTC');
            $elapsedSinceLastRequest = max(0, $now - $lastRequestedAt);

            if ($elapsedSinceLastRequest < self::COOLDOWN_SECONDS) {
                return [
                    'allowed' => false,
                    'reason' => 'cooldown',
                    'remaining_seconds' => max(1, self::COOLDOWN_SECONDS - $elapsedSinceLastRequest),
                ];
            }

            if (($now - $windowStartedAt) >= 3600) {
                $db->prepare(
                    'UPDATE password_recovery_rate_limits
                     SET window_started_at = UTC_TIMESTAMP(), last_requested_at = UTC_TIMESTAMP(), request_count = 1
                     WHERE ip_address = :ip'
                )->execute(['ip' => $ip]);

                return ['allowed' => true, 'reason' => null, 'remaining_seconds' => 0];
            }

            if ((int) $row['request_count'] >= 5) {
                return ['allowed' => false, 'reason' => 'hourly', 'remaining_seconds' => 0];
            }

            $db->prepare(
                'UPDATE password_recovery_rate_limits
                 SET last_requested_at = UTC_TIMESTAMP(), request_count = request_count + 1
                 WHERE ip_address = :ip'
            )->execute(['ip' => $ip]);

            return ['allowed' => true, 'reason' => null, 'remaining_seconds' => 0];
        });
    }

    public function isRateLimited(?int $userId, string $ip): bool
    {
        $db = Database::connection();
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::COOLDOWN_SECONDS);

        // 1. Rate limit por IP
        $stmt = $db->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE requested_ip = :ip AND created_at >= :cutoff');
        $stmt->execute(['ip' => $ip, 'cutoff' => $cutoff]);
        if ((int)$stmt->fetchColumn() > 0) {
            return true;
        }

        // 2. Rate limit por User ID (si existe)
        if ($userId !== null && $userId > 0) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = :user_id AND created_at >= :cutoff');
            $stmt->execute(['user_id' => $userId, 'cutoff' => $cutoff]);
            if ((int)$stmt->fetchColumn() > 0) {
                return true;
            }
        }

        return false;
    }

    public function createToken(int $userId, string $ip): array
    {
        $db = Database::connection();
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $ttl = self::tokenTtlMinutes();
        $expiresAt = gmdate('Y-m-d H:i:s', time() + ($ttl * 60));

        $stmt = $db->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, requested_ip) VALUES (:user_id, :token_hash, :expires_at, :requested_ip)');
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
            'requested_ip' => $ip
        ]);

        $tokenId = (int)$db->lastInsertId();

        return [
            'id' => $tokenId,
            'raw_token' => $token,
            'hash' => $hash
        ];
    }

    public function revokeToken(int $tokenId): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM password_reset_tokens WHERE id = :id');
        $stmt->execute(['id' => $tokenId]);
    }

    public function invalidatePreviousTokens(int $userId, int $currentTokenId): void
    {
        $db = Database::connection();
        // Marca como usados (o elimina) todos los tokens del usuario excepto el actual
        $stmt = $db->prepare('UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND id <> :current_id AND used_at IS NULL');
        $stmt->execute(['user_id' => $userId, 'current_id' => $currentTokenId]);
    }

    public function findValidToken(string $rawToken): ?array
    {
        $db = Database::connection();
        $hash = hash('sha256', $rawToken);

        $stmt = $db->prepare('SELECT pr.id, pr.user_id, pr.created_at, pr.expires_at, pr.used_at, u.must_change_password FROM password_reset_tokens pr LEFT JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = :hash LIMIT 1');
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // Verificar expiración y uso
        if (self::isTokenExpired($row) || $row['used_at'] !== null) {
            return null;
        }

        return $row;
    }

    /**
     * Consume el token de forma segura previniendo condiciones de carrera.
     */
    public function consumeToken(int $tokenId): bool
    {
        return Database::transaction(function (PDO $db) use ($tokenId): bool {
            // Locking con SELECT FOR UPDATE
            $stmt = $db->prepare('SELECT used_at, created_at, expires_at FROM password_reset_tokens WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $tokenId]);
            $row = $stmt->fetch();

            if (!$row || $row['used_at'] !== null || self::isTokenExpired($row)) {
                return false;
            }

            // Marcar como usado
            $update = $db->prepare('UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE id = :id');
            $update->execute(['id' => $tokenId]);

            return true;
        });
    }

    public function resolveUserByInstitutionalCode(string $code): array
    {
        $db = Database::connection();
        $code = AuthModel::normalizeLoginIdentifier($code);
        if ($code === '') {
            return [];
        }

        $sql = "
            SELECT u.id as user_id, u.email, u.status, u.deleted_at, u.purged_at, u.full_name, u.must_change_password
            FROM student_profiles sp
            INNER JOIN users u ON u.id = sp.user_id
            WHERE sp.institutional_code = :code1
            UNION
            SELECT u.id as user_id, u.email, u.status, u.deleted_at, u.purged_at, u.full_name, u.must_change_password
            FROM teacher_profiles tp
            INNER JOIN users u ON u.id = tp.user_id
            WHERE tp.institutional_code = :code2
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['code1' => $code, 'code2' => $code]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
