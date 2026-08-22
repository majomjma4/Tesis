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

    public function requestReset(string $institutionalCode, string $ip): void
    {
        $normalizedCode = trim($institutionalCode);
        if ($normalizedCode === '' || !preg_match('/^\d{10}$/', $normalizedCode)) {
            // Aplicar rate limit por IP para evitar fuerza bruta en inputs inválidos
            $this->model->isRateLimited(null, $ip);
            return;
        }

        // 1. Resolver usuario(s) por cédula
        $users = $this->model->resolveUserByInstitutionalCode($normalizedCode);

        // 2. Regla 0 / 1 / >1
        if (count($users) === 0) {
            // No existe la cédula: aplicar rate limit por IP
            $this->model->isRateLimited(null, $ip);
            return;
        }

        if (count($users) > 1) {
            // Inconsistencia de datos en perfiles legacy: abortar de forma segura y registrar log
            error_log("PasswordResetService: Cédula duplicada QA/Legacy detectada: {$normalizedCode}. Solicitud abortada por ambigüedad.");
            $this->model->isRateLimited(null, $ip);
            return;
        }

        $user = $users[0];
        $userId = (int)$user['user_id'];
        $email = trim((string)$user['email']);

        // 3. Control de Rate Limiting
        if ($this->model->isRateLimited($userId, $ip)) {
            return;
        }

        // 4. Validaciones de estado de cuenta
        $status = (string)$user['status'];
        $deletedAt = $user['deleted_at'] ?? null;

        if ($status !== 'active' || $deletedAt !== null || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Evitar continuar (manteniendo la respuesta genérica para evitar enumeración)
            return;
        }

        // 5. Generar y persistir el nuevo token
        $tokenData = $this->model->createToken($userId, $ip);

        // 6. Construir la URL segura desde configuración
        $config = require APP_PATH . '/config/app.php';
        $baseUrl = rtrim((string)($config['app_url'] ?? 'http://localhost/TESIS'), '/');
        $resetUrl = $baseUrl . '/index.php?page=reset-password&token=' . rawurlencode($tokenData['raw_token']);
        $ttl = (int)($config['password_reset_ttl_minutes'] ?? 60);

        // 7. Intentar envío SMTP al correo registrado
        $sent = $this->mailer->sendResetLink($email, (string)$user['full_name'], $resetUrl, $ttl);

        if (!$sent) {
            $this->model->revokeToken($tokenData['id']);
            error_log("PasswordResetService: No se pudo enviar el correo a {$email}, token revocado.");
        } else {
            $this->model->invalidatePreviousTokens($userId, $tokenData['id']);
        }
    }
}
