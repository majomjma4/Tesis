<?php

declare(strict_types=1);

final class NotificationModel
{
    private const TYPES = ['delivery', 'observation', 'status_change', 'review', 'reminder', 'system', 'tribunal', 'repository', 'comment', 'adjustment'];

    public function __construct(private readonly ?PDO $db = null)
    {
    }

    // Inicio de acceso y consulta de notificaciones
    // Centraliza la conexión y recupera registros, detalles y contadores limitados al usuario actual.
    private function connection(): PDO
    {
        return $this->db ?? Database::connection();
    }

    public function getByUser(int $userId, array $filters = []): array
    {
        [$conditions, $parameters] = $this->notificationQuery($userId, $filters);
        $statement = $this->connection()->prepare(
            'SELECT n.id, n.user_id, n.project_id, n.type, n.title, n.message, n.action_url, n.action_label, n.metadata, n.is_read, n.read_at, n.created_at, n.archived_at, n.deleted_at,
                    COALESCE(p.title, NULLIF(JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.project_name")), ""), "Notificacion general") AS project_name
             FROM notifications n LEFT JOIN projects p ON p.id = n.project_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY n.created_at DESC, n.id DESC'
        );
        $statement->execute($parameters);

        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    public function getByUserPaginated(int $userId, array $filters = [], array $pagination = []): array
    {
        [$conditions, $parameters] = $this->notificationQuery($userId, $filters);
        $where = ' FROM notifications n LEFT JOIN projects p ON p.id = n.project_id WHERE ' . implode(' AND ', $conditions);
        $result = PaginationService::run(
            $this->connection(),
            'SELECT COUNT(*)' . $where,
            'SELECT n.id, n.user_id, n.project_id, n.type, n.title, n.message, n.action_url, n.action_label, n.metadata, n.is_read, n.read_at, n.created_at, n.archived_at, n.deleted_at,
                    COALESCE(p.title, NULLIF(JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.project_name")), ""), "Notificacion general") AS project_name' . $where . ' ORDER BY n.created_at DESC, n.id DESC',
            $parameters,
            $pagination ?: PaginationService::request('notification_page', 'notifications_per_page')
        );
        $result['items'] = array_map([$this, 'hydrate'], $result['items']);
        return $result;
    }

    private function notificationQuery(int $userId, array $filters): array
    {
        $visibility = !empty($filters['trash'])
            ? 'n.deleted_at IS NOT NULL'
            : (!empty($filters['hidden']) ? 'n.archived_at IS NOT NULL AND n.deleted_at IS NULL' : 'n.archived_at IS NULL AND n.deleted_at IS NULL');
        $conditions = ['n.user_id = :user_id', $visibility];
        $parameters = ['user_id' => $userId];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(n.title LIKE :search_title OR n.message LIKE :search_message OR p.title LIKE :search_project OR JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.project_name")) LIKE :search_project)';
            $parameters['search_title'] = '%' . $search . '%';
            $parameters['search_message'] = '%' . $search . '%';
            $parameters['search_project'] = '%' . $search . '%';
        }

        $type = (string) ($filters['type'] ?? '');
        if (in_array($type, self::TYPES, true)) {
            $conditions[] = 'n.type = :type';
            $parameters['type'] = $type;
        }

        $projectId = (int) ($filters['project_id'] ?? 0);
        if ($projectId > 0) {
            $conditions[] = 'n.project_id = :project_id';
            $parameters['project_id'] = $projectId;
        }

        $date = (string) ($filters['date'] ?? '');
        if ($date !== '') {
            $conditions[] = 'DATE(n.created_at) = :date';
            $parameters['date'] = $date;
        }

        $dateFrom = (string) ($filters['date_from'] ?? '');
        if ($dateFrom !== '') {
            $conditions[] = 'DATE(n.created_at) >= :date_from';
            $parameters['date_from'] = $dateFrom;
        }

        $dateTo = (string) ($filters['date_to'] ?? '');
        if ($dateTo !== '') {
            $conditions[] = 'DATE(n.created_at) <= :date_to';
            $parameters['date_to'] = $dateTo;
        }

        if (($filters['status'] ?? '') === 'read') {
            $conditions[] = 'n.is_read = 1';
        } elseif (($filters['status'] ?? '') === 'unread') {
            $conditions[] = 'n.is_read = 0';
        }

        return [$conditions, $parameters];
    }

    public function findForUser(int $notificationId, int $userId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT n.id, n.user_id, n.project_id, n.type, n.title, n.message, n.action_url, n.action_label, n.metadata, n.is_read, n.read_at, n.created_at, n.archived_at, n.deleted_at,
                    p.code AS project_code, p.status AS project_status,
                    COALESCE(p.title, NULLIF(JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.project_name")), ""), "Notificacion general") AS project_name,
                    u.full_name AS sender_name
             FROM notifications n
             LEFT JOIN projects p ON p.id = n.project_id
             LEFT JOIN users u ON u.id = JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.admin_sender_id"))
             WHERE n.id = :id AND n.user_id = :user_id LIMIT 1'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);
        $notification = $statement->fetch();

        return $notification === false ? null : $this->hydrate($notification);
    }

    public function getCounters(int $userId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(is_read = 0) AS unread,
                    SUM(DATE(created_at) = CURRENT_DATE()) AS today,
                    SUM(YEARWEEK(created_at, 1) = YEARWEEK(CURRENT_DATE(), 1)) AS week
             FROM notifications WHERE user_id = :user_id AND archived_at IS NULL AND deleted_at IS NULL'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch() ?: [];

        return [
            'unread' => (int) ($row['unread'] ?? 0),
            'today' => (int) ($row['today'] ?? 0),
            'week' => (int) ($row['week'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    public function getVisibilityCounters(int $userId, bool $hidden, bool $trash): array
    {
        if (!$hidden && !$trash) return $this->getCounters($userId);
        $visibility = $trash ? 'deleted_at IS NOT NULL' : 'archived_at IS NOT NULL AND deleted_at IS NULL';
        $statement = $this->connection()->prepare(
            "SELECT COUNT(*) total,
                    COALESCE(SUM(is_read = 0), 0) unread,
                    COALESCE(SUM(YEARWEEK(created_at, 1) = YEARWEEK(CURRENT_DATE(), 1)), 0) week,
                    COALESCE(SUM(deleted_at IS NOT NULL AND deleted_at <= DATE_SUB(NOW(), INTERVAL 53 DAY)), 0) expiring
             FROM notifications WHERE user_id = :user_id AND $visibility"
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch() ?: [];
        return ['total' => (int) ($row['total'] ?? 0), 'unread' => (int) ($row['unread'] ?? 0), 'week' => (int) ($row['week'] ?? 0), 'expiring' => (int) ($row['expiring'] ?? 0)];
    }
    // Final de acceso y consulta de notificaciones

    // Inicio de cambios de estado y ciclo de eliminación
    // Gestiona lectura, archivado, papelera, restauración y eliminación individual o masiva.
    public function markAsRead(int $notificationId, int $userId): bool
    {
        return $this->updateReadState($notificationId, $userId, true);
    }

    public function markAsUnread(int $notificationId, int $userId): bool
    {
        return $this->updateReadState($notificationId, $userId, false);
    }

    public function markAllAsRead(int $userId): int
    {
        $statement = $this->connection()->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW(), updated_at = NOW()
             WHERE user_id = :user_id AND archived_at IS NULL AND deleted_at IS NULL AND is_read = 0'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->rowCount();
    }

    public function softDelete(int $notificationId, int $userId): bool
    {
        $statement = $this->connection()->prepare(
            'UPDATE notifications SET archived_at = NOW(), updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND archived_at IS NULL AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function restore(int $notificationId, int $userId): bool
    {
        $statement = $this->connection()->prepare(
            'UPDATE notifications SET archived_at = NULL, deleted_at = NULL, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND (archived_at IS NOT NULL OR deleted_at IS NOT NULL)'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function deletePermanently(int $notificationId, int $userId): bool
    {
        $statement = $this->connection()->prepare(
            'DELETE FROM notifications WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function moveToTrash(int $notificationId, int $userId): bool
    {
        $statement = $this->connection()->prepare(
            'UPDATE notifications SET archived_at = NULL, deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $notificationId, 'user_id' => $userId]);

        return $statement->rowCount() === 1;
    }

    public function purgeExpiredTrashForUser(int $userId, int $days = 60): int
    {
        $days = max(1, min($days, 365));
        $statement = $this->connection()->prepare(
            'DELETE FROM notifications WHERE user_id = :user_id AND deleted_at IS NOT NULL AND deleted_at < :cutoff'
        );
        $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue('cutoff', (new DateTimeImmutable())->modify("-{$days} days")->format('Y-m-d H:i:s'));
        $statement->execute();

        return $statement->rowCount();
    }

    public function emptyTrash(int $userId): int
    {
        $statement = $this->connection()->prepare('DELETE FROM notifications WHERE user_id = :user_id AND deleted_at IS NOT NULL');
        $statement->execute(['user_id' => $userId]);
        return $statement->rowCount();
    }

    public function restoreMany(array $notificationIds, int $userId): int
    {
        return $this->updateManyFromTrash($notificationIds, $userId, false);
    }

    public function deleteManyFromTrash(array $notificationIds, int $userId): int
    {
        return $this->updateManyFromTrash($notificationIds, $userId, true);
    }

    private function updateManyFromTrash(array $notificationIds, int $userId, bool $delete): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $notificationIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = $delete
            ? "DELETE FROM notifications WHERE user_id = ? AND deleted_at IS NOT NULL AND id IN ($placeholders)"
            : "UPDATE notifications SET deleted_at = NULL, archived_at = NULL, updated_at = NOW() WHERE user_id = ? AND deleted_at IS NOT NULL AND id IN ($placeholders)";
        $statement = $this->connection()->prepare($sql);
        $statement->execute(array_merge([$userId], $ids));
        return $statement->rowCount();
    }
    // Final de cambios de estado y ciclo de eliminación

    // Inicio de soporte demostrativo
    // Proporciona datos y contadores temporales mientras la base de datos no está habilitada.
    public function countUnread(int $userId): int
    {
        return $this->getCounters($userId)['unread'];
    }

    public function getDemoNotifications(): array
    {
        $now = new DateTimeImmutable();
        $items = [
            ['delivery', 'Nueva entrega registrada', 'María Fernanda subió el Informe final v3 para revisión.', 'Sistema de Gestión Documental Académica', '-20 minutes', false, 'index.php?page=project-detail&id=1&tab=deliveries'],
            ['observation', 'Se registraron observaciones', 'Tu tutor agregó observaciones en el capítulo de metodología.', 'Sistema de Gestión Documental Académica', '-2 hours', false, 'index.php?page=project-detail&id=1&tab=observations'],
            ['status_change', 'Proyecto aprobado', 'El perfil del proyecto superó la etapa de revisión académica.', 'Aplicación móvil de apoyo al aprendizaje inclusivo', '-3 hours', false, 'index.php?page=project-detail&id=2&tab=summary'],
            ['repository', 'Repositorio actualizado', 'Los documentos públicos del proyecto ya están disponibles.', 'Portal comunitario para alfabetización digital', '-4 hours', true, 'index.php?page=repository-detail&id=3'],
            ['review', 'Requiere correcciones', 'La entrega fue devuelta con ajustes pendientes antes de continuar.', 'Sistema de Gestión Documental Académica', '-1 day', false, 'index.php?page=project-detail&id=1&tab=observations'],
            ['comment', 'Nuevo comentario', 'El tutor dejó una recomendación general para el equipo.', 'Aplicación móvil de apoyo al aprendizaje inclusivo', '-1 day -3 hours', true, 'index.php?page=project-detail&id=2&tab=comments'],
            ['tribunal', 'Tribunal asignado', 'Se confirmaron los tres docentes que evaluarán tu proyecto.', 'Plataforma para seguimiento de prácticas preprofesionales', '-3 days', true, 'index.php?page=project-detail&id=3&tab=participants'],
            ['reminder', 'Recordatorio de entrega', 'Faltan 3 días para cargar la versión corregida del documento.', 'Análisis predictivo de permanencia estudiantil', '-4 days', false, null],
            ['delivery', 'Nueva versión subida', 'La versión 2.1 quedó registrada correctamente en el historial.', 'Plataforma para seguimiento de prácticas preprofesionales', '-8 days', true, 'index.php?page=project-detail&id=3&tab=deliveries'],
        ];

        return array_map(static function (array $item, int $index) use ($now): array {
            [$type, $title, $message, $projectName, $relativeDate, $isRead, $actionUrl] = $item;
            preg_match('/[?&]id=(\d+)/', (string) $actionUrl, $projectMatch);
            return [
                'id' => $index + 1,
                'user_id' => 1,
                'project_id' => $type === 'repository' ? 4 : (isset($projectMatch[1]) ? (int) $projectMatch[1] : null),
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'action_label' => $actionUrl === null ? null : match ($type) {
                    'repository' => 'Abrir repositorio',
                    'observation', 'review', 'comment' => 'Revisar en el proyecto',
                    'delivery' => 'Ver entrega',
                    'tribunal' => 'Ver informacion del tribunal',
                    default => 'Ir al proyecto',
                },
                'metadata' => ['project_name' => $projectName],
                'is_read' => $isRead,
                'read_at' => $isRead ? $now->modify($relativeDate)->format('Y-m-d H:i:s') : null,
                'created_at' => $now->modify($relativeDate)->format('Y-m-d H:i:s'),
                'archived_at' => null,
                'deleted_at' => null,
                'project_name' => $projectName,
            ];
        }, $items, array_keys($items));
    }

    public function getDemoCounters(array $notifications): array
    {
        $today = new DateTimeImmutable('today');
        $weekStart = $today->modify('monday this week');

        $active = array_filter($notifications, static fn (array $item): bool => empty($item['archived_at']) && empty($item['deleted_at']));
        return [
            'unread' => count(array_filter($active, static fn (array $item): bool => !$item['is_read'])),
            'today' => count(array_filter($active, static fn (array $item): bool => new DateTimeImmutable($item['created_at']) >= $today)),
            'week' => count(array_filter($active, static fn (array $item): bool => new DateTimeImmutable($item['created_at']) >= $weekStart)),
            'total' => count($active),
        ];
    }
    // Final de soporte demostrativo

    // Inicio de creación y normalización de registros
    // Inserta nuevas notificaciones y transforma los resultados SQL en estructuras seguras para la aplicación.
    public function createNotification(int $userId, string $type, string $title, string $message, ?int $projectId = null, ?string $actionUrl = null, array $metadata = []): int
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Tipo de notificacion no permitido.');
        }

        $statement = $this->connection()->prepare(
            'INSERT INTO notifications (user_id, project_id, type, title, message, action_url, metadata)
             VALUES (:user_id, :project_id, :type, :title, :message, :action_url, :metadata)'
        );
        $statement->execute([
            'user_id' => $userId,
            'project_id' => $projectId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    private function updateReadState(int $notificationId, int $userId, bool $isRead): bool
    {
        $statement = $this->connection()->prepare(
            'UPDATE notifications SET is_read = :is_read, read_at = ' . ($isRead ? 'NOW()' : 'NULL') . ', updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $statement->execute(['is_read' => (int) $isRead, 'id' => $notificationId, 'user_id' => $userId]);

        return $statement->rowCount() === 1 || $this->findForUser($notificationId, $userId) !== null;
    }

    private function hydrate(array $row): array
    {
        $metadata = [];
        if (!empty($row['metadata'])) {
            $decoded = json_decode((string) $row['metadata'], true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['project_id'] = $row['project_id'] === null ? null : (int) $row['project_id'];
        $row['is_read'] = (bool) $row['is_read'];
        $row['metadata'] = $metadata;
        $projectName = trim((string) ($row['project_name'] ?? ''));
        $row['project_name'] = $projectName !== '' ? $projectName : (string) ($metadata['project_name'] ?? 'Notificacion general');

        return $row;
    }
    // Final de creación y normalización de registros
}
