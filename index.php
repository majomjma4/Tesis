<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');

// Inicio de carga de configuración y dependencias
// Define la base de la aplicación e incorpora helpers y el cargador central de clases.
$config = require APP_PATH . '/config/app.php';
$timezone = (string) ($config['timezone'] ?? 'America/Guayaquil');
if (!in_array($timezone, timezone_identifiers_list(), true)) $timezone = 'America/Guayaquil';
date_default_timezone_set($timezone);

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
(new RouteAccessService())->enforce($page);

match ($page) {
    'login' => (new AuthController())->login(),
    'login-session-replace' => (new AuthController())->replaceActiveSession(),
    'login-session-replace-cancel' => (new AuthController())->cancelSessionReplacement(),
    'logout' => (new AuthController())->logout(),
    'change-password' => (new AccountController())->changePassword(),
    'dismiss-temp-password-warning' => (new AccountController())->dismissTemporaryPasswordWarning(),
    'profile-avatar-update' => (new AccountController())->updateAvatar(),
    'profile-avatar-remove' => (new AccountController())->removeAvatar(),
    'profile-avatar' => (new AccountController())->avatar(),
    'profile' => (new AccountController())->profile(),
    'forbidden' => (new AccountController())->forbidden(),
    'admin-users' => (new AdminController())->users(),
    'admin-user-save' => (new AdminController())->saveUser(),
    'admin-user-status' => (new AdminController())->changeUserStatus(),
    'admin-user-password' => (new AdminController())->resetUserPassword(),
    'admin-users-import' => (new AdminController())->importUsers(),
    'admin-project-save' => (new AdminController())->saveProject(),
    'admin-project-trash' => (new AdminController())->trashProject(),
    'admin-project-history' => (new AdminController())->projectHistory(),
    'admin-academic-save' => (new AdminController())->saveAcademic(),
    'admin-academic-promote' => (new AdminController())->promoteAcademicPeriod(),
    'admin-academic-revert' => (new AdminController())->revertAcademicPeriod(),
    'admin-repository-publish' => (new AdminController())->publishProject(),
    'admin-repository-trash' => (new AdminController())->trashRepositoryProject(),
    'admin-support-material-save' => (new AdminController())->saveSupportMaterial(),
    'admin-support-material-history' => (new AdminController())->supportMaterialHistory(),
    'admin-support-material-history-cleanup' => (new AdminController())->cleanupSupportMaterialHistory(),
    'admin-support-material-status' => (new AdminController())->changeSupportMaterialStatus(),
    'admin-support-material-file' => (new AdminController())->changeSupportMaterialFile(),
    'admin-notification-send' => (new AdminController())->sendNotification(),
    'admin-notification-audience-send' => (new AdminController())->sendNotificationAudience(),
    'admin-notification-recipients' => (new AdminController())->notificationRecipients(),
    'admin-trash-user' => (new AdminController())->trashUser(),
    'admin-trash-restore' => (new AdminController())->restoreTrash(),
    'admin-trash-restore-batch' => (new AdminController())->restoreTrashBatch(),
    'admin-trash-restore-all' => (new AdminController())->restoreTrashAll(),
    'admin-trash-delete' => (new AdminController())->deleteTrashPermanently(),
    'admin-trash-delete-batch' => (new AdminController())->deleteTrashPermanentlyBatch(),
    'admin-trash-empty-category' => (new AdminController())->emptyTrashCategory(),
    'admin-trash-purge' => (new AdminController())->purgeTrash(),
    'admin-report-export' => (new AdminController())->exportReport(),
    'admin-settings-save' => (new AdminController())->saveSettings(),
    'admin-academic' => (new AdminController())->academic(),
    'admin-repository' => (new AdminController())->repository(),
    'admin-reports' => (new AdminController())->reports(),
    'admin-settings' => (new AdminController())->settings(),
    'admin-trash' => (new AdminController())->trash(),
    'dev-reload' => (new DevController())->reloadStamp(),
    'dashboard', 'home', 'inicio' => (new DashboardController())->index(),
    'calendar', 'calendario' => (new CalendarController())->index(),
    'calendar-events' => (new CalendarController())->events(),
    'projects', 'proyectos', 'mis-proyectos' => (new ProjectsController())->index(),
    'assigned-projects' => (new ProjectsController())->assigned(),
    'project-detail', 'detalle-proyecto' => (new ProjectsController())->detail(),
    'project-academic-history-events' => (new ProjectsController())->academicHistoryEvents(),
    'project-file-preview' => (new ProjectsController())->filePreview(),
    'project-file-content' => (new ProjectsController())->fileContent(),
    'project-file-download' => (new ProjectsController())->fileDownload(),
    'project-package-download' => (new ProjectsController())->downloadAcademicPackage(),
    'project-zip-list' => (new ProjectsController())->zipList(),
    'project-zip-entry-preview' => (new ProjectsController())->zipEntryPreview(),
    'project-zip-entry-content' => (new ProjectsController())->zipEntryContent(),
    'project-zip-entry-download' => (new ProjectsController())->zipEntryDownload(),
    'admin-project-file' => (new ProjectDocumentController())->change(),
    'project-document-review-save' => (new ProjectDocumentReviewController())->save(),
    'project-adjustment-create' => (new ProjectAdjustmentController())->create(),
    'project-adjustment-respond' => (new ProjectAdjustmentController())->respond(),
    'project-adjustment-address' => (new ProjectAdjustmentController())->address(),
    'project-adjustment-close' => (new ProjectAdjustmentController())->close(),
    'project-adjustment-list' => (new ProjectAdjustmentController())->listing(),
    'project-description-save' => (new ProjectsController())->saveDescription(),
    'new-project', 'nuevo-proyecto' => (new ProjectsController())->create(),
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
    'support-material-evolution-events' => (new SupportMaterialController())->evolutionEvents(),
    'support-material-manage-save' => (new AdminController())->saveSupportMaterial(),
    'support-material-manage-file' => (new AdminController())->changeSupportMaterialFile(),
    'support-material-preview' => (new SupportMaterialController())->preview(),
    'support-material-preview-content' => (new SupportMaterialController())->previewContent(),
    'support-material-version-preview' => (new SupportMaterialController())->versionPreview(),
    'support-material-version-content' => (new SupportMaterialController())->versionContent(),
    'support-material-version-download' => (new SupportMaterialController())->versionDownload(),
    'support-material-download' => (new SupportMaterialController())->download(),
    'support-material-zip-list' => (new SupportMaterialController())->zipList(),
    'support-material-zip-entry-preview' => (new SupportMaterialController())->zipEntryPreview(),
    'support-material-zip-entry-content' => (new SupportMaterialController())->zipEntryContent(),
    'support-material-zip-entry-download' => (new SupportMaterialController())->zipEntryDownload(),
    'support-material-package-download' => (new SupportMaterialController())->downloadPackage(),
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
    default => (new ErrorController())->notFound(),
};
// Final del enrutamiento principal
