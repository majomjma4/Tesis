<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');

// Inicio de carga de configuración y dependencias
// Define la base de la aplicación e incorpora helpers y el cargador central de clases.
$config = require APP_PATH . '/config/app.php';

require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

if (($config['environment'] ?? 'production') === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
// Final de carga de configuración y dependencias

// Inicio del enrutamiento principal
// Resuelve los alias de cada pantalla y dirige la solicitud al controlador correspondiente.
$page = strtolower(trim((string) ($_GET['page'] ?? 'dashboard')));

match ($page) {
    'login' => (new AuthController())->login(),
    'logout' => (new AuthController())->logout(),
    'dev-reload' => (new DevController())->reloadStamp(),
    'dashboard', 'home', 'inicio' => (new DashboardController())->index(),
    'calendar', 'calendario' => (new CalendarController())->index(),
    'calendar-events' => (new CalendarController())->events(),
    'projects', 'proyectos', 'mis-proyectos' => (new ProjectsController())->index(),
    'repository', 'repositorio' => (new RepositoryController())->index(),
    'repository-detail', 'detalle-repositorio' => (new RepositoryController())->detail(),
    'repository-files' => (new RepositoryController())->files(),
    'repository-download' => (new RepositoryController())->downloadProject(),
    'repository-file-download' => (new RepositoryController())->downloadFile(),
    'repository-preview' => (new RepositoryController())->preview(),
    'repository-preview-content' => (new RepositoryController())->previewContent(),
    'repository-favorite' => (new RepositoryController())->toggleFavorite(),
    'support-materials' => (new SupportMaterialController())->index(),
    'support-material-detail' => (new SupportMaterialController())->detail(),
    'support-material-preview' => (new SupportMaterialController())->preview(),
    'support-material-preview-content' => (new SupportMaterialController())->previewContent(),
    'support-material-download' => (new SupportMaterialController())->download(),
    'notifications', 'notificaciones' => (new NotificationsController())->index(),
    'notifications/list' => (new NotificationsController())->listing(),
    'notifications/read' => (new NotificationsController())->markRead(),
    'notifications/unread' => (new NotificationsController())->markUnread(),
    'notifications/read-all' => (new NotificationsController())->markAllRead(),
    'notifications/delete' => (new NotificationsController())->delete(),
    'notifications/restore' => (new NotificationsController())->restore(),
    'notifications/destroy' => (new NotificationsController())->destroy(),
    'notifications/trash-empty' => (new NotificationsController())->emptyTrash(),
    'notifications/trash-bulk' => (new NotificationsController())->trashBulk(),
    'notifications/counters' => (new NotificationsController())->counters(),
    'notifications/open' => (new NotificationsController())->open(),
    default => (new DashboardController())->index(),
};
// Final del enrutamiento principal
