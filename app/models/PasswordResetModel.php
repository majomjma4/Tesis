<?php
declare(strict_types=1);

final class PasswordResetModel
{
    private const COOLDOWN_SECONDS = 60;

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

        $config = require APP_PATH . '/config/app.php';
        $ttl = (int)($config['password_reset_ttl_minutes'] ?? 60);
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

        $stmt = $db->prepare('SELECT id, user_id, expires_at, used_at FROM password_reset_tokens WHERE token_hash = :hash LIMIT 1');
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // Verificar expiración y uso
        $expired = strtotime((string)$row['expires_at'] . ' UTC') <= time();
        if ($expired || $row['used_at'] !== null) {
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
            $stmt = $db->prepare('SELECT used_at, expires_at FROM password_reset_tokens WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $tokenId]);
            $row = $stmt->fetch();

            if (!$row || $row['used_at'] !== null || strtotime((string)$row['expires_at'] . ' UTC') <= time()) {
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
        $code = trim($code);
        if ($code === '') {
            return [];
        }

        $sql = "
            SELECT u.id as user_id, u.email, u.status, u.deleted_at, u.full_name
            FROM student_profiles sp
            INNER JOIN users u ON u.id = sp.user_id
            WHERE sp.institutional_code = :code1
            UNION
            SELECT u.id as user_id, u.email, u.status, u.deleted_at, u.full_name
            FROM teacher_profiles tp
            INNER JOIN users u ON u.id = tp.user_id
            WHERE tp.institutional_code = :code2
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['code1' => $code, 'code2' => $code]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
