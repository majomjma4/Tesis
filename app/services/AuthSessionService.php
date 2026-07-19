<?php
declare(strict_types=1);

/** Única fuente de identidad web; evita leer/escribir la sesión en cada módulo. */
final class AuthSessionService
{
    public function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    public function login(array $user): void
    {
        $this->start(); session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = (string) $user['full_name'];
        $_SESSION['user_email'] = (string) $user['email'];
        $_SESSION['roles'] = array_values(array_unique(array_map('strval', $user['roles'] ?? [])));
        $_SESSION['role'] = (string) ($_SESSION['roles'][0] ?? 'student');
        $_SESSION['authenticated_at'] = time();
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

    public function csrfToken(string $scope): string
    {
        $this->start(); $key = 'csrf_' . preg_replace('/[^a-z0-9_]/i', '_', $scope);
        if (!isset($_SESSION[$key])) $_SESSION[$key] = bin2hex(random_bytes(32));
        return (string) $_SESSION[$key];
    }

    public function validateCsrf(string $scope, string $token): bool { return hash_equals($this->csrfToken($scope), $token); }
}
