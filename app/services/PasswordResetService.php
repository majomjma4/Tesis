<?php
declare(strict_types=1);

final class PasswordResetService
{
    private PasswordResetModel $model;
    private MailService $mailer;

    public function __construct()
    {
        $this->model = new PasswordResetModel();
        $this->mailer = new MailService();
    }

    public function requestReset(string $institutionalCode, string $ip): string
    {
        $ipLimit = $this->model->consumeIpRateLimit($ip);
        if (!$ipLimit['allowed']) {
            if ($ipLimit['reason'] === 'hourly') {
                return 'rate_limited_hour';
            }

            return 'rate_limited:' . max(1, (int) $ipLimit['remaining_seconds']);
        }

        $normalizedCode = AuthModel::normalizeLoginIdentifier($institutionalCode);
        if ($normalizedCode === '' || !preg_match('/^\d{10}$/', $normalizedCode)) {
            // Aplicar rate limit por IP para evitar fuerza bruta en inputs inválidos
            return 'invalid';
        }

        // 1. Resolver usuario(s) por cédula
        $users = $this->model->resolveUserByInstitutionalCode($normalizedCode);

        // 2. Regla 0 / 1 / >1
        if (count($users) === 0) {
            // No existe la cédula: aplicar rate limit por IP
            return 'not_found';
        }

        if (count($users) > 1) {
            // Inconsistencia de datos en perfiles legacy: abortar de forma segura y registrar log
            error_log('PasswordResetService: cédula duplicada detectada; solicitud abortada por ambigüedad.');
            return 'duplicate';
        }

        $user = $users[0];
        $userId = (int)$user['user_id'];
        $email = trim((string)$user['email']);

        // 3. Control de Rate Limiting
        if ($this->model->isRateLimited($userId, $ip)) {
            return 'rate_limited:' . $this->rateLimitRemainingSeconds($userId, $ip);
        }

        // 4. Validaciones de estado de cuenta
        $status = (string)$user['status'];
        $deletedAt = $user['deleted_at'] ?? null;
        $purgedAt = $user['purged_at'] ?? null;

        if ($status !== 'active' || $deletedAt !== null || $purgedAt !== null) {
            return 'unavailable';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'no_email';
        }

        if ((bool) ($user['must_change_password'] ?? false)) {
            return 'temporary_password';
        }

        // 5. Generar y persistir el nuevo token
        $tokenData = $this->model->createToken($userId, $ip);

        // 6. Construir la URL segura desde configuración
        $config = require APP_PATH . '/config/app.php';
        $baseUrl = rtrim((string)($config['app_url'] ?? 'http://localhost/TESIS'), '/');
        $resetUrl = $baseUrl . '/index.php?page=reset-password&token=' . rawurlencode($tokenData['raw_token']);
        $ttl = PasswordResetModel::tokenTtlMinutes();

        // 7. Intentar envío SMTP al correo registrado
        $sent = $this->mailer->sendResetLink($email, (string)$user['full_name'], $resetUrl, $ttl);

        if (!$sent) {
            $this->model->revokeToken($tokenData['id']);
            error_log('PasswordResetService: envío SMTP fallido; token revocado.');
            return 'smtp_failed';
        } else {
            $this->model->invalidatePreviousTokens($userId, $tokenData['id']);
            return 'sent';
        }
    }

    private function rateLimitRemainingSeconds(int $userId, string $ip): int
    {
        $db = Database::connection();
        $statement = $db->prepare('SELECT MAX(created_at) FROM password_reset_tokens WHERE requested_ip = :ip OR user_id = :user_id');
        $statement->execute(['ip' => $ip, 'user_id' => $userId]);
        $createdAt = $statement->fetchColumn();
        if (!$createdAt) {
            return 1;
        }

        $cooldown = (int) (new ReflectionClass(PasswordResetModel::class))
            ->getReflectionConstant('COOLDOWN_SECONDS')
            ->getValue();
        $elapsed = time() - (int) strtotime((string) $createdAt . ' UTC');

        return max(1, $cooldown - $elapsed);
    }
}
