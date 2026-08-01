<?php

declare(strict_types=1);

/** Adapta la auditoría existente del proyecto al panel administrativo compartido. */
final class ProjectAuditHistoryModel
{
    private const STATUS_LABELS = [
        'development' => 'En desarrollo', 'under_review' => 'En revisión',
        'changes_required' => 'Requiere cambios', 'approved' => 'Aprobado',
        'defense' => 'En tribunal', 'tribunal_approved' => 'Aprobado por el Tribunal',
        'published' => 'Publicado',
    ];

    public function forProject(int $projectId, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(20, $limit));
        $offset = max(0, $offset);
        $count = Database::connection()->prepare('SELECT COUNT(*) FROM project_audit_log WHERE project_id=:project_id');
        $count->execute(['project_id' => $projectId]);
        $total = (int) $count->fetchColumn();
        $statement = Database::connection()->prepare(
            'SELECT audit.*,actor.full_name actor_name,actor.email actor_email,
                    GROUP_CONCAT(DISTINCT roles.name ORDER BY roles.name SEPARATOR ", ") actor_roles
             FROM project_audit_log audit
             LEFT JOIN users actor ON actor.id=audit.user_id
             LEFT JOIN user_roles actor_roles ON actor_roles.user_id=actor.id
             LEFT JOIN roles ON roles.id=actor_roles.role_id
             WHERE audit.project_id=:project_id
             GROUP BY audit.id
             ORDER BY audit.created_at DESC,audit.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('project_id', $projectId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        return [
            'items' => array_map([$this, 'normalize'], $rows),
            'has_more' => $hasMore,
            'next_offset' => $offset + count($rows),
            'total_count' => $total,
            'incomplete_count' => 0,
        ];
    }

    private function normalize(array $row): array
    {
        $previous = $this->decode($row['previous_state'] ?? null);
        $next = $this->decode($row['new_state'] ?? null);
        $changes = $this->changes($previous, $next);
        $metadata = $next;
        unset($metadata['_history_changes']);
        $utc = new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC'));
        $config = require APP_PATH . '/config/app.php';
        $timezone = (string) ($config['timezone'] ?? 'America/Guayaquil');
        if (!in_array($timezone, timezone_identifiers_list(), true)) $timezone = 'America/Guayaquil';
        $local = $utc->setTimezone(new DateTimeZone($timezone));
        return [
            'id' => (int) $row['id'], 'action' => (string) $row['action'],
            'action_label' => $this->actionLabel((string) $row['action']),
            'summary' => 'Proyecto',
            'actor' => [
                'name' => trim((string) ($row['actor_name'] ?? '')) ?: 'Sistema institucional',
                'email' => (string) ($row['actor_email'] ?? ''),
                'role' => (string) ($row['actor_roles'] ?? ''),
            ],
            'created_at' => $local->format(DateTimeInterface::ATOM),
            'created_at_label' => $local->format('d/m/Y · H:i'),
            'changes' => $changes, 'has_details' => $changes !== [],
            'legacy_without_details' => false, 'cleanup' => null,
            'details' => $this->detailRows($metadata, (string) ($row['reason'] ?? '')),
        ];
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function changes(array $previous, array $next): array
    {
        $history = (array) ($next['_history_changes'] ?? []);
        if ($history !== []) return array_values(array_filter(array_map(fn (array $change): ?array =>
            isset($change['field']) ? [
                'field' => (string) $change['field'], 'label' => (string) $change['field'],
                'old' => $this->displayValue((string) ($change['from'] ?? '')),
                'new' => $this->displayValue((string) ($change['to'] ?? '')),
            ] : null, $history)));
        $ignored = ['_history_changes', 'description_origin', 'edited_by_administrator'];
        $changes = [];
        foreach (array_unique([...array_keys($previous), ...array_keys($next)]) as $field) {
            if (in_array($field, $ignored, true) || !array_key_exists($field, $previous) || !array_key_exists($field, $next)) continue;
            if ($previous[$field] === $next[$field]) continue;
            $changes[] = [
                'field' => (string) $field, 'label' => $this->fieldLabel((string) $field),
                'old' => $this->displayValue($previous[$field], (string) $field), 'new' => $this->displayValue($next[$field], (string) $field),
            ];
        }
        return $changes;
    }

    private function detailRows(array $metadata, string $reason): array
    {
        $rows = [];
        if (trim($reason) !== '') $rows[] = ['key' => 'reason', 'label' => 'Motivo', 'value' => trim($reason)];
        foreach ($metadata as $key => $value) {
            if ($key === '_history_changes' || str_ends_with((string) $key, '_id') || is_array($value)) continue;
            if ($value === null || $value === '') continue;
            $rows[] = ['key' => (string) $key, 'label' => $this->fieldLabel((string) $key), 'value' => $this->displayValue($value, (string) $key)];
        }
        return $rows;
    }

    private function displayValue(mixed $value, string $field = ''): string
    {
        if (is_bool($value)) return $value ? 'Sí' : 'No';
        if ($field === 'size_bytes' && is_numeric($value)) return ArchiveService::formatBytes((int) $value);
        $text = trim((string) $value);
        return self::STATUS_LABELS[$text] ?? ($text !== '' ? $text : 'Sin asignar');
    }

    private function fieldLabel(string $field): string
    {
        return [
            'status' => 'Estado', 'title' => 'Título', 'subtitle' => 'Descripción breve',
            'summary' => 'Descripción pública', 'is_available' => 'Disponibilidad',
            'original_name' => 'Archivo', 'presentation_file_id' => 'Archivo de presentación',
            'name' => 'Nombre', 'size_bytes' => 'Tamaño',
            'old_value' => 'Valor anterior', 'new_value' => 'Valor nuevo',
        ][$field] ?? ucfirst(str_replace(['_', '.'], ' ', $field));
    }

    private function actionLabel(string $action): string
    {
        return [
            'project_created' => 'Registró el proyecto', 'project_updated' => 'Editó la información del proyecto',
            'project_description_updated' => 'Editó la descripción pública',
            'project_published' => 'Publicó el proyecto', 'project_republished' => 'Restauró la publicación',
            'project_unpublished' => 'Retiró la publicación', 'project_availability_changed' => 'Cambió la disponibilidad',
            'project_trashed' => 'Envió el proyecto a Papelera', 'project_restored' => 'Restauró el proyecto',
            'project.file_added' => 'Agregó un archivo al proyecto', 'project.file_replaced' => 'Reemplazó un archivo del proyecto',
            'project.file_removed' => 'Retiró un archivo del proyecto', 'project.file_restored' => 'Restauró un archivo del proyecto',
            'project.file_purged' => 'Eliminó definitivamente un archivo',
            'project_file_added' => 'Agregó un archivo al proyecto', 'project_file_replaced' => 'Reemplazó un archivo del proyecto',
            'project_file_removed' => 'Retiró un archivo del proyecto', 'project_file_restored' => 'Restauró un archivo del proyecto',
            'project.presentation_selected' => 'Seleccionó el archivo de presentación',
            'project.presentation_changed' => 'Cambió el archivo de presentación',
            'project.presentation_removed' => 'Quitó el archivo de presentación',
            'delivery_submitted' => 'Registró una entrega académica',
            'project_approved' => 'Aprobó el proyecto',
            'project_tribunal_approved' => 'Registró la aprobación del tribunal',
            'tribunal_approved' => 'Registró la aprobación del tribunal',
        ][$action] ?? ucfirst(str_replace(['_', '.'], ' ', $action));
    }
}
