<?php
declare(strict_types=1);

final class RouteAccessService
{
    private const PUBLIC_ROUTES = ['login', 'logout', 'dev-reload'];
    public function enforce(string $page): void
    {
        $config = $GLOBALS['config'] ?? [];
        if (!($config['auth_required'] ?? false) || in_array($page, self::PUBLIC_ROUTES, true)) return;
        if (!(new AuthSessionService())->isAuthenticated()) { header('Location: ' . route('login')); exit; }
    }
}
