<?php

declare(strict_types=1);

final class View
{
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

        extract($data, EXTR_SKIP);

        // Captura la vista como contenido para insertarla dentro del layout.
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require $layoutFile;
    }
}
