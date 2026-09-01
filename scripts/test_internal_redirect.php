<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
$GLOBALS['config'] = ['base_url' => ''];
require ROOT_PATH . '/app/helpers.php';

$fallback = '/index.php?page=dashboard';
$allowed = [
    '/',
    '/index.php?page=dashboard',
    '/ruta-interna',
];
$rejected = [
    '//evil.example',
    'https://evil.example',
    'http://evil.example',
    'javascript:alert(1)',
    'data:text/html,<script>alert(1)</script>',
    '\\evil.example',
    '/%2F%2Fevil.example',
    '/%252F%252Fevil.example',
    '/\\evil.example',
];

$failures = [];
foreach ($allowed as $candidate) {
    if (safe_internal_redirect_target($candidate, $fallback) !== $candidate) {
        $failures[] = 'permitido rechazado: ' . $candidate;
    }
}
foreach ($rejected as $candidate) {
    if (safe_internal_redirect_target($candidate, $fallback) !== $fallback) {
        $failures[] = 'externo aceptado: ' . $candidate;
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

printf("Internal redirect validation OK (%d permitidos, %d rechazados)\n", count($allowed), count($rejected));
