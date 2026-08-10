<?php
declare(strict_types=1);

/** Única fuente de identidad web; evita leer/escribir la sesión en cada módulo. */
final class AuthSessionService
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        ini_set('session.cookie_secure', $https ? '1' : '0');
        session_start();
    }

    public function login(array $user): void
    {
        $this->start(); session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = (string) $user['full_name'];
        $_SESSION['user_email'] = (string) $user['email'];
        $_SESSION['roles'] = array_values(array_unique(array_map('strval', $user['roles'] ?? [])));
        $_SESSION['role'] = (string) ($_SESSION['roles'][0] ?? 'student');
        $_SESSION['is_admin'] = (bool) ($user['is_admin'] ?? in_array('administrator', $_SESSION['roles'], true));
        $_SESSION['is_initial_admin'] = (bool) ($user['is_initial_admin'] ?? false);
        $_SESSION['authenticated_at'] = time();
        $_SESSION['session_version'] = (int) ($user['session_version'] ?? 1);
        $_SESSION['must_change_password'] = (bool) ($user['must_change_password'] ?? false);
        $_SESSION['password_warning_count'] = (int) ($user['password_warning_count'] ?? 0);
        $_SESSION['temporary_password_expires_at'] = $user['temporary_password_expires_at'] ?? null;
        $_SESSION['temporary_password_last_warning_at'] = $user['temporary_password_last_warning_at'] ?? null;
        $_SESSION['avatar_path'] = $user['avatar_path'] ?? null;
        $_SESSION['avatar_updated_at'] = $user['avatar_updated_at'] ?? null;
    }

    public function logout(): void
    {
        $this->start(); $_SESSION = [];
        if (ini_get('session.use_cookies')) { $params = session_get_cookie_params(); setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']); }
        session_destroy();
    }

    public function isAuthenticated(): bool { $this->start(); return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0; }
    public function userId(): ?int { return $this->isAuthenticated() ? (int) $_SESSION['user_id'] : null; }
    public function roles(): array { $this->start(); return array_values(array_filter(array_map('strval', (array) ($_SESSION['roles'] ?? [$_SESSION['role'] ?? ''])))); }
    public function hasAdminAccess(): bool { $this->start(); return (bool)($_SESSION['is_admin']??false); }
    public function isInitialAdmin(): bool { $this->start(); return (bool)($_SESSION['is_initial_admin']??false); }
    public function name(): string { $this->start(); return (string)($_SESSION['user_name']??'Usuario'); }
    public function email(): string { $this->start(); return (string)($_SESSION['user_email']??''); }
    public function sessionVersion(): int { $this->start(); return (int) ($_SESSION['session_version'] ?? 0); }
    public function avatarPath(): ?string { $this->start(); $path=$_SESSION['avatar_path']??null; return is_string($path)&&$path!==''?$path:null; }
    public function avatarUpdatedAt(): ?string { $this->start(); $value=$_SESSION['avatar_updated_at']??null; return is_string($value)&&$value!==''?$value:null; }
    public function passwordWarningCount(): int { $this->start(); return (int)($_SESSION['password_warning_count']??0); }
    public function mustChangePassword(): bool { $this->start(); return (bool)($_SESSION['must_change_password']??false); }
    public function temporaryPasswordExpiresAt(): ?string { $this->start(); return isset($_SESSION['temporary_password_expires_at']) ? (string)$_SESSION['temporary_password_expires_at'] : null; }
    public function temporaryPasswordRemainingDays(): ?int
    {
        $expires = $this->temporaryPasswordExpiresAt();
        if (!$expires) return null;
        $diff = strtotime($expires) - time();
        if ($diff <= 0) return 0;
        return (int) ceil($diff / 86400);
    }
    public function isTemporaryPasswordWarningDismissedToday(): bool
    {
        $this->start();
        $today = date('Y-m-d');
        $dbDate = $_SESSION['temporary_password_last_warning_at'] ?? null;
        $sessDate = $_SESSION['temp_pass_dismissed_date'] ?? null;
        return $dbDate === $today || $sessDate === $today;
    }
    public function dismissTemporaryPasswordWarningToday(): void
    {
        $this->start();
        $today = date('Y-m-d');
        $_SESSION['temp_pass_dismissed_date'] = $today;
        $_SESSION['temporary_password_last_warning_at'] = $today;
        $userId = $this->userId();
        if ($userId) {
            (new AuthModel())->recordWarningDismissedToday($userId);
        }
    }
    public function refresh(array $identity): void
    {
        $this->start(); $_SESSION['user_name']=(string)$identity['full_name'];$_SESSION['user_email']=(string)$identity['email'];$_SESSION['roles']=$identity['roles'];$_SESSION['role']=(string)($identity['roles'][0]??'student');$_SESSION['is_admin']=(bool)($identity['is_admin']??false);$_SESSION['is_initial_admin']=(bool)($identity['is_initial_admin']??false);$_SESSION['session_version']=(int)$identity['session_version'];$_SESSION['must_change_password']=(bool)$identity['must_change_password'];$_SESSION['password_warning_count']=(int)$identity['password_warning_count'];$_SESSION['temporary_password_expires_at']=$identity['temporary_password_expires_at'];$_SESSION['temporary_password_last_warning_at']=$identity['temporary_password_last_warning_at']??$_SESSION['temporary_password_last_warning_at']??null;$_SESSION['avatar_path']=$identity['avatar_path']??null;$_SESSION['avatar_updated_at']=$identity['avatar_updated_at']??null;
    }

    public function csrfToken(string $scope): string
    {
        $this->start(); $key = 'csrf_' . preg_replace('/[^a-z0-9_]/i', '_', $scope);
        if (!isset($_SESSION[$key])) $_SESSION[$key] = bin2hex(random_bytes(32));
        return (string) $_SESSION[$key];
    }

    public function validateCsrf(string $scope, string $token): bool { return hash_equals($this->csrfToken($scope), $token); }
}
