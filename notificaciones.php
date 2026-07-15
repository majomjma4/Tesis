<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');

$config = require APP_PATH . '/config/app.php';

require APP_PATH . '/helpers.php';
require APP_PATH . '/Core/View.php';
require APP_PATH . '/Core/Database.php';
require APP_PATH . '/models/NotificationModel.php';
require APP_PATH . '/controllers/NotificationsController.php';

(new NotificationsController())->index();
