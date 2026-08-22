<?php
declare(strict_types=1);

final class AuthController
{
    public function login(): void
    {
        $auth = new AuthModel();
        $session = new AuthSessionService();
        if ($session->isAuthenticated()) { header('Location: ' . route('dashboard')); exit; }
        if ($this->isAjaxOrJsonRequest()) {
            $notice = (string) ($_SESSION['auth_notice'] ?? '');
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'authenticated' => false, 'message' => $notice ?: 'La sesión no está activa.', 'data' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $error = (string) ($_SESSION['auth_notice'] ?? '') ?: null; unset($_SESSION['auth_notice']);
        $login = ''; $lockoutSeconds = 0; $challenge = $session->sessionReplacementChallenge();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $login = trim((string) ($_POST['user'] ?? ''));
            $lockState = $auth->checkLoginLockout($login, $ip);
            if ($lockState['is_locked']) {
                $lockoutSeconds = (int) $lockState['remaining_seconds'];
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
                    $auth->clearFailedLogins($login, $ip);
                    try {
                        $conflict = $session->login($user);
                        if ($conflict !== null) {
                            $session->beginSessionReplacementChallenge((int) $user['id'], (int) $user['session_version'], (string) ($conflict['device_label'] ?? 'Otro dispositivo'));
                            $challenge = $session->sessionReplacementChallenge();
                        } else {
                            $auth->recordLogin((int) $user['id']);
                            $this->redirectAfterLogin($user);
                        }
                    } catch (Throwable $exception) {
                        error_log('Login session registration: ' . $exception->getMessage());
                        $error = 'No fue posible iniciar sesión. Inténtalo nuevamente.';
                    }
                } else {
                    $failState = $auth->recordFailedLogin($login, $ip);
                    if ($failState['is_locked']) {
                        $lockoutSeconds = (int) $failState['remaining_seconds'];
                        $error = 'Demasiados intentos de inicio de sesión. Podrás intentarlo nuevamente en ' . sprintf('%02d:%02d', floor($lockoutSeconds / 60), $lockoutSeconds % 60) . '.';
                    } else $error = 'Correo electrónico o contraseña incorrectos.';
                }
            }
        }

