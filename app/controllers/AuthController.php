<?php

declare(strict_types=1);

final class AuthController
{
    public function login(): void
    {
        $auth = new AuthModel();
        $session = new AuthSessionService();
        if ($session->isAuthenticated()) { header('Location: ' . route('dashboard')); exit; }
        $error = null; $login = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $login = trim((string) ($_POST['user'] ?? ''));
            if (!$session->validateCsrf('login', (string) ($_POST['_csrf'] ?? ''))) $error = 'La sesión del formulario venció. Inténtalo nuevamente.';
            elseif (!Database::isEnabled()) $error = 'El acceso real estará disponible cuando se active la base de datos.';
            elseif (!filter_var($login, FILTER_VALIDATE_EMAIL) || (string) ($_POST['password'] ?? '') === '') $error = 'Completa un correo válido y la contraseña.';
            else {
                $user = $auth->findActiveUserByLogin($login);
                if ($user && password_verify((string) $_POST['password'], (string) $user['password_hash'])) {
                    $requiresTemporaryPasswordChange = (bool)$user['must_change_password']
                        && !(bool)($user['is_admin'] ?? false);
                    if ($requiresTemporaryPasswordChange) {
                        $user['password_warning_count'] = $auth->registerTemporaryPasswordWarning((int)$user['id']);
                    }
                    $session->login($user); $auth->recordLogin((int) $user['id']);
                    header('Location: ' . ($requiresTemporaryPasswordChange && ($user['password_warning_count'] ?? 0) >= 3 ? route('change-password') : route('dashboard'))); exit;
                }
                $error = 'Usuario o contraseña incorrectos.';
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
        ], 'auth');
    }

    public function logout(): void
    {
        (new AuthSessionService())->logout();
        header('Location: ' . route('login'));
        exit;
    }
}
