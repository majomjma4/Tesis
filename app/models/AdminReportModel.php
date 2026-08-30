<?php

declare(strict_types=1);

final class AdminReportModel
{
    private const OPERATIONAL_STATUSES = ['development','under_review','approved','defense','tribunal_approved','published'];

    public function dashboard(string $from, string $to, array $pagination = []): array
    {
        $db = Database::connection();
        $range = ['from'=>$from.' 00:00:00','to'=>$to.' 23:59:59'];
        
        $summary = [
            'users'=>$this->count($db,'SELECT COUNT(*) FROM users WHERE created_at BETWEEN :from AND :to',$range),
            'projects'=>$this->count($db,'SELECT COUNT(*) FROM projects WHERE created_at BETWEEN :from AND :to',$range),
            'deliveries'=>$this->count($db,'SELECT COUNT(*) FROM project_deliveries WHERE submitted_at BETWEEN :from AND :to',$range),
            'actions'=>0,
        ];
        $roles = $db->query("SELECT r.name label,
                                    SUM(CASE WHEN u.status = 'active' AND u.deleted_at IS NULL THEN 1 ELSE 0 END) active,
                                    SUM(CASE WHEN (u.status = 'inactive' OR u.status = 'blocked') AND u.deleted_at IS NULL THEN 1 ELSE 0 END) blocked,
                                    SUM(CASE WHEN u.deleted_at IS NOT NULL THEN 1 ELSE 0 END) trash,
                                    COUNT(u.id) total
                             FROM roles r
                             LEFT JOIN user_roles ur ON ur.role_id = r.id
                             LEFT JOIN users u ON u.id = ur.user_id AND u.purged_at IS NULL
                             GROUP BY r.id, r.name
                             ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
        $statusRows = $db->query("SELECT status,COUNT(*) total FROM projects WHERE deleted_at IS NULL AND status IN ('development','under_review','approved','defense','tribunal_approved','published') GROUP BY status")->fetchAll();
        $statusCounts = array_column($statusRows,'total','status');
        $statuses = array_map(static function(string $status) use ($statusCounts): array {
            $labels = project_academic_labels($status);
            return ['code'=>$status,'label'=>$labels['status'],'total'=>(int)($statusCounts[$status]??0),'url'=>$status==='published'?route('admin-repository'):route('projects').'&status='.rawurlencode($status)];
        }, self::OPERATIONAL_STATUSES);
        $reviewCounts = (new ProjectReviewSituationService())->aggregate($db,false);
        $reviewSituations = [
            ['code'=>'pending','label'=>'Con observaciones pendientes','total'=>$reviewCounts['pending'],'url'=>route('projects').'&review_situation=pending'],
            ['code'=>'addressed','label'=>'Observaciones atendidas','total'=>$reviewCounts['addressed'],'url'=>route('projects').'&review_situation=addressed'],
            ['code'=>'none','label'=>'Sin observaciones registradas','total'=>$reviewCounts['none'],'url'=>route('projects').'&review_situation=none'],
        ];
        

        
        $page = max(1,(int)($pagination['page'] ?? PaginationService::request()['page'] ?? 1));
        $perPage = PaginationService::normalizeSize((int)($pagination['size'] ?? PaginationService::request()['size'] ?? 10));
        $total = $this->countAuditEvents($from, $to, 2);
        $pages = (int)ceil($total / max(1, $perPage));
        $page = min($page, max(1, $pages));
        $offset = ($page - 1) * $perPage;
        $items = $this->processEvents($this->fetchAuditEvents($from, $to, 2, $perPage, $offset));
        $summary['actions'] = $total;

        $paginationData = [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, $pages),
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total)
        ];

