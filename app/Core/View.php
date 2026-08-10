<?php

declare(strict_types=1);

final class View
{
    // Inicio de renderizado de vistas
    // Valida archivos, incorpora datos globales y compone la vista dentro de su layout.
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        // Construye las rutas internas de la vista y del layout solicitado.
        $viewFile = APP_PATH . '/views/' . $view . '.php';
        $layoutFile = APP_PATH . '/views/layouts/' . $layout . '.php';

        if (!is_file($viewFile) || !is_file($layoutFile)) {
            http_response_code(500);
            echo 'La vista solicitada no existe.';
            return;
        }

        $data += self::institutionalData();
        if ($layout === 'main') {
            $data += self::notificationLayoutData();
        }

        extract($data, EXTR_SKIP);

        // Captura la vista como contenido para insertarla dentro del layout.
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require $layoutFile;
    }

    private static function institutionalData(): array
    {
        try {$name=(string)(new SystemSettingModel())->all()['institution_name'];}
        catch (Throwable) {$name=(string)(new SystemSettingModel())->defaults()['institution_name'];}
        return ['institutionName'=>$name];
    }
    // Final de renderizado de vistas

    // Inicio de datos globales de notificaciones
    // Mantiene contador, protección CSRF y endpoint disponibles en todas las pantallas principales.
    private static function notificationLayoutData(): array
    {
        $session = new AuthSessionService();
        $session->start();

        if (!isset($_SESSION['notification_csrf'])) {
            $_SESSION['notification_csrf'] = bin2hex(random_bytes(32));
        }

        try {
            $userId = (int) ($_SESSION['user_id'] ?? $_SESSION['notification_demo_user_id'] ?? 1);
            $unread = (new NotificationModel())->countUnread($userId);
        } catch (Throwable $exception) {
            if (!isset($_SESSION['notification_demo_items']) || !is_array($_SESSION['notification_demo_items'])) {
                $_SESSION['notification_demo_items'] = (new NotificationModel())->getDemoNotifications();
            }
            $unread = (new NotificationModel())->getDemoCounters($_SESSION['notification_demo_items'])['unread'];
        }

        return [
            'notificationUnreadCount' => $unread,
            'notificationCsrfToken' => (string) $_SESSION['notification_csrf'],
            'notificationOpenEndpoint' => route('notifications/open'),
            'layoutUserName' => (new AuthSessionService())->name(),
            'layoutUserEmail' => (new AuthSessionService())->email(),
            'layoutUserRoles' => (new AuthSessionService())->roles(),
            'layoutIsAdmin' => (new AuthSessionService())->hasAdminAccess(),
            'layoutIsInitialAdmin' => (new AuthSessionService())->isInitialAdmin(),
            'layoutPasswordWarningCount' => (new AuthSessionService())->passwordWarningCount(),
            'layoutMustChangePassword' => (new AuthSessionService())->mustChangePassword(),
            'layoutTemporaryPasswordRemainingDays' => (new AuthSessionService())->temporaryPasswordRemainingDays(),
            'layoutIsTemporaryPasswordDismissedToday' => (new AuthSessionService())->isTemporaryPasswordWarningDismissedToday(),
            'layoutTemporaryPasswordWarningCsrf' => $session->csrfToken('dismiss_temp_password_warning'),
        ];
    }
    // Final de datos globales de notificaciones
}
