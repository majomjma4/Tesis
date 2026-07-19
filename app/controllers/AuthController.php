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
            elseif ($login === '' || (string) ($_POST['password'] ?? '') === '') $error = 'Completa el usuario y la contraseña.';
            else {
                $user = $auth->findActiveUserByLogin($login);
                if ($user && password_verify((string) $_POST['password'], (string) $user['password_hash'])) { $session->login($user); $auth->recordLogin((int) $user['id']); header('Location: ' . route('dashboard')); exit; }
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
