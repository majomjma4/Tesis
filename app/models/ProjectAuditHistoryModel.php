<?php

declare(strict_types=1);

/** Adapta la auditoría existente del proyecto al panel administrativo compartido. */
final class ProjectAuditHistoryModel
{
    private string $context = 'repository';
    private const STATUS_LABELS = [
        'development' => 'En desarrollo', 'under_review' => 'En revisión',
        'changes_required' => 'Correcciones solicitadas', 'corrections_requested' => 'Correcciones solicitadas',
        'approved' => 'Aprobado',
        'defense' => 'En tribunal', 'tribunal_approved' => 'Aprobado por el Tribunal',
        'published' => 'Publicado', 'registration' => 'Registro', 'pending' => 'Pendiente', 'rejected' => 'Rechazada',
        'addressed' => 'Atendida', 'resolved' => 'Resuelta', 'submitted' => 'Enviada',
    ];
    private const VALUE_LABELS = [
        'pis' => 'Proyecto integrador de saberes', 'thesis' => 'Titulación',
        'thesis_profile' => 'Perfil de tesis', 'practice' => 'Prácticas preprofesionales',
        'community' => 'Proyecto de vinculación', 'modification' => 'Modificación',
        'correction' => 'Corrección', 'change' => 'Cambio', 'document' => 'Documento',
        'published_modification' => 'Modificación de proyecto publicado',
    ];
    private const TECHNICAL_FIELDS = [
        'checksum', 'checksum_sha256', 'previous_checksum', 'new_checksum', 'hash', 'previous_hash', 'new_hash',
        'project_id', 'delivery_id', 'request_id', 'file_id', 'change_id', 'version_id', 'entity_id',
        'user_id', 'actor_id', 'actor_user_id', 'previous_file_id', 'new_file_id', 'presentation_file_id',
        'mime_type', 'extension', 'storage_name', 'storage_path', 'academic_history', 'publication_final',
        'is_package', 'schema_version', 'lock_version', 'description_origin', 'edited_by_administrator',
        'origin', 'deleted_at', 'purged_at', 'removed_at',
    ];
    /**
     * Explicit administrative mutations. Ordinary academic actions are
     * intentionally excluded even when the actor also has a teaching role.
     */
    private const ADMINISTRATIVE_ACTIONS = [
        'project_updated', 'project_description_updated', 'project_authors_updated',
        'project_published', 'project_republished', 'project_unpublished',
        'project_withdrawn', 'project_reincorporated', 'project_availability_changed',
        'project_publication_reverted', 'project_qa_prepared', 'project_trashed',
        'project_restored', 'project_status_changed', 'project_approved',
        'project.file_added', 'project.file_replaced', 'project.file_removed',
        'project.file_restored', 'project.file_purged',
        'project_file_added', 'project_file_replaced', 'project_file_removed',
        'project_file_restored', 'project_file_purged',
        'project.presentation_selected', 'project.presentation_changed',
        'project.presentation_removed', 'project_publication_file_added',
        'project_publication_file_replaced', 'project_publication_file_excluded',
        'project_document_version_created', 'project_document_versions_archived',
        'project_adjustment_request_approved', 'project_adjustment_request_rejected',
        'project_reopened_for_adjustment', 'repository_direct_publish',
    ];

