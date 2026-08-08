<?php

declare(strict_types=1);

final class CalendarController
{
    // Inicio de presentación del calendario
    // Prepara los eventos y las rutas contextuales requeridas por la vista principal.
    public function index(): void
    {
        $session = new AuthSessionService();
        if (!$session->isAuthenticated() || (int)($session->userId() ?? 0) < 1) { header('Location: ' . route('login')); exit; }
        $calendar = new CalendarModel();
        $projectFilterId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

        View::render('calendar/index', [
            'currentPage' => 'calendar',
            'title' => 'Calendario | Gestion Documental Academica',
            'bodyClass' => 'dashboard-page calendar-page',
            'pageScript' => asset('js/calendar.js'),
            'calendarEvents' => $calendar->getEventsForOwner((int)$session->userId()),
            'calendarCsrf' => $session->csrfToken('calendar_events'),
            'projectsUrl' => route('project-detail'),
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
            $session = new AuthSessionService();
            if (!$session->isAuthenticated() || (int)($session->userId() ?? 0) < 1) { $this->json(false, 'La sesión no está activa.', null, 403); return; }
            $owner = (int)$session->userId();
            if ($method === 'GET') {
                $this->json(true, 'Eventos cargados.', $model->getEventsForOwner($owner));
                return;
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                throw new InvalidArgumentException('La solicitud no contiene datos válidos.');
            }
            $token = (string)($payload['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
            if (!$session->validateCsrf('calendar_events', $token)) { $this->json(false, 'La solicitud contiene un token CSRF inválido.', null, 419); return; }
            if ($method === 'POST') {
                $event = $model->saveForOwner($payload, $owner);
                (new CalendarEventReminderService())->syncForOwner($owner);
                $this->json(true, 'Evento guardado correctamente.', $event);
                return;
            }
            if ($method === 'DELETE') {
                $model->deleteForOwner($payload['id'] ?? null, $owner);
                $this->json(true, 'Evento eliminado.');
                return;
            }
            $this->json(false, 'Método no permitido.', null, 405);
        } catch (CalendarEventException $error) {
            $this->json(false, $error->getMessage(), null, $error->httpStatus());
        } catch (InvalidArgumentException $error) {
            $this->json(false, $error->getMessage(), null, 422);
        } catch (Throwable $error) {
            error_log('Calendar events: ' . $error->getMessage());
            $this->json(false, 'No fue posible completar la operación del calendario.', null, 500);
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
