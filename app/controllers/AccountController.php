<?php
declare(strict_types=1);

final class AccountController
{
    public function profile(): void
    {
        $session = new AuthSessionService(); $model = new AuthModel(); $error = null; $success = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $session->start();
            $success = (string) ($_SESSION['profile_success_flash'] ?? '');
            unset($_SESSION['profile_success_flash']);
            if ($success === '') $success = null;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!$session->validateCsrf('profile', (string) ($_POST['_csrf'] ?? ''))) $error = 'La sesión del formulario venció.';
            else try {
                $hasAvatarUpload = isset($_FILES['avatar']) && (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                $removeAvatar = (string) ($_POST['avatar_remove'] ?? '') === '1';
                $avatarAction = $hasAvatarUpload ? 'replace' : ($removeAvatar ? 'remove' : 'none');
                $storage = new ProfileAvatarStorageService();
                $stored = null;
                $persisted = false;
                if ($hasAvatarUpload) {
                    $stored = $storage->store((array) $_FILES['avatar']);
                }
                try {
                    $result = $model->updateProfile(
                        (int) $session->userId(),
                        trim((string) ($_POST['full_name'] ?? '')),
                        mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
                        trim((string) ($_POST['username'] ?? '')),
                        (int) ($_POST['profile_version'] ?? 0),
                        $avatarAction,
                        $stored['path'] ?? null
                    );
                    $persisted = true;
                } catch (Throwable $exception) {
                    if (!$persisted && is_array($stored)) $storage->discard($stored);
                    throw $exception;
                }

                $identity = $model->sessionIdentity((int) $session->userId());
                if (!$identity) throw new RuntimeException('La cuenta dejó de estar disponible.');
                $session->refresh($identity);

                $previousAvatar = $result['previous_avatar_path'] ?? null;
                if ($avatarAction !== 'none' && is_string($previousAvatar) && $previousAvatar !== '' && !$storage->delete($previousAvatar)) {
                    error_log('Previous profile avatar cleanup failed for user ' . (int) $session->userId());
                }

                $changes = (array) ($result['changes'] ?? []);
                $avatarChanged = (bool) ($result['avatar_changed'] ?? false);
                $success = $changes === [] && !$avatarChanged ? 'No se detectaron cambios en el perfil.' : 'Cambios guardados correctamente.';
                $session->start();
                $_SESSION['profile_success_flash'] = $success;
                session_write_close();
                header('Location: ' . route('profile'), true, 303);
                exit;
            } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
            catch (RuntimeException $exception) {
                if ($exception->getMessage() === 'ACCOUNT_UNAVAILABLE') $error = 'Tu cuenta ya no se encuentra habilitada para realizar cambios.';
                elseif ($exception->getMessage() === 'PROFILE_VERSION_CONFLICT') $error = 'Tu perfil fue actualizado desde otra pestaña o sesión. Recarga la página antes de guardar tus cambios.';
                else { error_log('Profile update: '.$exception->getMessage()); $error = 'No fue posible actualizar el perfil.'; }
            }
            catch (Throwable $exception) { error_log('Profile update: '.$exception->getMessage()); $error = 'No fue posible actualizar el perfil.'; }
        }
        try { $profile = $model->profile((int) $session->userId()); }
        catch (Throwable) { $profile = ['full_name'=>$session->name(),'email'=>$session->email(),'created_at'=>null,'last_login_at'=>null,'password_changed_at'=>null,'roles'=>$session->roles()]; $error ??= 'No fue posible consultar todos los datos del perfil.'; }
        View::render('account/profile',['currentPage'=>'profile','title'=>$session->isAdminModeActive() ? 'Mi perfil | Administración' : 'Mi perfil | Gestión Documental Académica','bodyClass'=>'account-profile-page','pageStyles'=>[asset('css/account-profile.css')],'profile'=>$profile,'profileError'=>$error,'profileSuccess'=>$success,'profileCsrf'=>$session->csrfToken('profile')]);
    }

    public function changePassword(): void
    {
        $session = new AuthSessionService(); $model = new AuthModel(); $error = null; $success = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $current = (string) ($_POST['current_password'] ?? ''); $new = (string) ($_POST['new_password'] ?? ''); $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
            if (!$session->validateCsrf('change_password', (string) ($_POST['_csrf'] ?? ''))) $error = 'La sesión del formulario venció.';
            elseif ($new !== $confirmation) $error = 'Las contraseñas no coinciden.';
            elseif ($current === $new) $error = 'La nueva contraseña debe ser diferente de la actual.';
            else try {
                $model->assertPasswordPolicy($new);
                if (!$model->changePassword((int) $session->userId(), $current, $new)) $error = 'La contraseña actual no es correcta.';
                else { $identity = $model->sessionIdentity((int) $session->userId()); if (!$identity) throw new RuntimeException('La cuenta dejó de estar disponible.'); $session->refresh($identity); $success = 'Contraseña actualizada correctamente. Por seguridad, vuelve a iniciar sesión.'; }
            } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
            catch (Throwable $exception) { error_log('Password change: '.$exception->getMessage()); $error = 'No fue posible actualizar la contraseña.'; }
        }
        $forcedPasswordChange = $session->mustChangePassword() && !$session->hasAdminAccess();
        $passwordTitle = $session->isAdminModeActive() ? 'Cambiar contraseña | Administración' : ($session->isTeacher() ? 'Cambiar contraseña | Docente' : 'Cambiar contraseña');
        View::render('account/change-password',['currentPage'=>'profile','title'=>$passwordTitle,'bodyClass'=>'account-page','pageStyles'=>[asset('css/admin-access.css')],'passwordCsrfToken'=>$session->csrfToken('change_password'),'passwordError'=>$error,'passwordSuccess'=>$success,'forcedPasswordChange'=>$forcedPasswordChange]);
    }

    public function dismissTemporaryPasswordWarning(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->json(false, 'Método no permitido.', 405);
        $session = new AuthSessionService();
        if (!$session->validateCsrf('dismiss_temp_password_warning', (string) ($_POST['_csrf'] ?? ''))) $this->json(false, 'La sesión venció.', 419);
        $session->dismissTemporaryPasswordWarningToday();
        $this->json(true, 'Aviso descartado por hoy.');
    }

    public function updateAvatar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->json(false, 'Método no permitido.', 405);
        $session = new AuthSessionService();
        try { $userId = $this->authenticatedUserId($session); }
        catch (Throwable) { $this->json(false, 'La sesión no está activa.', 403); }
        if (!$session->validateCsrf('profile_avatar', (string) ($_POST['_csrf'] ?? ''))) $this->json(false, 'La sesión del formulario venció.', 403);

        $storage = new ProfileAvatarStorageService();
        $model = new AuthModel();
        $stored = [];
        $persisted = false;
        try {
            $upload = $_FILES['avatar'] ?? null;
            if (!is_array($upload)) throw new InvalidArgumentException('Selecciona una fotografía.');
            $stored = $storage->store($upload);
            $previous = $model->replaceAvatar($userId, (string) $stored['path']);
            $persisted = true;
            if ($previous !== null && !$storage->delete($previous)) error_log('Previous profile avatar cleanup failed for user ' . $userId);
            $identity = $model->sessionIdentity($userId);
            if (!$identity) throw new RuntimeException('La cuenta dejó de estar disponible.');
            $session->refresh($identity);
            $this->json(true, 'Fotografía de perfil actualizada correctamente.', 200, ['avatar_url' => $this->avatarUrl($identity['avatar_updated_at'] ?? null)]);
        } catch (InvalidArgumentException $exception) {
            if (!$persisted) $storage->discard($stored);
            $this->json(false, $exception->getMessage(), 422);
        } catch (Throwable $exception) {
            if (!$persisted) $storage->discard($stored);
            error_log('Profile avatar update: ' . $exception->getMessage());
            $this->json(false, 'No se pudo actualizar la fotografía. Inténtalo nuevamente.', 500);
        }
    }

    public function removeAvatar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->json(false, 'Método no permitido.', 405);
        $session = new AuthSessionService();
        try { $userId = $this->authenticatedUserId($session); }
        catch (Throwable) { $this->json(false, 'La sesión no está activa.', 403); }
        if (!$session->validateCsrf('profile_avatar', (string) ($_POST['_csrf'] ?? ''))) $this->json(false, 'La sesión del formulario venció.', 403);

        try {
            $previous = (new AuthModel())->removeAvatar($userId);
            if (!(new ProfileAvatarStorageService())->delete($previous)) error_log('Profile avatar file was unavailable during removal for user ' . $userId);
            $identity = (new AuthModel())->sessionIdentity($userId);
            if (!$identity) throw new RuntimeException('La cuenta dejó de estar disponible.');
            $session->refresh($identity);
            $this->json(true, 'Fotografía de perfil eliminada correctamente.', 200, ['avatar_url' => null]);
        } catch (InvalidArgumentException $exception) {
            $this->json(false, $exception->getMessage(), 422);
        } catch (Throwable $exception) {
            error_log('Profile avatar removal: ' . $exception->getMessage());
            $this->json(false, 'No se pudo eliminar la fotografía. Inténtalo nuevamente.', 500);
        }
    }

    public function avatar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); exit; }
        $session = new AuthSessionService();
        try { $userId = $this->authenticatedUserId($session); }
        catch (Throwable) { http_response_code(403); exit; }
        try {
            $path = (new AuthModel())->avatarPath($userId);
            if ($path === null) throw new RuntimeException('Avatar absent.');
            $file = (new ProfileAvatarStorageService())->resolve($path);
            header('Content-Type: ' . $file['mime']);
            header('Content-Length: ' . (string) filesize($file['path']));
            header('Content-Disposition: inline');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=3600');
            readfile($file['path']);
            exit;
        } catch (Throwable) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Fotografía no disponible.');
        }
    }

    public function forbidden(): void
    {
        http_response_code(403);
        View::render('errors/403', ['currentPage'=>'','title'=>'Acceso no autorizado | Gestión Académica','bodyClass'=>'error-page','pageStyles'=>[asset('css/admin-access.css')]], 'error');
    }

    private function avatarUrl(mixed $updatedAt): string
    {
        return route('profile-avatar') . '&v=' . rawurlencode((string) $updatedAt);
    }

    private function authenticatedUserId(AuthSessionService $session): int
    {
        $userId = (int) ($session->userId() ?? 0);
        if ($userId < 1) throw new RuntimeException('Inactive session.');
        $identity = (new AuthModel())->sessionIdentity($userId);
        if (!$identity || ($identity['status'] ?? '') !== 'active' || (int) ($identity['session_version'] ?? 0) !== $session->sessionVersion()) {
            throw new RuntimeException('Inactive session.');
        }
        return $userId;
    }

    public function toggleAdminMode(): void
    {
        $session = new AuthSessionService();
        if (!$session->isAuthenticated()) {
            header('Location: ' . route('login'));
            exit;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $session->validateCsrf('toggle_admin_mode', (string) ($_POST['_csrf'] ?? ''))) {
            $session->toggleAdminMode();
        }
        $returnUrl = (string) ($_POST['return_url'] ?? $_SERVER['HTTP_REFERER'] ?? route('dashboard'));
        if (!str_starts_with($returnUrl, '/') && !str_starts_with($returnUrl, route('dashboard'))) {
            $returnUrl = route('dashboard');
        }
        header('Location: ' . $returnUrl);
        exit;
    }

    private function json(bool $success, string $message, int $status = 200, array $data = []): never
    {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data], JSON_UNESCAPED_UNICODE); exit;
    }
}
