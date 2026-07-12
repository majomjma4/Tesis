<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');

$config = require APP_PATH . '/config/app.php';

require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/View.php';
require APP_PATH . '/models/DashboardModel.php';
require APP_PATH . '/models/AuthModel.php';
require APP_PATH . '/models/ProjectModel.php';
require APP_PATH . '/models/RepositoryModel.php';
require APP_PATH . '/models/FavoriteModel.php';
require APP_PATH . '/models/DownloadModel.php';
require APP_PATH . '/models/SupportMaterialModel.php';
require APP_PATH . '/models/SupportMaterialDownloadModel.php';
require APP_PATH . '/services/ArchiveService.php';
require APP_PATH . '/services/FilePreviewService.php';
require APP_PATH . '/services/DocxPreviewService.php';
require APP_PATH . '/controllers/DashboardController.php';
require APP_PATH . '/controllers/AuthController.php';
require APP_PATH . '/controllers/DevController.php';
require APP_PATH . '/controllers/ProjectsController.php';
require APP_PATH . '/controllers/RepositoryController.php';
require APP_PATH . '/controllers/SupportMaterialController.php';

// Front controller: centraliza las rutas principales de la aplicacion.
$page = strtolower(trim((string) ($_GET['page'] ?? 'dashboard')));

match ($page) {
    'login' => (new AuthController())->login(),
    'logout' => (new AuthController())->logout(),
    'dev-reload' => (new DevController())->reloadStamp(),
    'dashboard', 'home', 'inicio' => (new DashboardController())->index(),
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
    default => (new DashboardController())->index(),
};
