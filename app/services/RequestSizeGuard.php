<?php

declare(strict_types=1);

final class RequestSizeGuard
{
    private const MESSAGE = 'La solicitud supera el tamaño máximo permitido por el servidor.';

    private const MULTIPART_ROUTES = [
        'profile',
        'profile-avatar-update',
        'new-project',
        'admin-project-save',
        'admin-project-file',
        'admin-users-import',
        'admin-repository-publish',
        'admin-support-material-save',
        'admin-support-material-file',
        'support-material-manage-save',
        'support-material-manage-file',
        'project-draft-upload',
        'student-project-document',
        'student-project-publish',
        'student-project-review-representation',
        'repository-direct-project-publish',
        'repository-direct-project-file',
    ];

    public static function rejectIfExceeded(string $page): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !in_array($page, self::MULTIPART_ROUTES, true)) {
            return;
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMaxBytes = self::iniBytes((string) ini_get('post_max_size'));
        if ($contentLength <= 0 || $postMaxBytes === PHP_INT_MAX || $contentLength <= $postMaxBytes) {
            return;
        }

        error_log('Upload request rejected before parsing: Content-Length exceeded post_max_size.');
        http_response_code(413);

        if ($page === 'profile' || $page === 'new-project') {
            self::renderPageError($page);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => self::MESSAGE, 'data' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function renderPageError(string $page): never
    {
        View::render('errors/413', [
            'currentPage' => $page === 'profile' ? 'profile' : 'projects',
            'title' => 'Solicitud demasiado grande',
            'bodyClass' => 'error-page',
            'requestSizeError' => self::MESSAGE,
            'requestSizeReturnUrl' => route($page === 'profile' ? 'profile' : 'new-project'),
        ], 'error');
        exit;
    }

    private static function iniBytes(string $value): int
    {
        $value = trim(strtolower($value));
        if ($value === '' || $value === '-1') return PHP_INT_MAX;
        $unit = substr($value, -1);
        $number = (float) $value;
        $multiplier = match ($unit) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
        return (int) round($number * $multiplier);
    }
}
