<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(getenv('DB_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL),
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'name' => getenv('DB_DATABASE') ?: 'tesis',
    'user' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
