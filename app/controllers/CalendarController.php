<?php

declare(strict_types=1);

final class CalendarController
{
    // Inicio de presentación del calendario
    // Prepara los eventos y las rutas contextuales requeridas por la vista principal.
    public function index(): void
    {
        $calendar = new CalendarModel();
        $projectFilterId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

        View::render('calendar/index', [
            'currentPage' => 'calendar',
            'title' => 'Calendario | Gestion Documental Academica',
            'bodyClass' => 'dashboard-page calendar-page',
            'pageScript' => asset('js/calendar.js'),
            'calendarEvents' => $calendar->getEvents(),
            'projectsUrl' => route('projects'),
            'projectFilterId' => $projectFilterId,
        ]);
    }
    // Final de presentación del calendario

    // Inicio de operaciones asíncronas de eventos
    // Atiende la consulta, creación, actualización y eliminación solicitada por JavaScript.
    public function events(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $model = new CalendarModel();
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'GET') {
                $this->json(true, 'Eventos cargados.', $model->getEvents());
                return;
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                throw new InvalidArgumentException('La solicitud no contiene datos válidos.');
            }
            if ($method === 'POST') {
                $this->json(true, 'Evento guardado correctamente.', $model->save($payload));
                return;
            }
            if ($method === 'DELETE') {
                $deleted = $model->delete(trim((string) ($payload['id'] ?? '')));
                $this->json($deleted, $deleted ? 'Evento eliminado.' : 'El evento no existe.', null, $deleted ? 200 : 404);
                return;
            }
            $this->json(false, 'Método no permitido.', null, 405);
        } catch (Throwable $error) {
            $this->json(false, $error->getMessage(), null, $error instanceof InvalidArgumentException ? 422 : 500);
        }
    }
    // Final de operaciones asíncronas de eventos

    // Inicio de construcción de respuestas JSON
    // Unifica el formato y el código HTTP devuelto por el endpoint del calendario.
    private function json(bool $success, string $message, mixed $data = null, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    // Final de construcción de respuestas JSON
}