    public function forProject(int $projectId, int $limit = 20, int $offset = 0, string $context = 'repository'): array
    {
        $this->context = $context === 'academic_management' ? 'academic_management' : 'repository';
        $limit = max(1, min(20, $limit));
        $offset = max(0, $offset);
        $administrativeWhere = $this->administrativeWhere();
        $count = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM project_audit_log audit
             LEFT JOIN users actor ON actor.id=audit.user_id
             WHERE audit.project_id=:project_id AND ' . $administrativeWhere
        );
        $count->execute(['project_id' => $projectId]);
        $total = (int) $count->fetchColumn();
        $statement = Database::connection()->prepare(
            'SELECT audit.*,actor.full_name actor_name,actor.email actor_email,
                    GROUP_CONCAT(DISTINCT roles.code ORDER BY roles.code SEPARATOR ", ") actor_role_codes
             FROM project_audit_log audit
             LEFT JOIN users actor ON actor.id=audit.user_id
             LEFT JOIN user_roles actor_roles ON actor_roles.user_id=actor.id
             LEFT JOIN roles ON roles.id=actor_roles.role_id
             WHERE audit.project_id=:project_id AND ' . $administrativeWhere . '
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

    /**
     * The audit schema predates persistence of the Admin Mode session flag.
     * Use the durable administrative actor plus an explicit action allowlist,
     * with guards for codes that are also used by ordinary student flows.
     */
    private function administrativeWhere(): string
    {
        $actions = implode(',', array_map(
            static fn (string $action): string => "'" . str_replace("'", "''", $action) . "'",
            self::ADMINISTRATIVE_ACTIONS
        ));

        return "actor.is_admin=1
            AND audit.action IN ({$actions})
            AND NOT (
                audit.action='project_description_updated'
                AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(audit.new_state,'$.edited_by_administrator')),'') NOT IN ('1','true')
            )
            AND NOT (
                audit.action='project_published'
                AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(audit.new_state,'$.context')),'')='student_publication'
            )";
    }

    private function normalize(array $row): array
    {
        $previous = $this->decode($row['previous_state'] ?? null);
        $next = $this->decode($row['new_state'] ?? null);
        $changes = $this->changes($previous, $next);
        $metadata = $next;
        unset($metadata['_history_changes']);
        $details = $this->detailRows($metadata, (string) ($row['reason'] ?? ''));
        $utc = new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC'));
        $config = require APP_PATH . '/config/app.php';
        $timezone = (string) ($config['timezone'] ?? 'America/Guayaquil');
        if (!in_array($timezone, timezone_identifiers_list(), true)) $timezone = 'America/Guayaquil';
        $local = $utc->setTimezone(new DateTimeZone($timezone));
        return [
            'id' => (int) $row['id'], 'action' => (string) $row['action'],
            'action_label' => $this->actionLabel((string) $row['action']),
            'summary' => $this->context === 'academic_management' ? 'Proyecto académico' : 'Proyecto',
            'actor' => [
                'name' => trim((string) ($row['actor_name'] ?? '')) ?: 'Sistema institucional',
                'email' => (string) ($row['actor_email'] ?? ''),
                'role' => $this->roleLabels((string) ($row['actor_role_codes'] ?? '')),
            ],
            'created_at' => $local->format(DateTimeInterface::ATOM),
            'created_at_label' => $local->format('d/m/Y · H:i'),
            'changes' => $changes, 'has_details' => $changes !== [] || $details !== [],
            'legacy_without_details' => false, 'cleanup' => null,
            'details' => $details,
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
        if ($history !== []) {
            $changes = [];
            foreach ($history as $change) {
                $field = (string) ($change['field'] ?? '');
                if ($field === '' || $this->isTechnicalField($field)) continue;
                $changes[] = [
                    'field' => $field, 'label' => $this->fieldLabel($field),
                    'old' => $this->displayValue($change['from'] ?? '', $field),
                    'new' => $this->displayValue($change['to'] ?? '', $field),
                ];
            }
            return $changes;
        }
        $ignored = ['_history_changes', 'description_origin', 'edited_by_administrator'];
        $changes = [];
        foreach (array_unique([...array_keys($previous), ...array_keys($next)]) as $field) {
            if (in_array($field, $ignored, true) || $this->isTechnicalField((string) $field)
                || !array_key_exists($field, $previous) || !array_key_exists($field, $next)) continue;
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
        $reasonAdded = trim($reason) !== '';
        if ($reasonAdded) $rows[] = ['key' => 'reason', 'label' => 'Motivo', 'value' => trim($reason)];
        $previousVersion = $metadata['previous_version_number'] ?? null;
        $newVersion = $metadata['new_version_number'] ?? null;
        if ($previousVersion !== null || $newVersion !== null) {
            $from = $previousVersion === null ? 'Sin asignar' : trim((string) $previousVersion);
            $to = $newVersion === null ? 'Sin asignar' : trim((string) $newVersion);
            $rows[] = ['key' => 'version_transition', 'label' => 'Versión', 'value' => $from . ' → ' . $to];
        }
        foreach ($metadata as $key => $value) {
            $key = (string) $key;
            if (in_array($key, ['_history_changes', 'context', 'previous_version_number', 'new_version_number'], true)
                || $this->isTechnicalField($key) || is_array($value)) continue;
            if ($value === null || $value === '') continue;
            if ($key === 'reason' && $reasonAdded) continue;
            $rows[] = ['key' => $key, 'label' => $this->fieldLabel($key), 'value' => $this->displayValue($value, $key)];
        }
        return $rows;
    }

    private function displayValue(mixed $value, string $field = ''): string
    {
        $normalizedField = $this->normalizeFieldKey($field);
        if (is_bool($value)) {
            if ($normalizedField === 'removed') return $value ? 'Retirado' : 'Disponible';
            return $value ? 'Sí' : 'No';
        }
        if (in_array($normalizedField, ['is_available', 'active', 'included'], true) && is_numeric($value)) {
            return (int) $value === 1 ? 'Sí' : 'No';
        }
        if ($normalizedField === 'size_bytes' && is_numeric($value)) return ArchiveService::formatBytes((int) $value);
        $text = trim((string) $value);
        if ($text === '') return 'Sin asignar';
        return self::STATUS_LABELS[$text] ?? self::VALUE_LABELS[$text] ?? $text;
    }

    private function fieldLabel(string $field): string
    {
        $field = $this->normalizeFieldKey($field);
        return [
            'code' => 'Código', 'type' => 'Tipo', 'type_code' => 'Tipo de proyecto',
            'participants' => 'Participantes', 'files' => 'Archivos', 'status' => 'Estado',
            'project_status' => 'Estado del proyecto', 'delivery_status' => 'Estado de la entrega',
            'current_stage' => 'Etapa', 'stage' => 'Etapa', 'stage_label' => 'Etapa',
            'qa_preparation' => 'Preparación', 'title' => 'Título', 'subtitle' => 'Descripción breve',
            'summary' => $this->context === 'academic_management' ? 'Descripción' : 'Descripción pública',
            'is_available' => 'Disponibilidad', 'active' => 'Disponibilidad', 'included' => 'Incluido',
            'original_name' => 'Archivo', 'file_name' => 'Archivo',
            'name' => 'Nombre', 'size_bytes' => 'Tamaño', 'restore_hours' => 'Plazo de restauración (horas)',
            'version_number' => 'Versión', 'version_transition' => 'Versión',
            'previous_name' => 'Nombre anterior', 'new_name' => 'Nombre nuevo',
            'previous_status' => 'Estado anterior', 'new_status' => 'Estado nuevo',
            'original_published_at' => 'Fecha original de publicación', 'publication_reverted_at' => 'Fecha de reversión',
            'previous_available' => 'Disponibilidad anterior', 'new_availability' => 'Disponibilidad nueva',
            'previous_file' => 'Archivo anterior', 'new_file' => 'Archivo nuevo',
            'old_file_name' => 'Archivo anterior', 'new_file_name' => 'Archivo nuevo',
            'presentation_previous' => 'Archivo anterior', 'presentation_new' => 'Archivo nuevo',
            'project_keywords' => 'Clasificación', 'keywords' => 'Clasificación',
            'entity_label' => 'Entidad', 'area' => 'Área', 'project_type' => 'Tipo de proyecto',
            'career' => 'Carrera', 'academic_period' => 'Período académico', 'request_type' => 'Tipo de solicitud',
            'reviewed_documents' => 'Documentos revisados', 'submitted_file_count' => 'Archivos enviados',
            'approved' => 'Aprobados', 'under_review' => 'En revisión',
            'corrections_requested' => 'Correcciones solicitadas', 'observation_count' => 'Observaciones',
            'delivery_number' => 'Número de entrega', 'removed' => 'Estado del archivo',
            'archived_count' => 'Versiones archivadas', 'unavailable_count' => 'Versiones no disponibles',
            'held_count' => 'Versiones conservadas', 'reason' => 'Motivo', 'declared_summary' => 'Descripción del cambio',
            'decision' => 'Decisión', 'result' => 'Resultado', 'attempt' => 'Intento', 'notes' => 'Notas',
            'document_status' => 'Estado del documento', 'status_label' => 'Estado', 'published_at' => 'Fecha de publicación',
            'old_value' => 'Valor anterior', 'new_value' => 'Valor nuevo',
        ][$field] ?? 'Dato';
    }

    private function actionLabel(string $action): string
    {
        return AuditLabelFormatter::projectAction($action);
    }

    private function normalizeFieldKey(string $field): string
    {
        return strtolower(str_replace([' ', '.', '-'], '_', trim($field)));
    }

    private function isTechnicalField(string $field): bool
    {
        $normalized = $this->normalizeFieldKey($field);
        return in_array($normalized, self::TECHNICAL_FIELDS, true)
            || str_contains($normalized, 'checksum')
            || str_contains($normalized, 'hash')
            || preg_match('/(^|_)id($|_)/', $normalized) === 1;
    }

    private function roleLabels(string $roles): string
    {
        $labels = [];
        foreach (explode(',', $roles) as $role) {
            $role = strtolower(trim($role));
            if ($role === '') continue;
            $labels[] = [
                'student' => 'Estudiante', 'teacher' => 'Docente',
                'administrator' => 'Administrador', 'admin' => 'Administrador',
            ][$role] ?? 'Usuario';
        }
        return implode(' · ', array_values(array_unique($labels))) ?: 'Usuario';
    }
}
