<?php
declare(strict_types=1);

final class AccountController
{
    public function profile(): void
    {
        $session = new AuthSessionService(); $model = new AuthModel(); $error = null; $success = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$session->validateCsrf('profile', (string) ($_POST['_csrf'] ?? ''))) $error = 'La sesión del formulario venció.';
            else try {
                $changes = $model->updateProfile((int) $session->userId(), trim((string) ($_POST['full_name'] ?? '')), mb_strtolower(trim((string) ($_POST['email'] ?? ''))), (string) ($_POST['current_password'] ?? ''));
                $identity = $model->sessionIdentity((int) $session->userId());
                if (!$identity) throw new RuntimeException('La cuenta dejó de estar disponible.');
                $session->refresh($identity);
                $success = $changes === [] ? 'No se detectaron cambios en el perfil.' : 'Perfil actualizado correctamente.';
            } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
            catch (Throwable $exception) { error_log('Profile update: '.$exception->getMessage()); $error = 'No fue posible actualizar el perfil.'; }
        }
        try { $profile = $model->profile((int) $session->userId()); }
        catch (Throwable) { $profile = ['full_name'=>$session->name(),'email'=>$session->email(),'created_at'=>null,'last_login_at'=>null,'password_changed_at'=>null,'roles'=>$session->roles()]; $error ??= 'No fue posible consultar todos los datos del perfil.'; }
        View::render('account/profile',['currentPage'=>'profile','title'=>'Mi perfil | Administración','bodyClass'=>'account-profile-page','pageStyles'=>[asset('css/account-profile.css')],'profile'=>$profile,'profileError'=>$error,'profileSuccess'=>$success,'profileCsrf'=>$session->csrfToken('profile')]);
    }

    public function changePassword(): void
    {
        $session = new AuthSessionService(); $model = new AuthModel(); $error = null; $success = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $current = (string) ($_POST['current_password'] ?? ''); $new = (string) ($_POST['new_password'] ?? ''); $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
            if (!$session->validateCsrf('change_password', (string) ($_POST['_csrf'] ?? ''))) $error = 'La sesión del formulario venció.';
            elseif ($new !== $confirmation) $error = 'La confirmación no coincide con la nueva contraseña.';
            elseif ($current === $new) $error = 'La nueva contraseña debe ser diferente a la actual.';
            else try {
                $model->assertPasswordPolicy($new);
                if (!$model->changePassword((int) $session->userId(), $current, $new)) $error = 'La contraseña actual no es correcta.';
                else { $identity = $model->sessionIdentity((int) $session->userId()); if (!$identity) throw new RuntimeException('La cuenta dejó de estar disponible.'); $session->refresh($identity); $success = 'Contraseña actualizada. Por seguridad, las demás sesiones dejarán de ser válidas.'; }
            } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
            catch (Throwable $exception) { error_log('Password change: '.$exception->getMessage()); $error = 'No fue posible actualizar la contraseña.'; }
        }
        $forcedPasswordChange = $session->mustChangePassword() && !$session->hasAdminAccess();
        View::render('account/change-password',['currentPage'=>'profile','title'=>'Cambiar contraseña | Administración','bodyClass'=>'account-page','pageStyles'=>[asset('css/admin-access.css')],'passwordCsrfToken'=>$session->csrfToken('change_password'),'passwordError'=>$error,'passwordSuccess'=>$success,'forcedPasswordChange'=>$forcedPasswordChange]);
    }

    public function dismissTemporaryPasswordWarning(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->json(false, 'Método no permitido.', 405);
        $session = new AuthSessionService();
        if (!$session->validateCsrf('dismiss_temp_password_warning', (string) ($_POST['_csrf'] ?? ''))) $this->json(false, 'La sesión venció.', 419);
        $session->dismissTemporaryPasswordWarningToday();
        $this->json(true, 'Aviso descartado por hoy.');
    }

    public function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', ['currentPage'=>'','title'=>'Acceso no autorizado | Gestión Académica','bodyClass'=>'error-page','pageStyles'=>[asset('css/admin-access.css')]], 'error');
    }

    private function json(bool $success, string $message, int $status = 200): never
    {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>$success,'message'=>$message], JSON_UNESCAPED_UNICODE); exit;
    }
}