        View::render('auth/login', ['title' => 'Iniciar sesión | Gestión Documental Académica','bodyClass' => 'login-page','pageScript' => asset('js/login.js'),'roles' => $auth->getAllowedRoles(),'loginCsrfToken' => $session->csrfToken('login'),'loginReplacementCsrfToken' => $session->csrfToken('login_session_replace'),'loginError' => $error,'loginValue' => $login,'lockoutSeconds' => $lockoutSeconds,'sessionReplacementChallenge' => $challenge], 'auth');
    }

    public function replaceActiveSession(): void
    {
        $session = new AuthSessionService();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !$session->validateCsrf('login_session_replace', (string) ($_POST['_csrf'] ?? ''))) {
            $this->returnToLogin($session, 'No fue posible confirmar el inicio de sesión. Inténtalo nuevamente.');
        }
        $challenge = $session->consumeSessionReplacementChallenge();
        if ($challenge === null) $this->returnToLogin($session, 'La confirmación de inicio de sesión venció. Inténtalo nuevamente.');
        $user = (new AuthModel())->sessionIdentity((int) $challenge['user_id']);
        if (!$user || (int) $user['session_version'] !== (int) $challenge['session_version']) $this->returnToLogin($session, 'La confirmación de inicio de sesión ya no es válida.');
        try {
            $session->loginReplacingActiveSession($user);
            (new AuthModel())->recordLogin((int) $user['id']);
            $this->redirectAfterLogin($user);
        } catch (Throwable $exception) {
            error_log('Session replacement: ' . $exception->getMessage());
            $this->returnToLogin($session, 'No fue posible reemplazar la sesión activa. Inténtalo nuevamente.');
        }
    }

    public function cancelSessionReplacement(): void
    {
        $session = new AuthSessionService();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !$session->validateCsrf('login_session_replace', (string) ($_POST['_csrf'] ?? ''))) {
            $this->returnToLogin($session, 'No fue posible cancelar el inicio de sesión.');
        }
        $session->clearSessionReplacementChallenge();
        $this->returnToLogin($session, 'Se mantuvo la sesión activa existente.');
    }

    public function logout(): void
    {
        (new AuthSessionService())->logout();
        header('Location: ' . route('login')); exit;
    }

    private function redirectAfterLogin(array $user): never
    {
        $requiresTemporaryPasswordChange = (bool) ($user['must_change_password'] ?? false) && !(bool) ($user['is_admin'] ?? false);
        $expired = !empty($user['temporary_password_expires_at']) && strtotime((string) $user['temporary_password_expires_at']) <= time();
        header('Location: ' . ($requiresTemporaryPasswordChange && $expired ? route('change-password') : route('dashboard'))); exit;
    }

    private function returnToLogin(AuthSessionService $session, string $message): never
    {
        $session->start(); $_SESSION['auth_notice'] = $message; session_write_close();
        header('Location: ' . route('login')); exit;
    }

    public function forgotPassword(): void
    {
        $session = new AuthSessionService();
        if ($session->isAuthenticated()) { header('Location: ' . route('dashboard')); exit; }

        header('Referrer-Policy: no-referrer');

        $error = null;
        $success = null;
        $code = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$session->validateCsrf('forgot_password', (string)($_POST['_csrf'] ?? ''))) {
                $error = 'La sesión del formulario venció. Inténtalo nuevamente.';
            } else {
                $code = trim((string)($_POST['institutional_code'] ?? ''));
                if (!preg_match('/^\d{10}$/', $code)) {
                    $error = 'La cédula debe contener exactamente 10 dígitos.';
                } else {
                    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
                    try {
                        (new PasswordResetService())->requestReset($code, $ip);
                        // Respuesta genérica anti-enumeración de usuarios
                        $success = 'Si los datos corresponden a una cuenta habilitada con un correo registrado, recibirás instrucciones para restablecer tu contraseña.';
                    } catch (Throwable $e) {
                        error_log('ForgotPassword Error: ' . $e->getMessage());
                        $error = 'Ocurrió un error al procesar tu solicitud. Inténtalo de nuevo más tarde.';
                    }
                }
            }
        }

        View::render('auth/forgot-password', [
            'title' => 'Recuperar contraseña | Gestión Documental Académica',
            'bodyClass' => 'login-page',
            'forgotCsrfToken' => $session->csrfToken('forgot_password'),
            'forgotError' => $error,
            'forgotSuccess' => $success,
            'codeValue' => $code
        ], 'auth');
    }

    public function resetPassword(): void
    {
        $session = new AuthSessionService();
        if ($session->isAuthenticated()) { header('Location: ' . route('dashboard')); exit; }

        header('Referrer-Policy: no-referrer');

        $token = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
        $model = new PasswordResetModel();

        // Validar token
        $tokenData = $model->findValidToken($token);
        $tokenError = null;

        if (!$tokenData) {
            $tokenError = 'El enlace de recuperación es inválido o ha expirado.';
        }

        $error = null;
        $success = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$tokenError) {
            if (!$session->validateCsrf('reset_password', (string)($_POST['_csrf'] ?? ''))) {
                $error = 'La sesión del formulario venció. Inténtalo nuevamente.';
            } else {
                $password = (string)($_POST['password'] ?? '');
                $confirm = (string)($_POST['confirm_password'] ?? '');

                if ($password === '' || $confirm === '') {
                    $error = 'Completa todos los campos.';
                } elseif ($password !== $confirm) {
                    $error = 'Las contraseñas no coinciden.';
                } else {
                    try {
                        // Consumir el token de forma segura previniendo condiciones de carrera
                        $consumed = $model->consumeToken((int)$tokenData['id']);
                        if (!$consumed) {
                            $tokenError = 'El enlace de recuperación ya ha sido utilizado o ha expirado.';
                        } else {
                            // Actualizar contraseña e invalidar sesiones concurrentes
                            (new AuthModel())->resetPasswordWithoutCurrent((int)$tokenData['user_id'], $password);
                            $success = 'Contraseña restablecida correctamente. Ya puedes iniciar sesión.';
                        }
                    } catch (InvalidArgumentException $e) {
                        $error = $e->getMessage();
                    } catch (Throwable $e) {
                        error_log('ResetPassword Error: ' . $e->getMessage());
                        $error = 'Ocurrió un error al restablecer la contraseña. Inténtalo de nuevo.';
                    }
                }
            }
        }

        View::render('auth/reset-password', [
            'title' => 'Establecer nueva contraseña | Gestión Documental Académica',
            'bodyClass' => 'login-page',
            'resetCsrfToken' => $session->csrfToken('reset_password'),
            'tokenValue' => $token,
            'tokenError' => $tokenError,
            'resetError' => $error,
            'resetSuccess' => $success
        ], 'auth');
    }

    private function isAjaxOrJsonRequest(): bool
    {
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }
}
