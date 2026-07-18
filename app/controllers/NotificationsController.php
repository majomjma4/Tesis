<?php

declare(strict_types=1);

final class NotificationsController
{
    // Inicio de presentación y consulta de notificaciones
    // Prepara la pantalla principal y responde listados filtrados con sus contadores actualizados.
    public function index(): void
    {
        $this->ensureSession();
        $error = null;
        $notifications = [];
        $counters = ['unread' => 0, 'today' => 0, 'week' => 0, 'total' => 0];

        try {
            $model = new NotificationModel();
            $notifications = $model->getByUser($this->currentUserId());
            $counters = $model->getCounters($this->currentUserId());
        } catch (Throwable $exception) {
            error_log('Notifications index error: ' . $exception->getMessage());
            $notifications = $this->demoNotifications();
            $counters = (new NotificationModel())->getDemoCounters($notifications);
        }

        View::render('notifications/index', [
            'currentPage' => 'notifications',
            'title' => 'Notificaciones | Gestion Documental Academica',
            'bodyClass' => 'notifications-page',
            'pageScript' => asset('js/notifications.js'),
            'summary' => $this->summaryCards($counters),
            'groups' => $this->groupNotifications($notifications),
            'statusFilters' => $this->statusFilters(),
            'typeFilters' => $this->typeFilters(),
            'sidebarSummary' => ['unread' => $counters['unread'], 'read' => max(0, $counters['total'] - $counters['unread']), 'updated' => date('d/m/Y H:i')],
            'sidebarActivity' => $this->activitySummary($notifications),
            'notificationUnreadCount' => $counters['unread'],
            'notificationCsrfToken' => $this->csrfToken(),
            'notificationEndpoints' => $this->endpoints(),
            'loadError' => $error,
        ]);
    }

    public function listing(): void
    {
        $this->requireMethod('GET');
        $this->ensureSession();
        $search = mb_strtolower(mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 120));
        $type = (string) ($_GET['type'] ?? '');
        $hidden = filter_var($_GET['hidden'] ?? false, FILTER_VALIDATE_BOOL);
        $trash = filter_var($_GET['trash'] ?? false, FILTER_VALIDATE_BOOL);
        $status = (string) ($_GET['status'] ?? '');
        $model = new NotificationModel();

        try {
            $notifications = $model->getByUser($this->currentUserId(), ['search' => $search, 'type' => $type, 'status' => $status, 'hidden' => $hidden, 'trash' => $trash]);
            $counters = $model->getCounters($this->currentUserId());
            $sectionNotifications = ($hidden || $trash) ? $model->getByUser($this->currentUserId(), ['hidden' => $hidden, 'trash' => $trash]) : [];
        } catch (Throwable $exception) {
            error_log('Notifications list fallback: ' . $exception->getMessage());
            $allNotifications = $this->demoNotifications();
            $notifications = array_values(array_filter($allNotifications, static function (array $item) use ($search, $type, $status, $hidden, $trash): bool {
                $haystack = mb_strtolower($item['title'] . ' ' . $item['message'] . ' ' . $item['project_name']);
                $matchesSearch = $search === '' || str_contains($haystack, $search);
                $matchesType = $type === '' || $item['type'] === $type;
                $matchesStatus = $status === '' || ($status === 'read' ? $item['is_read'] : !$item['is_read']);
                $matchesVisibility = $trash
                    ? !empty($item['deleted_at'])
                    : ($hidden ? !empty($item['archived_at']) && empty($item['deleted_at']) : empty($item['archived_at']) && empty($item['deleted_at']));
                return $matchesSearch && $matchesType && $matchesStatus && $matchesVisibility;
            }));
            $counters = $model->getDemoCounters($allNotifications);
            $sectionNotifications = array_values(array_filter($allNotifications, static fn (array $item): bool => $trash ? !empty($item['deleted_at']) : ($hidden ? !empty($item['archived_at']) && empty($item['deleted_at']) : empty($item['archived_at']) && empty($item['deleted_at']))));
        }