        return compact('summary','roles','statuses','reviewSituations') + ['activity' => $items, 'pagination' => $paginationData];
    }

    /** Returns the canonical, reportable audit universe for the requested base range. */
    public function auditEvents(string $from, string $to, int $maxRelevance = 3): array
    {
        return $this->processEvents($this->fetchAuditEvents($from, $to, $maxRelevance));
    }

    private function processEvents(array $events): array
    {
        $filtered = [];

        foreach ($events as $event) {
            $action = (string)$event['action'];
            $entityType = (string)$event['entity_type'];
            $actor = $event['actor'] ?: 'Sistema';
            $createdAt = (string)$event['created_at'];

            // 1. Ocultar acciones internas/técnicas o demasiado genéricas
            // 2. Consolidar duplicados lógicos donde un evento se registra como 'project' y como 'project_file'
            // Si la entidad es 'project' para una acción de archivo, descartar la duplicada manteniendo la de 'project_file'
            // Mapear traducción limpia
            $elementLabel = trim((string)($event['element_label'] ?? ''));
            $presentation = AuditLabelFormatter::present($action, (string)($event['action_label'] ?? ''));
            $actionLabel = $presentation['action_label'];
            $details = json_decode((string)($event['details'] ?? ''), true);
            if (is_array($details)) $actionLabel = $this->contextualActionLabel($action, $actionLabel, $elementLabel, $details);
            $entityLabel = $elementLabel !== '' ? $elementLabel : AuditLabelFormatter::context($entityType);
            
            // Llave de deduplicación basada en actor, acción traducida, entidad y ventana de 3 segundos
            if ($entityType === 'project' && array_key_exists('project_available', $event) && (int)$event['project_available'] !== 1) {
                $entityLabel = 'Proyecto no disponible';
            }
            $module = trim((string)($event['module'] ?? ''));
            if ($module === '') $module = AuditLabelFormatter::context($entityType);

            $filtered[] = [
                'action' => $action,
                'action_label' => $actionLabel,
                'relevance' => $presentation['relevance'],
                'entity_type' => $entityType,
                'entity_id' => $event['entity_id'] ?? null,
                'entity_label' => $entityLabel,
                'module' => $module,
                'element_label' => $elementLabel,
                'created_at' => $createdAt,
                'created_at_local' => $this->formatLocalDateTime($createdAt),
                'actor' => $actor
            ];
        }

        return $filtered;
    }

    /** @deprecated El flujo activo usa AuditLabelFormatter::present(). */
    public function translateAction(string $action, string $entityType): array
    {
        static $map = [
            'user_created' => 'Usuario registrado',
            'user_updated' => 'Información de usuario actualizada',
            'user_status_changed' => 'Estado de usuario modificado',
            'password_reset' => 'Contraseña restablecida',
            'profile_updated' => 'Perfil actualizado',
            'project_created' => 'Proyecto creado',
            'project_updated' => 'Proyecto actualizado',
            'project_status_changed' => 'Cambio de estado en proyecto',
            'project_approved' => 'Proyecto aprobado',
            'project_published' => 'Proyecto publicado en repositorio',
            'project_republished' => 'Publicación de proyecto actualizada',
            'project_unpublished' => 'Proyecto retirado del repositorio',
            'project_trashed' => 'Proyecto movido a papelera',
            'project_restored' => 'Proyecto restaurado',
            'project_adjustment_request_created' => 'Solicitud de ajuste creada',
            'project_qa_prepared' => 'Expediente preparado para revisión',
            'project.file_added' => 'Documento añadido al proyecto',
            'project.file_removed' => 'Documento eliminado del proyecto',
            'project.file_restored' => 'Documento restaurado en el proyecto',
            'project.file_replaced' => 'Documento reemplazado en el proyecto',
            'project.file_purged' => 'Documento eliminado definitivamente',
            'project.presentation_changed' => 'Archivo de presentación actualizado',
            'project.presentation_removed' => 'Archivo de presentación removido',
            'delivery_submitted' => 'Nueva entrega recibida',
            'notification_sent' => 'Comunicado o notificación enviada',
            'settings_updated' => 'Configuración del sistema actualizada',
            'academic_period_closed' => 'Periodo académico cerrado',
            'academic_period_activated' => 'Periodo académico activado',
            'academic_period_planned' => 'Periodo académico planificado',
            'academic_period_closure_reverted' => 'Cierre de periodo revertido',
            'academic_type_activated' => 'Tipo de proyecto activado',
            'academic_type_deactivated' => 'Tipo de proyecto desactivado',
            'academic_type_updated' => 'Tipo de proyecto actualizado',
            'support_material_published' => 'Material de apoyo publicado',
            'support_material_withdrawn' => 'Material de apoyo retirado',
            'support_material_updated' => 'Material de apoyo actualizado',
            'support_material_restored' => 'Material de apoyo restaurado',
            'support_material_availability_changed' => 'Disponibilidad de material cambiada',
            'support_material.file_added' => 'Archivo añadido a material de apoyo',
            'support_material.file_removed' => 'Archivo removido de material de apoyo',
            'support_material.file_replaced' => 'Archivo reemplazado en material de apoyo',
            'support_material.file_restored' => 'Archivo restaurado en material de apoyo',
            'support_material.file_purged' => 'Archivo depurado en material de apoyo',
            'support_material.presentation_selected' => 'Presentación de material seleccionada',
            'support_material.presentation_changed' => 'Presentación de material cambiada',
            'support_material.presentation_removed' => 'Presentación de material removida',
            'support_material.trashed' => 'Material de apoyo movido a papelera',
            'support_material.updated' => 'Material de apoyo actualizado',
            'support_material.history_cleaned' => 'Historial de material depurado',
        ];

        static $entityMap = [
            'user' => 'Usuario',
            'project' => 'Proyecto',
            'project_file' => 'Documento de proyecto',
            'project_adjustment_request' => 'Solicitud de ajuste',
            'delivery' => 'Entrega',
            'notification' => 'Notificación',
            'settings' => 'Configuración',
            'period' => 'Periodo académico',
            'type' => 'Tipo de proyecto',
            'project_type' => 'Tipo de proyecto',
            'support_material' => 'Material de apoyo',
        ];

        $actionLabel = AuditLabelFormatter::action($action, $map[$action] ?? '');
        $entityLabel = AuditLabelFormatter::context($entityType);

        return ['action_label' => $actionLabel, 'entity_label' => $entityLabel];
    }

    private function contextualActionLabel(string $action, string $fallback, string $elementLabel, array $details): string
    {
        $name = trim((string)($details['name'] ?? $details['catalog_name'] ?? $elementLabel));
        if ($name === '') return $fallback;
        $quoted = '"' . $name . '"';
        return match ($action) {
            'academic_type_description_added', 'academic_type_description_updated' => 'Actualizó la configuración del tipo '.$quoted.'. Modificó la descripción utilizada durante el registro.',
            'academic_type_description_removed' => 'Actualizó la configuración del tipo '.$quoted.'. Eliminó la descripción utilizada durante el registro.',
            'academic_keyword_created' => 'Registró la palabra clave '.$quoted.'.',
            'academic_keyword_deleted' => 'Eliminó la palabra clave '.$quoted.'.',
            default => $fallback,
        };
    }

    private function countAuditEvents(string $from, string $to, int $maxRelevance): int
    {
        $db = Database::connection();
        [$whereAdmin, $paramsAdmin] = $this->auditActionFilter('a', 'admin', $maxRelevance);
        [$whereProject, $paramsProject] = $this->auditActionFilter('p', 'project', $maxRelevance);
        $sql = "SELECT COUNT(*) FROM (
            SELECT a.id FROM admin_audit_log a WHERE a.created_at BETWEEN :from1 AND :to1 AND $whereAdmin
            UNION ALL
            SELECT p.id FROM project_audit_log p WHERE p.created_at BETWEEN :from2 AND :to2 AND $whereProject
        ) audit_count";
        $params = array_merge($paramsAdmin, $paramsProject, [
            'from1'=>$from.' 00:00:00','to1'=>$to.' 23:59:59',
            'from2'=>$from.' 00:00:00','to2'=>$to.' 23:59:59',
        ]);
        $query = $db->prepare($sql);
        $query->execute($params);
        return (int)$query->fetchColumn();
    }

    private function fetchAuditEvents(string $from, string $to, int $maxRelevance, ?int $limit = null, int $offset = 0): array
    {
        $db = Database::connection();
        [$whereAdmin, $paramsAdmin] = $this->auditActionFilter('a', 'admin', $maxRelevance);
        [$whereProject, $paramsProject] = $this->auditActionFilter('p', 'project', $maxRelevance);
        $sql = "SELECT id, action, action_label, module, entity_type, entity_id, element_label,
                       details, previous_state, new_state, project_available, created_at, actor
                FROM (
                    SELECT a.id, a.action, a.action_label, a.module, a.entity_type, a.entity_id,
                           a.element_label, a.details, NULL previous_state, NULL new_state,
                           CASE WHEN a.entity_type='project' AND EXISTS (SELECT 1 FROM projects target WHERE target.id=a.entity_id) THEN 1
                                WHEN a.entity_type='project' THEN 0 ELSE NULL END project_available,
                           a.created_at, u.full_name actor
                    FROM admin_audit_log a LEFT JOIN users u ON u.id=a.actor_user_id
                    WHERE a.created_at BETWEEN :from1 AND :to1 AND $whereAdmin
                    UNION ALL
                    SELECT p.id, p.action, NULL action_label, NULL module, p.entity_type, p.entity_id,
                           NULL element_label, NULL details, p.previous_state, p.new_state,
                           CASE WHEN p.entity_type='project' AND EXISTS (SELECT 1 FROM projects target WHERE target.id=p.entity_id) THEN 1
                                WHEN p.entity_type='project' THEN 0 ELSE NULL END project_available,
                           p.created_at, u.full_name actor
                    FROM project_audit_log p LEFT JOIN users u ON u.id=p.user_id
                    WHERE p.created_at BETWEEN :from2 AND :to2 AND $whereProject
                ) audit_events
                ORDER BY created_at DESC, id DESC";
        $params = array_merge($paramsAdmin, $paramsProject, [
            'from1'=>$from.' 00:00:00','to1'=>$to.' 23:59:59',
            'from2'=>$from.' 00:00:00','to2'=>$to.' 23:59:59',
        ]);
        if ($limit !== null) {
            $sql .= ' LIMIT :audit_limit OFFSET :audit_offset';
            $params['audit_limit'] = max(1, $limit);
            $params['audit_offset'] = max(0, $offset);
        }
        $query = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $query->bindValue(':'.$key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{0:string,1:array<string,string>} */
    private function auditActionFilter(string $alias, string $prefix, int $maxRelevance): array
    {
        $actions = AuditLabelFormatter::actionsForMaxLevel($maxRelevance);
        if ($maxRelevance >= 3) {
            $excluded = AuditLabelFormatter::actionsForMaxLevel(4);
            $technical = AuditLabelFormatter::actionsForMaxLevel(3);
            $excluded = array_values(array_diff($excluded, $technical));
            $placeholders = [];
            $params = [];
            foreach ($excluded as $index => $action) {
                $name = $prefix.'_excluded_'.$index;
                $placeholders[] = ':'.$name;
                $params[$name] = $action;
            }
            return [$alias.'.action NOT IN ('.implode(',', $placeholders).')', $params];
        }
        $placeholders = [];
        $params = [];
        foreach ($actions as $index => $action) {
            $name = $prefix.'_action_'.$index;
            $placeholders[] = ':'.$name;
            $params[$name] = $action;
        }
        return [$alias.'.action IN ('.implode(',', $placeholders).')', $params];
    }

    private function formatLocalDateTime(string $value): string
    {
        if (trim($value) === '') return '';
        $timezone = (string)($GLOBALS['config']['timezone'] ?? 'America/Guayaquil');
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone($timezone))
            ->format('d/m/Y H:i');
    }

    public function export(string $type, string $from, string $to): array
    {
        $db=Database::connection();
        $params=['from'=>$from.' 00:00:00','to'=>$to.' 23:59:59'];
        if($type==='users'){
            $sql="SELECT u.full_name, u.email,
                  COALESCE(sp.institutional_code, tp.institutional_code, '-') institutional_code,
                  COALESCE(user_role_names.roles, '-') role,
                  CASE
                    WHEN u.deleted_at IS NOT NULL THEN 'En papelera'
                    WHEN u.status = 'active' THEN 'Activo'
                    WHEN u.status = 'inactive' THEN 'Inactivo'
                    WHEN u.status = 'blocked' THEN 'Bloqueado'
                    ELSE u.status
                  END status_label,
                  u.created_at created_at_raw,
                  u.last_login_at last_login_raw
                  FROM users u
                  LEFT JOIN (SELECT ur.user_id, GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') roles
                             FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id
                             GROUP BY ur.user_id) user_role_names ON user_role_names.user_id=u.id
                  LEFT JOIN student_profiles sp ON sp.user_id=u.id
                  LEFT JOIN teacher_profiles tp ON tp.user_id=u.id
                  WHERE u.created_at BETWEEN :from AND :to AND u.purged_at IS NULL
                  ORDER BY u.created_at DESC";
            $headers=['Nombre completo','Correo electrónico','Cédula / Código','Rol','Estado','Fecha de registro','Último acceso'];
            $query=$db->prepare($sql);
            $query->execute($params);
            $rows=array_map(function(array $row): array {
                $row['created_at_formatted']=$this->formatLocalDateTime((string)$row['created_at_raw']);
                $row['last_login_formatted']=$row['last_login_raw'] ? $this->formatLocalDateTime((string)$row['last_login_raw']) : 'Nunca';
                unset($row['created_at_raw'],$row['last_login_raw']);
                return $row;
            }, $query->fetchAll(PDO::FETCH_ASSOC));
            return ['headers'=>$headers,'rows'=>$rows];
        }elseif($type==='projects'){
            $sql="SELECT p.id, p.code, p.title, pt.name type_name, c.name career_name, ap.name period_name, p.status status_code,
                  u.full_name tutor_name,
                  p.created_at created_at_raw,
                  p.updated_at updated_at_raw
                  FROM projects p
                  JOIN project_types pt ON pt.id=p.project_type_id
                  JOIN careers c ON c.id=p.career_id
                  JOIN academic_periods ap ON ap.id=p.academic_period_id
                  LEFT JOIN users u ON u.id=p.tutor_id
                  WHERE p.created_at BETWEEN :from AND :to AND p.deleted_at IS NULL
                    AND p.status IN ('development','under_review','approved','defense','tribunal_approved','published')
                  ORDER BY p.created_at DESC";
            $query=$db->prepare($sql);
            $query->execute($params);
            $rawProjects=$query->fetchAll(PDO::FETCH_ASSOC);
            $situations = (new ProjectReviewSituationService())->forProjects(
                array_map('intval', array_column($rawProjects, 'id')),
                $db
            );

            // Obtener autores por proyecto
            $projectIds = array_column($rawProjects, 'id');
            $authorsByProject = [];
            if ($projectIds) {
                $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
                $stmt = $db->prepare("SELECT pp.project_id, GROUP_CONCAT(u.full_name SEPARATOR ', ') authors
                                      FROM project_participants pp
                                      JOIN users u ON u.id=pp.user_id
                                      WHERE pp.project_id IN ($placeholders) AND pp.role_code='student' AND pp.status='active' AND pp.removed_at IS NULL
                                      GROUP BY pp.project_id");
                $stmt->execute($projectIds);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $authorsByProject[(int)$row['project_id']] = $row['authors'];
                }
            }

            $rows = [];
            foreach ($rawProjects as $p) {
                $pid = (int)$p['id'];
                $statusLabel = project_academic_labels((string)$p['status_code'])['status'];
                $reviewSituation = $situations[$pid] ?? ProjectReviewSituationService::emptySituation();
                $reviewLabel = !empty($reviewSituation['has_pending_observations'])
                    ? 'Observaciones pendientes'
                    : (!empty($reviewSituation['has_addressed_observations'])
                        ? 'Observaciones atendidas'
                        : 'Sin observaciones registradas');
                $rows[] = [
                    'code' => $p['code'],
                    'title' => $p['title'],
                    'type' => $p['type_name'],
                    'career' => $p['career_name'],
                    'period' => $p['period_name'],
                    'status' => $statusLabel,
                    'review_situation' => $reviewLabel,
                    'author' => $authorsByProject[$pid] ?? 'Por asignar',
                    'tutor' => $p['tutor_name'] ?: 'Por asignar',
                    'created_at' => $this->formatLocalDateTime((string)$p['created_at_raw']),
                    'updated_at' => $this->formatLocalDateTime((string)$p['updated_at_raw']),
                ];
            }
            $headers=['Código','Título','Tipo','Carrera','Período académico','Estado','Situación de revisión','Autor(es)','Tutor','Fecha de registro','Última actualización'];
            return ['headers'=>$headers,'rows'=>$rows];
        }else{
            // La exportación conserva Nivel 1, 2 y 3; excluye ruido técnico.
            $processed = $this->auditEvents($from, $to, 3);
            $rows = array_map(function($item) {
                return [
                    'created_at' => $item['created_at_local'],
                    'actor' => $item['actor'],
                    'action' => $item['action_label'],
                    'entity_type' => $item['module'] ?: $item['entity_label'],
                    'entity_id' => $item['element_label'] ?: '-',
                ];
            }, $processed);

            $headers = ['Fecha y hora', 'Responsable', 'Acción auditada', 'Módulo / Entidad', 'Registro relacionado'];
            return ['headers' => $headers, 'rows' => $rows];
        }
    }

    private function count(PDO $db,string $sql,array $params):int
    {
        $query=$db->prepare($sql);$query->execute($params);return (int)$query->fetchColumn();
    }
}
