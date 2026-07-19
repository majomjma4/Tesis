<?php

declare(strict_types=1);

final class ErrorController
{
    public function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', [
            'currentPage' => '',
            'title' => 'Página no encontrada | Gestión Académica',
            'bodyClass' => 'error-page',
            'pageStyles' => [asset('css/admin-access.css')],
        ], 'error');
    }
}