        $this->json(true, 'Notificaciones actualizadas.', [
            'notifications' => $notifications,
            'groups' => $this->groupNotifications($notifications),
            'counters' => $counters,
            'sectionCounters' => $this->sectionCounters($sectionNotifications, $counters, $hidden, $trash),
        ]);
    }
    // Final de presentación y consulta de notificaciones

    // Inicio de gestión del estado de lectura
    // Marca notificaciones individuales o múltiples y devuelve el resumen global resultante.
    public function markRead(): void
    {
        $this->changeReadState(true);
    }

    public function markUnread(): void
    {
        $this->changeReadState(false);
    }

    public function markAllRead(): void
    {
        $this->requirePostAndCsrf();
        $this->runJson(function (NotificationModel $model, int $userId): array {
            $updated = $model->markAllAsRead($userId);
            return ['updated' => $updated, 'counters' => $model->getCounters($userId)];
        }, 'Todas las notificaciones fueron marcadas como leidas.', function (): array {
            $notifications = $this->demoNotifications();
            $updated = 0;
            foreach ($notifications as &$notification) {
                if (!$notification['is_read']) {
                    $notification['is_read'] = true;
                    $notification['read_at'] = date('Y-m-d H:i:s');
                    $updated++;
                }
            }
            unset($notification);
            $this->saveDemoNotifications($notifications);
            return ['updated' => $updated, 'counters' => (new NotificationModel())->getDemoCounters($notifications)];
        });
    }
    // Final de gestión del estado de lectura

    // Inicio de archivado, papelera y restauración
    // Coordina el ciclo reversible de ocultar, restaurar y eliminar notificaciones.
    public function delete(): void
    {
        $this->requirePostAndCsrf();
        $id = $this->notificationId();
        $this->runJson(function (NotificationModel $model, int $userId) use ($id): array {
            if ($model->findForUser($id, $userId) === null) {
                $this->json(false, 'La notificacion no existe.', [], 404);
            }
            $model->softDelete($id, $userId);
            return ['notificationId' => $id, 'counters' => $model->getCounters($userId)];
        }, 'Notificacion archivada.', function () use ($id): array {
            $notifications = $this->demoNotifications();
            $exists = false;
            foreach ($notifications as &$notification) {
                if ($notification['id'] === $id) {
                    $exists = true;
                    $notification['archived_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($notification);
            if (!$exists) {
                $this->json(false, 'La notificacion no existe.', [], 404);
            }
            $this->saveDemoNotifications($notifications);
            return ['notificationId' => $id, 'counters' => (new NotificationModel())->getDemoCounters($notifications)];
        });
    }

    public function counters(): void
    {
        $this->requireMethod('GET');
        $this->runJson(
            fn (NotificationModel $model, int $userId): array => ['counters' => $model->getCounters($userId)],
            'Contadores actualizados.',
            fn (): array => ['counters' => (new NotificationModel())->getDemoCounters($this->demoNotifications())]
        );
    }

    public function restore(): void
    {
        $this->requirePostAndCsrf();
        $id = $this->notificationId();
        $this->runJson(function (NotificationModel $model, int $userId) use ($id): array {
            if (!$model->restore($id, $userId)) {
                $this->json(false, 'La notificacion oculta no existe.', [], 404);
            }
            return ['notificationId' => $id, 'counters' => $model->getCounters($userId)];
        }, 'Notificacion restaurada.', function () use ($id): array {
            $notifications = $this->demoNotifications();
            $index = $this->findDemoNotificationIndex($notifications, $id);
            if ($index === null || (empty($notifications[$index]['archived_at']) && empty($notifications[$index]['deleted_at']))) {
                $this->json(false, 'La notificacion oculta no existe.', [], 404);
            }
            $notifications[$index]['deleted_at'] = null;
            $this->saveDemoNotifications($notifications);
            return ['notificationId' => $id, 'counters' => (new NotificationModel())->getDemoCounters($notifications)];
        });
    }

    public function destroy(): void
    {
        $this->requirePostAndCsrf();
        $id = $this->notificationId();
        $this->runJson(function (NotificationModel $model, int $userId) use ($id): array {
            if (!$model->moveToTrash($id, $userId)) {
                $this->json(false, 'La notificacion no existe.', [], 404);
            }
            return ['notificationId' => $id, 'counters' => $model->getCounters($userId)];
        }, 'Notificacion movida a la papelera.', function () use ($id): array {
            $notifications = $this->demoNotifications();
            $index = $this->findDemoNotificationIndex($notifications, $id);
            if ($index === null) {
                $this->json(false, 'La notificacion no existe.', [], 404);
            }
            $notifications[$index]['archived_at'] = null;
            $notifications[$index]['deleted_at'] = date('Y-m-d H:i:s');
            $this->saveDemoNotifications($notifications);
            return ['notificationId' => $id, 'counters' => (new NotificationModel())->getDemoCounters($notifications)];
        });
    }

    public function emptyTrash(): void
    {
        $this->requirePostAndCsrf();
        $this->runJson(function (NotificationModel $model, int $userId): array {
            $deleted = $model->emptyTrash($userId);
            return ['affected' => $deleted, 'counters' => $model->getCounters($userId)];
        }, 'Papelera vaciada.', function (): array {
            $notifications = $this->demoNotifications();
            $before = count($notifications);
            $notifications = array_values(array_filter($notifications, static fn (array $item): bool => empty($item['deleted_at'])));
            $this->saveDemoNotifications($notifications);
            return ['affected' => $before - count($notifications), 'counters' => (new NotificationModel())->getDemoCounters($notifications)];
        });
    }

    public function trashBulk(): void
    {
        $this->requirePostAndCsrf();
        $action = (string) ($_POST['bulk_action'] ?? '');
        if (!in_array($action, ['restore', 'delete'], true)) {
            $this->json(false, 'Accion masiva no valida.', [], 400);
        }
        $ids = $this->notificationIds();
        $this->runJson(function (NotificationModel $model, int $userId) use ($action, $ids): array {
            $affected = $action === 'restore' ? $model->restoreMany($ids, $userId) : $model->deleteManyFromTrash($ids, $userId);
            return ['affected' => $affected, 'counters' => $model->getCounters($userId)];
        }, $action === 'restore' ? 'Notificaciones restauradas.' : 'Notificaciones eliminadas definitivamente.', function () use ($action, $ids): array {
            $notifications = $this->demoNotifications();
            $affected = 0;
            if ($action === 'delete') {
                $notifications = array_values(array_filter($notifications, static function (array $item) use ($ids, &$affected): bool {
                    if (!empty($item['deleted_at']) && in_array((int) $item['id'], $ids, true)) { $affected++; return false; }
                    return true;
                }));
            } else {
                foreach ($notifications as &$item) {
                    if (!empty($item['deleted_at']) && in_array((int) $item['id'], $ids, true)) { $item['deleted_at'] = null; $item['archived_at'] = null; $affected++; }
                }
                unset($item);
            }
            $this->saveDemoNotifications($notifications);
            return ['affected' => $affected, 'counters' => (new NotificationModel())->getDemoCounters($notifications)];
        });
    }
    // Final de archivado, papelera y restauración

    // Inicio de apertura contextual
    // Marca la notificación como leída y entrega únicamente una ruta interna validada.
    public function open(): void
    {
        $this->requirePostAndCsrf();
        $id = $this->notificationId();
        $this->runJson(function (NotificationModel $model, int $userId) use ($id): array {
            $notification = $model->findForUser($id, $userId);
            if ($notification === null) {
                $this->json(false, 'La notificacion no existe.', [], 404);
            }
            $model->markAsRead($id, $userId);
            $url = $this->safeInternalUrl($notification['action_url']);

            return [
                'notificationId' => $id,
                'url' => $url,
                'detail' => $notification,
                'counters' => $model->getCounters($userId),
            ];
        }, 'Notificacion abierta.', function () use ($id): array {
            $notifications = $this->demoNotifications();
            $index = $this->findDemoNotificationIndex($notifications, $id);
            if ($index === null) {
                $this->json(false, 'La notificacion no existe.', [], 404);
            }
            $notifications[$index]['is_read'] = true;
            $notifications[$index]['read_at'] = date('Y-m-d H:i:s');
            $this->saveDemoNotifications($notifications);
            $url = $this->safeInternalUrl($notifications[$index]['action_url']);
            return ['notificationId' => $id, 'url' => $url, 'detail' => $notifications[$index], 'counters' => (new NotificationModel())->getDemoCounters($notifications)];
        });
    }
    // Final de apertura contextual

    // Inicio de operaciones compartidas y respaldo demostrativo
    // Unifica cambios de estado, respuestas con MySQL y persistencia temporal cuando la conexión no está activa.
    private function changeReadState(bool $read): void
    {
        $this->requirePostAndCsrf();
        $id = $this->notificationId();
        $this->runJson(function (NotificationModel $model, int $userId) use ($id, $read): array {
            if ($model->findForUser($id, $userId) === null) {
                $this->json(false, 'La notificacion no existe.', [], 404);
            }
            $read ? $model->markAsRead($id, $userId) : $model->markAsUnread($id, $userId);
            return ['notificationId' => $id, 'isRead' => $read, 'counters' => $model->getCounters($userId)];
        }, $read ? 'Notificacion marcada como leida.' : 'Notificacion marcada como no leida.', function () use ($id, $read): array {
            $notifications = $this->demoNotifications();
            $index = $this->findDemoNotificationIndex($notifications, $id);
            if ($index === null) {
                $this->json(false, 'La notificacion no existe.', [], 404);
            }
            $notifications[$index]['is_read'] = $read;
            $notifications[$index]['read_at'] = $read ? date('Y-m-d H:i:s') : null;
            $this->saveDemoNotifications($notifications);
            return ['notificationId' => $id, 'isRead' => $read, 'counters' => (new NotificationModel())->getDemoCounters($notifications)];
        });
    }

    private function runJson(callable $operation, string $message, ?callable $demoFallback = null): void
    {
        $this->ensureSession();
        try {
            $data = $operation(new NotificationModel(), $this->currentUserId());
            $this->json(true, $message, $data);
        } catch (Throwable $exception) {
            error_log('Notifications action error: ' . $exception->getMessage());
            if ($demoFallback !== null) {
                try {
                    $this->json(true, $message, $demoFallback());
                } catch (Throwable $fallbackException) {
                    error_log('Notifications demo action error: ' . $fallbackException->getMessage());
                }
            }
            $this->json(false, 'No fue posible procesar la solicitud.', [], 500);
        }
    }

    private function demoNotifications(): array
    {
        $this->ensureSession();
        if (!isset($_SESSION['notification_demo_items']) || !is_array($_SESSION['notification_demo_items'])) {
            $_SESSION['notification_demo_items'] = (new NotificationModel())->getDemoNotifications();
        }
        $expiration = new DateTimeImmutable('-30 days');
        $_SESSION['notification_demo_items'] = array_values(array_filter($_SESSION['notification_demo_items'], static function (array $notification) use ($expiration): bool {
            if (empty($notification['deleted_at'])) {
                return true;
            }
            return new DateTimeImmutable($notification['deleted_at']) >= $expiration;
        }));
        return $_SESSION['notification_demo_items'];
    }

    private function saveDemoNotifications(array $notifications): void
    {
        $_SESSION['notification_demo_items'] = array_values($notifications);
    }

    private function findDemoNotificationIndex(array $notifications, int $id): ?int
    {
        foreach ($notifications as $index => $notification) {
            if ((int) $notification['id'] === $id) {
                return $index;
            }
        }
        return null;
    }
    // Final de operaciones compartidas y respaldo demostrativo

    // Inicio de validación de solicitudes
    // Protege métodos, CSRF, identificadores, sesión y navegación interna antes de ejecutar acciones.
    private function requirePostAndCsrf(): void
    {
        $this->requireMethod('POST');
        $this->ensureSession();
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
        if ($token === '' || !hash_equals($this->csrfToken(), $token)) {
            $this->json(false, 'No fue posible validar la solicitud.', [], 403);
        }
    }

    private function requireMethod(string $method): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
            header('Allow: ' . $method);
            $this->json(false, 'Metodo no permitido.', [], 405);
        }
    }

    private function notificationId(): int
    {
        $id = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id === null) {
            $this->json(false, 'Identificador invalido.', [], 400);
        }
        return (int) $id;
    }

    private function notificationIds(): array
    {
        $submitted = $_POST['notification_ids'] ?? [];
        if (!is_array($submitted)) {
            $this->json(false, 'Seleccion no valida.', [], 400);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', array_slice($submitted, 0, 100)), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            $this->json(false, 'Selecciona al menos una notificacion.', [], 400);
        }
        return $ids;
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!isset($_SESSION['user_id']) && !isset($_SESSION['notification_demo_user_id'])) {
            $_SESSION['notification_demo_user_id'] = 1;
        }
    }

    private function currentUserId(): int
    {
        return (int) ($_SESSION['user_id'] ?? $_SESSION['notification_demo_user_id'] ?? 0);
    }

    private function csrfToken(): string
    {
        if (!isset($_SESSION['notification_csrf'])) {
            $_SESSION['notification_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['notification_csrf'];
    }

    private function safeInternalUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '' || str_contains($url, "\0") || str_contains($url, '..')) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || str_starts_with($url, '//')) {
            return null;
        }
        $path = ltrim((string) ($parts['path'] ?? ''), '/');
        if ($path !== 'index.php') {
            return null;
        }
        return base_url($url);
    }
    // Final de validación de solicitudes

    // Inicio de transformación para la interfaz
    // Agrupa, presenta y resume los registros crudos en estructuras consumibles por la vista.
    private function groupNotifications(array $notifications): array
    {
        $groups = ['Hoy' => [], 'Ayer' => [], 'Esta semana' => [], 'Anteriores' => []];
        $today = new DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');
        $weekStart = $today->modify('monday this week');

        foreach ($notifications as $notification) {
            $date = new DateTimeImmutable($notification['created_at']);
            $group = $date >= $today ? 'Hoy' : ($date >= $yesterday ? 'Ayer' : ($date >= $weekStart ? 'Esta semana' : 'Anteriores'));
            $groups[$group][] = $this->present($notification);
        }

        return array_filter($groups);
    }

    private function present(array $notification): array
    {
        $styles = [
            'delivery' => ['delivery', 'Entregas', 'fa-cloud-arrow-up'], 'observation' => ['observation', 'Observaciones', 'fa-comment-dots'],
            'status_change' => ['status approved', 'Cambios de estado', 'fa-circle-check'], 'review' => ['correction', 'Revision', 'fa-triangle-exclamation'],
            'reminder' => ['reminder', 'Recordatorios', 'fa-clock'], 'system' => ['system', 'Sistema', 'fa-gear'],
            'tribunal' => ['tribunal', 'Tribunal', 'fa-user-group'], 'repository' => ['system', 'Repositorio', 'fa-database'],
            'comment' => ['observation', 'Comentarios', 'fa-message'],
        ];
        [$typeClass, $label, $icon] = $styles[$notification['type']] ?? $styles['system'];
        $date = new DateTimeImmutable($notification['created_at']);
        return array_merge($notification, ['description' => $notification['message'], 'project' => $notification['project_name'], 'time' => $date->format('H:i'), 'date' => $date->format('d/m/Y'), 'unread' => !$notification['is_read'], 'filter' => $label, 'type_class' => $typeClass, 'icon' => $icon]);
    }

    private function summaryCards(array $counters): array
    {
        return [
            ['key' => 'unread', 'label' => 'No leidas', 'value' => (string) $counters['unread'], 'icon' => 'fa-eye-slash', 'tone' => 'blue'],
            ['key' => 'today', 'label' => 'Hoy', 'value' => (string) $counters['today'], 'icon' => 'fa-calendar-day', 'tone' => 'green'],
            ['key' => 'week', 'label' => 'Esta semana', 'value' => (string) $counters['week'], 'icon' => 'fa-calendar-week', 'tone' => 'purple'],
            ['key' => 'total', 'label' => 'Total', 'value' => (string) $counters['total'], 'icon' => 'fa-layer-group', 'tone' => 'slate'],
        ];
    }

    private function statusFilters(): array
    {
        return ['all' => 'Todas', 'unread' => 'No leidas', 'read' => 'Leidas', 'hidden' => 'Archivadas', 'trash' => 'Papelera'];
    }

    private function typeFilters(): array
    {
        return ['all' => 'Todos', 'delivery' => 'Entregas', 'observation' => 'Observaciones', 'status_change' => 'Cambios de estado', 'review' => 'Revision', 'reminder' => 'Recordatorios', 'system' => 'Sistema', 'tribunal' => 'Tribunal', 'repository' => 'Repositorio', 'comment' => 'Comentarios'];
    }

    private function activitySummary(array $notifications): array
    {
        $counts = ['delivery' => 0, 'observation' => 0, 'status_change' => 0];
        foreach ($notifications as $notification) {
            if (array_key_exists($notification['type'], $counts)) $counts[$notification['type']]++;
        }
        return [
            ['label' => 'Entregas', 'value' => $counts['delivery'], 'icon' => 'fa-cloud-arrow-up', 'tone' => 'blue'],
            ['label' => 'Observaciones', 'value' => $counts['observation'], 'icon' => 'fa-comment-dots', 'tone' => 'warning'],
            ['label' => 'Cambios de estado', 'value' => $counts['status_change'], 'icon' => 'fa-arrows-rotate', 'tone' => 'green'],
        ];
    }

    private function sectionCounters(array $notifications, array $globalCounters, bool $hidden, bool $trash): array
    {
        if (!$hidden && !$trash) {
            return $globalCounters;
        }
        $weekStart = new DateTimeImmutable('monday this week');
        $expirationThreshold = new DateTimeImmutable('-23 days');
        return [
            'total' => count($notifications),
            'unread' => count(array_filter($notifications, static fn (array $item): bool => !$item['is_read'])),
            'week' => count(array_filter($notifications, static fn (array $item): bool => new DateTimeImmutable($item['created_at']) >= $weekStart)),
            'expiring' => count(array_filter($notifications, static fn (array $item): bool => !empty($item['deleted_at']) && new DateTimeImmutable($item['deleted_at']) <= $expirationThreshold)),
        ];
    }

    private function endpoints(): array
    {
        $names = ['list', 'read', 'unread', 'read-all', 'delete', 'restore', 'destroy', 'trash-empty', 'trash-bulk', 'counters', 'open'];
        return array_combine($names, array_map(static fn (string $name): string => route('notifications/' . $name), $names));
    }
    // Final de transformación para la interfaz

    // Inicio de respuestas JSON
    // Mantiene un contrato uniforme para todas las operaciones asíncronas del módulo.
    private function json(bool $success, string $message, array $data = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    // Final de respuestas JSON
}
