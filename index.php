<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');

$config = require APP_PATH . '/config/app.php';

require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/View.php';
require APP_PATH . '/models/DashboardModel.php';
require APP_PATH . '/models/AuthModel.php';
require APP_PATH . '/controllers/DashboardController.php';
require APP_PATH . '/controllers/AuthController.php';
require APP_PATH . '/controllers/DevController.php';

// Front controller: centraliza las rutas principales de la aplicacion.
$page = strtolower(trim((string) ($_GET['page'] ?? 'dashboard')));

match ($page) {
    'login' => (new AuthController())->login(),
    'logout' => (new AuthController())->logout(),
    'dev-reload' => (new DevController())->reloadStamp(),
    'dashboard', 'home', 'inicio' => (new DashboardController())->index(),
    default => (new DashboardController())->index(),
};
