<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class MailService
{
    public function sendResetLink(string $toEmail, string $recipientName, string $resetUrl, int $ttlMinutes): bool
    {
        $config = require APP_PATH . '/config/app.php';

        $host = (string)($config['mail_host'] ?? 'smtp.gmail.com');
        $port = (int)($config['mail_port'] ?? 587);
        $encryption = (string)($config['mail_encryption'] ?? 'tls');
        $username = (string)($config['mail_username'] ?? '');
        $password = (string)($config['mail_password'] ?? '');
        $fromAddress = (string)($config['mail_from_address'] ?? '');
        $fromName = (string)($config['mail_from_name'] ?? 'Gestión Documental Académica');

        if ($username === '' || $password === '') {
            error_log('MailService Error: Credenciales de correo SMTP no configuradas.');
            return false;
        }

        try {
            if (!class_exists(PHPMailer::class)) {
                error_log('MailService Error: Dependencia PHPMailer no disponible.');
                return false;
            }

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->SMTPDebug = 0;
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;

            if (strtolower($encryption) === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (strtolower($encryption) === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
            }

            $mail->Port = $port;
            $mail->CharSet = 'UTF-8';

            // Remitente y Destinatario
            $mail->setFrom($fromAddress !== '' ? $fromAddress : $username, $fromName);
            $mail->addAddress($toEmail, $recipientName);

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = 'Restablece tu contraseña';

            // HTML Body template
            $escapedName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
            $escapedUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
            $mail->Body = "
                <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #1e3a8a; margin-bottom: 20px;'>Restablece tu contraseña</h2>
                    <p>Hola, <strong>{$escapedName}</strong>:</p>
                    <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta institucional.</p>
                    <p style='margin: 30px 0;'>
                        <a href='{$escapedUrl}' style='background-color: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Restablecer contraseña</a>
                    </p>
                    <p>Este enlace expirará en <strong>{$ttlMinutes} minutos</strong>.</p>
                    <p style='color: #64748b; font-size: 0.9em; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px;'>
                        Si no solicitaste este cambio, puedes ignorar este mensaje de forma segura.
                    </p>
                </div>
            ";

            // Plain text fallback
            $mail->AltBody = "Hola, {$recipientName}:\n\nRecibimos una solicitud para restablecer la contraseña de tu cuenta institucional. Haz clic en el siguiente enlace para restablecerla:\n\n{$resetUrl}\n\nEste enlace expirará en {$ttlMinutes} minutos.\n\nSi no solicitaste este cambio, puedes ignorar este mensaje.";

            $mail->send();
            return true;
        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());
            $cause = str_contains($message, 'authentication') || str_contains($message, 'auth')
                ? 'auth failed'
                : (str_contains($message, 'starttls') || str_contains($message, 'tls')
                    ? 'STARTTLS failed'
                    : (str_contains($message, 'timed out') || str_contains($message, 'timeout')
                        ? 'timeout'
                        : (str_contains($message, 'getaddrinfo') || str_contains($message, 'dns')
                            ? 'DNS'
                            : 'SMTP delivery failure')));
            error_log('MailService Delivery Error: ' . $cause);
            return false;
        }
    }
}
