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

    public function getByUser(int $userId, array $filters = [], string $context = '', ?int $limit = null): array
    {
        [$conditions, $parameters] = $this->notificationQuery($userId, $filters, $context);
        $limitClause = $limit !== null ? ' LIMIT ' . max(1, min($limit, 50)) : '';
        $statement = $this->connection()->prepare(
            'SELECT n.id, n.user_id, n.project_id, n.type, n.title, n.message, n.action_url, n.action_label, n.metadata, n.is_read, n.read_at, n.created_at, n.archived_at, n.deleted_at,
                    p.status AS project_status,
                    COALESCE(p.title, NULLIF(JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.project_name")), ""), "Notificacion general") AS project_name
             FROM notifications n LEFT JOIN projects p ON p.id = n.project_id WHERE ' . implode(' AND ', $conditions) . ' ORDER BY n.created_at DESC, n.id DESC' . $limitClause
        );
        $statement->execute($parameters);

        $items = array_map([$this, 'hydrate'], $statement->fetchAll());

        return $items;
    }

    public function getByUserPaginated(int $userId, array $filters = [], array $pagination = [], string $context = ''): array
    {
        [$conditions, $parameters] = $this->notificationQuery($userId, $filters, $context);
        $where = ' FROM notifications n LEFT JOIN projects p ON p.id = n.project_id WHERE ' . implode(' AND ', $conditions);
        $result = PaginationService::run(
            $this->connection(),
            'SELECT COUNT(*)' . $where,
            'SELECT n.id, n.user_id, n.project_id, n.type, n.title, n.message, n.action_url, n.action_label, n.metadata, n.is_read, n.read_at, n.created_at, n.archived_at, n.deleted_at,
                    p.status AS project_status,
                    COALESCE(p.title, NULLIF(JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.project_name")), ""), "Notificacion general") AS project_name' . $where . ' ORDER BY n.created_at DESC, n.id DESC',
            $parameters,
            $pagination ?: PaginationService::request('notification_page', 'notifications_per_page')
        );
        $result['items'] = array_map([$this, 'hydrate'], $result['items']);
        return $result;
    }

    private function notificationQuery(int $userId, array $filters, string $context = ''): array
    {
        $visibility = !empty($filters['trash'])
            ? 'n.deleted_at IS NOT NULL AND n.deleted_at >= :trash_cutoff'
            : (!empty($filters['hidden']) ? 'n.archived_at IS NOT NULL AND n.deleted_at IS NULL' : 'n.archived_at IS NULL AND n.deleted_at IS NULL');
        $conditions = ['n.user_id = :user_id', $visibility];
        $parameters = ['user_id' => $userId];
        if (!empty($filters['trash'])) {
            $parameters['trash_cutoff'] = (new DateTimeImmutable('-' . max(1, min(365, (int) (new SystemSettingModel())->retentionDays('notification_trash_retention_days'))) . ' days'))->format('Y-m-d H:i:s');
        }

        if ($context === 'admin') {
            $conditions[] = '(n.type = "system" OR n.action_url LIKE "%page=admin-%" OR JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.admin_sender_id")) IS NOT NULL OR JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.context")) = "admin" OR JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.scope")) = "admin")';
        } elseif ($context === 'teacher') {
            $conditions[] = '(n.type != "system" AND (n.action_url IS NULL OR n.action_url NOT LIKE "%page=admin-%") AND JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.admin_sender_id")) IS NULL AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.context")), "") != "admin" AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.scope")), "") != "admin")';
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $search = $this->escapeLike($search);
            $conditions[] = '(n.title LIKE :search_title ESCAPE "!" OR n.message LIKE :search_message ESCAPE "!" OR COALESCE(p.title, NULLIF(JSON_UNQUOTE(JSON_EXTRACT(n.metadata, "$.project_name")), ""), "Notificacion general") LIKE :search_project ESCAPE "!")';
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

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
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

    public function getCounters(int $userId, string $context = ''): array
    {
        [$conditions, $parameters] = $this->notificationQuery($userId, [], $context);
        $statement = $this->connection()->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(n.is_read = 0), 0) AS unread,
                    COALESCE(SUM(DATE(n.created_at) = CURRENT_DATE()), 0) AS today,
                    COALESCE(SUM(YEARWEEK(n.created_at, 1) = YEARWEEK(CURRENT_DATE(), 1)), 0) AS week
             FROM notifications n WHERE ' . implode(' AND ', $conditions)
        );
        $statement->execute($parameters);
        $row = $statement->fetch() ?: [];

        return [
            'unread' => (int) ($row['unread'] ?? 0),
            'today' => (int) ($row['today'] ?? 0),
            'week' => (int) ($row['week'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    public function getVisibilityCounters(int $userId, bool $hidden, bool $trash, string $context = ''): array
    {
        if (!$hidden && !$trash) return $this->getCounters($userId, $context);
        $filters = ['hidden' => $hidden, 'trash' => $trash];
        [$conditions, $parameters] = $this->notificationQuery($userId, $filters, $context);
        $settings = (new SystemSettingModel())->all();
        $retentionDays = max(1, (int)($settings['notification_trash_retention_days'] ?? 60));
        $expiringCutoff = max(0, $retentionDays - 7);
        $parameters['expiration_cutoff'] = (new DateTimeImmutable())->modify("-{$expiringCutoff} days")->format('Y-m-d H:i:s');
        $statement = $this->connection()->prepare(
            "SELECT COUNT(*) total,
                    COALESCE(SUM(n.is_read = 0), 0) unread,
                    COALESCE(SUM(YEARWEEK(n.created_at, 1) = YEARWEEK(CURRENT_DATE(), 1)), 0) week,
                    COALESCE(SUM(n.deleted_at IS NOT NULL AND n.deleted_at <= :expiration_cutoff), 0) expiring
             FROM notifications n WHERE " . implode(' AND ', $conditions)
        );
        $statement->execute($parameters);
        $row = $statement->fetch() ?: [];
        return ['total' => (int) ($row['total'] ?? 0), 'unread' => (int) ($row['unread'] ?? 0), 'week' => (int) ($row['week'] ?? 0), 'expiring' => (int) ($row['expiring'] ?? 0)];
    }

    public function purgeExpiredTrashForUser(int $userId, ?int $days = null): int
    {
        if ($days === null) {
            $settings = (new SystemSettingModel())->all();
            $days = (int)($settings['notification_trash_retention_days'] ?? 60);
        }
        $days = max(1, min($days, 365));
        $statement = $this->connection()->prepare(
            'DELETE FROM notifications WHERE user_id = :user_id AND deleted_at IS NOT NULL AND deleted_at < :cutoff'
        );
        $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue('cutoff', (new DateTimeImmutable())->modify("-{$days} days")->format('Y-m-d H:i:s'));
        $statement->execute();

        return $statement->rowCount();
    }

    /** Purga global explícita para tareas de mantenimiento, nunca para GET de lectura. */
    public function purgeExpiredTrash(?int $days = null): int
    {
        if ($days === null) {
            $days = (new SystemSettingModel())->retentionDays('notification_trash_retention_days');
        }
        $days = max(1, min($days, 365));
        $statement = $this->connection()->prepare(
            'DELETE FROM notifications
             WHERE deleted_at IS NOT NULL
               AND deleted_at < :cutoff'
        );
        $statement->execute([
            'cutoff' => (new DateTimeImmutable())->modify("-{$days} days")->format('Y-m-d H:i:s'),
        ]);

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

    public function markAsRead(int $id, int $userId): bool
    {
        return $this->updateReadState($id, $userId, true);
    }

    public function markAsUnread(int $id, int $userId): bool
    {
        return $this->updateReadState($id, $userId, false);
    }

    public function softDelete(int $id, int $userId): bool
    {
        $statement = $this->connection()->prepare(
            'UPDATE notifications
             SET archived_at = COALESCE(archived_at, NOW()), updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND archived_at IS NULL AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        return $statement->rowCount() === 1;
    }

    public function restore(int $id, int $userId): bool
    {
        $statement = $this->connection()->prepare(
            'UPDATE notifications
             SET archived_at = NULL, deleted_at = NULL, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND (archived_at IS NOT NULL OR deleted_at IS NOT NULL)'
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        return $statement->rowCount() === 1;
    }

    public function moveToTrash(int $id, int $userId): bool
    {
        $statement = $this->connection()->prepare(
            'UPDATE notifications
             SET archived_at = NULL, deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        return $statement->rowCount() === 1;
    }
    // Final de cambios de estado y ciclo de eliminación

    // Inicio de soporte demostrativo
    // Proporciona datos y contadores temporales mientras la base de datos no está habilitada.
    public function countUnread(int $userId, string $context = ''): int
    {
        return $this->getCounters($userId, $context)['unread'];
    }

    public function markAllAsRead(int $userId, string $context = ''): int
    {
        [$conditions, $parameters] = $this->notificationQuery($userId, ['status' => 'unread'], $context);
        $sql = 'UPDATE notifications n SET is_read = 1, read_at = NOW(), updated_at = NOW() WHERE ' . implode(' AND ', $conditions);
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement->rowCount();
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
        if ($row['project_id'] !== null && array_key_exists('project_status', $row) && $row['project_status'] === null) {
            $row['action_url'] = null;
        }
        $projectName = trim((string) ($row['project_name'] ?? ''));
        $row['project_name'] = $projectName !== '' ? $projectName : (string) ($metadata['project_name'] ?? 'Notificacion general');

        if (isset($row['project_status'])) {
            $row['project_status'] = project_academic_labels((string) $row['project_status'])['status'];
        }

        // Clean historical titles and messages for presentation (Backward Compatibility)
        if (!empty($row['message']) && !empty($row['project_name'])) {
            $code = !empty($row['project_code']) ? (string)$row['project_code'] : (!empty($metadata['project_code']) ? (string)$metadata['project_code'] : '');
            $projectLabel = $code !== '' ? $code . ' · ' . $row['project_name'] : $row['project_name'];

            $search = [
                'El proyecto ' . $projectLabel . ' fue enviado para tu revisión.' => 'Se ha registrado el proyecto para tu revisión.',
                'El proyecto ' . $projectLabel . ' fue enviado a Tribunal para su defensa.' => 'El proyecto ha sido derivado al Tribunal para la sustentación de la defensa.',
                'El proyecto ' . $projectLabel . ' inició la etapa de defensa.' => 'Se ha dado inicio a la etapa de defensa del proyecto para tu evaluación.',
                'El proyecto ' . $projectLabel . ' alcanzó el estado “' => 'El proyecto ha alcanzado el estado de “',
                'El proyecto ' . $projectLabel . ' fue publicado correctamente en el Repositorio Académico.' => 'El documento final ha sido publicado correctamente en el Repositorio Académico.',
                'El proyecto ' . $projectLabel . ' ha sido publicado en el Repositorio institucional.' => 'El trabajo de titulación ha sido publicado en el Repositorio institucional.',
                'El proyecto ' . $projectLabel . ' no fue aprobado por el Tribunal.' => 'El Tribunal ha registrado la calificación. El proyecto no ha sido aprobado.',
                'El Tribunal aprobó el proyecto ' . $projectLabel . '. El proceso continuará hacia publicación.' => 'El Tribunal ha aprobado tu proyecto de titulación. El trámite continuará con la fase de publicación.',
                'El Tribunal aprobó el proyecto ' . $code . '. El proceso continuará hacia publicación.' => 'El Tribunal ha aprobado tu proyecto de titulación. El trámite continuará con la fase de publicación.',
                'El proyecto ' . $code . ' ha sido publicado en el Repositorio institucional.' => 'El trabajo de titulación ha sido publicado en el Repositorio institucional.',
                'El proyecto ' . $code . ' no fue aprobado por el Tribunal.' => 'El Tribunal ha registrado la calificación. El proyecto no ha sido aprobado.',
            ];

            // Reemplazo del patrón de entregas
            $deliveryPattern = '/El proyecto ' . preg_quote($projectLabel, '/') . ' envió la entrega (\d+) con (\d+) documento\(s\) para tu revisión\./u';
            if (preg_match($deliveryPattern, $row['message'], $matches)) {
                $fileCount = (int)$matches[2];
                $row['message'] = "Se ha enviado una nueva entrega para tu revisión. Incluye " . $fileCount . " " . ($fileCount === 1 ? "documento pendiente" : "documentos pendientes") . " de revisar.";
            } else {
                foreach ($search as $target => $replacement) {
                    if (str_contains($row['message'], $target)) {
                        $row['message'] = str_replace($target, $replacement, $row['message']);
                    }
                }
            }

            // Asignación de tutor y tribunal
            $tutorPattern = '/Has sido asignado como tutor del proyecto ' . preg_quote($projectLabel, '/') . '/u';
            if (preg_match($tutorPattern, $row['message'])) {
                $row['message'] = 'Has sido asignado como tutor de este proyecto.';
            }
            $tribunalPattern = '/Has sido asignado al Tribunal del proyecto ' . preg_quote($projectLabel, '/') . '/u';
            if (preg_match($tribunalPattern, $row['message'])) {
                $row['message'] = 'Has sido asignado como evaluador en el Tribunal de este proyecto.';
            }
            $tribunalConfPattern = '/El Tribunal del proyecto ' . preg_quote($projectLabel, '/') . ' fue conformado y el proyecto avanzó a la etapa de defensa\./u';
            if (preg_match($tribunalConfPattern, $row['message'])) {
                $row['message'] = 'El Tribunal evaluador ha sido conformado. El proyecto avanza a la etapa de defensa.';
            }
            $tribunalConfTutorPattern = '/El Tribunal del proyecto ' . preg_quote($projectLabel, '/') . ' fue conformado\./u';
            if (preg_match($tribunalConfTutorPattern, $row['message'])) {
                $row['message'] = 'El Tribunal evaluador del proyecto ha sido conformado.';
            }
        }

        // Clean historical titles if any match technical names
        if (!empty($row['title'])) {
            $titleReplacements = [
                'Proyecto enviado a revisión' => 'Nueva entrega para revisión',
                'Has sido asignado como tutor' => 'Asignación como tutor',
                'Asignación a tribunal' => 'Asignación a tribunal',
                'Etapa de defensa iniciada' => 'Defensa de proyecto iniciada',
            ];
            if (isset($titleReplacements[$row['title']])) {
                $row['title'] = $titleReplacements[$row['title']];
            }
            if (str_starts_with($row['title'], 'Proyecto aprobado')) {
                $row['title'] = str_replace('Proyecto aprobado', 'Estado de proyecto aprobado', $row['title']);
            }
        }

        return $row;
    }
    private function cleanAccents(string $str): string
    {
        return str_ireplace(
            ['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ'],
            ['a','e','i','o','u','n','a','e','i','o','u','n'],
            mb_strtolower($str, 'UTF-8')
        );
    }
    // Final de creación y normalización de registros
}
