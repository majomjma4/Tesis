<?php

declare(strict_types=1);

final class AuthController
{
    public function login(): void
    {
        $auth = new AuthModel();
        $session = new AuthSessionService();
        if ($session->isAuthenticated()) { header('Location: ' . route('dashboard')); exit; }
        $error = null; $login = ''; $lockoutSeconds = 0;
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $login = trim((string) ($_POST['user'] ?? ''));

            // Verificar bloqueo activo antes de procesar
            $lockState = $auth->checkLoginLockout($login, $ip);
            if ($lockState['is_locked']) {
                $lockoutSeconds = (int)$lockState['remaining_seconds'];
                $error = 'Demasiados intentos de inicio de sesión. Podrás intentarlo nuevamente en ' . sprintf('%02d:%02d', floor($lockoutSeconds / 60), $lockoutSeconds % 60) . '.';
            } elseif (!$session->validateCsrf('login', (string) ($_POST['_csrf'] ?? ''))) {
                $error = 'La sesión del formulario venció. Inténtalo nuevamente.';
            } elseif (!Database::isEnabled()) {
                $error = 'El acceso real estará disponible cuando se active la base de datos.';
            } elseif (!filter_var($login, FILTER_VALIDATE_EMAIL) || (string) ($_POST['password'] ?? '') === '') {
                $error = 'Completa un correo válido y la contraseña.';
            } else {
                $user = $auth->findActiveUserByLogin($login);
                if ($user && password_verify((string) $_POST['password'], (string) $user['password_hash'])) {
                    // Limpiar intentos fallidos acumulados en login exitoso
                    $auth->clearFailedLogins($login, $ip);

                    $requiresTemporaryPasswordChange = (bool)$user['must_change_password']
                        && !(bool)($user['is_admin'] ?? false);
                    $expired = !empty($user['temporary_password_expires_at']) && strtotime((string)$user['temporary_password_expires_at']) <= time();

                    $session->login($user); $auth->recordLogin((int) $user['id']);

                    // Redirigir a change-password SOLO si expiró la fecha límite
                    header('Location: ' . ($requiresTemporaryPasswordChange && $expired ? route('change-password') : route('dashboard'))); exit;
                }

                // Registrar intento fallido
                $failState = $auth->recordFailedLogin($login, $ip);
                if ($failState['is_locked']) {
                    $lockoutSeconds = (int)$failState['remaining_seconds'];
                    $error = 'Demasiados intentos de inicio de sesión. Podrás intentarlo nuevamente en ' . sprintf('%02d:%02d', floor($lockoutSeconds / 60), $lockoutSeconds % 60) . '.';
                } else {
                    $error = 'Correo electrónico o contraseña incorrectos.';
                }
            }
        }

        View::render('auth/login', [
            'title' => 'Iniciar sesion | Gestion Documental Academica',
            'bodyClass' => 'login-page',
            'pageScript' => asset('js/login.js'),
            'roles' => $auth->getAllowedRoles(),
            'loginCsrfToken' => $session->csrfToken('login'),
            'loginError' => $error,
            'loginValue' => $login,
            'lockoutSeconds' => $lockoutSeconds,
        ], 'auth');
    }

    public function logout(): void
    {
        (new AuthSessionService())->logout();
        header('Location: ' . route('login'));
        exit;
    }
}
