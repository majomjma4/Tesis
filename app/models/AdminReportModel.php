<?php

declare(strict_types=1);

final class AdminReportModel
{
    private const OPERATIONAL_STATUSES = ['development','under_review','approved','defense','tribunal_approved','published'];

    public function dashboard(string $from, string $to, array $pagination = []): array
    {
        $db = Database::connection();
        $range = ['from'=>$from.' 00:00:00','to'=>$to.' 23:59:59'];
        
        $params=['from1'=>$range['from'],'to1'=>$range['to'],'from2'=>$range['from'],'to2'=>$range['to']];
        $sql = "SELECT action, action_label, module, entity_type, entity_id, element_label, created_at, actor FROM (
                    SELECT a.action, a.action_label, a.module, a.entity_type, a.entity_id, a.element_label, a.created_at, u.full_name actor FROM admin_audit_log a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.created_at BETWEEN :from1 AND :to1
                    UNION ALL
                    SELECT p.action, NULL action_label, NULL module, p.entity_type, p.entity_id, NULL element_label, p.created_at, u.full_name actor FROM project_audit_log p LEFT JOIN users u ON u.id=p.user_id WHERE p.created_at BETWEEN :from2 AND :to2
                ) audit_events ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rawEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = $this->processEvents($rawEvents);

        $summary = [
            'users'=>$this->count($db,'SELECT COUNT(*) FROM users WHERE created_at BETWEEN :from AND :to',$range),
            'projects'=>$this->count($db,'SELECT COUNT(*) FROM projects WHERE created_at BETWEEN :from AND :to',$range),
            'deliveries'=>$this->count($db,'SELECT COUNT(*) FROM project_deliveries WHERE submitted_at BETWEEN :from AND :to',$range),
            'actions'=>count($processed),
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
        

        
        $page = (int)($pagination['page'] ?? PaginationService::request()['page'] ?? 1);
        $perPage = (int)($pagination['size'] ?? PaginationService::request()['size'] ?? 10);
        $total = count($processed);
        $pages = (int)ceil($total / max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $items = array_slice($processed, $offset, $perPage);

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

    private function processEvents(array $events): array
    {
        $filtered = [];
        $seenKeys = [];

        $ignoredActions = [
            'demo_teacher_updated', 'demo_users_imported', 'demo_catalog_configured',
            'profile_updated', 'project_updated',
            'support_material.presentation_selected',
            'support_material.presentation_changed',
            'support_material.presentation_removed'
        ];

        foreach ($events as $event) {
            $action = (string)$event['action'];
            $entityType = (string)$event['entity_type'];
            $actor = $event['actor'] ?: 'Sistema';
            $createdAt = (string)$event['created_at'];

            // 1. Ocultar acciones internas/técnicas o demasiado genéricas
            if (in_array($action, $ignoredActions, true)) {
                continue;
            }

            // 2. Consolidar duplicados lógicos donde un evento se registra como 'project' y como 'project_file'
            // Si la entidad es 'project' para una acción de archivo, descartar la duplicada manteniendo la de 'project_file'
            if ($entityType === 'project' && strpos($action, 'project.') === 0) {
                continue;
            }

            // Mapear traducción limpia
            $actionLabel = AuditLabelFormatter::action($action, (string)($event['action_label'] ?? ''));
            $elementLabel = trim((string)($event['element_label'] ?? ''));
            $entityLabel = $elementLabel !== '' ? $elementLabel : AuditLabelFormatter::context($entityType);
            
            // Llave de deduplicación basada en actor, acción traducida, entidad y ventana de 3 segundos
            $timestamp = strtotime($createdAt);
            $timeBlock = floor($timestamp / 3);
            $dedupKey = $actor . '|' . $actionLabel . '|' . $entityLabel . '|' . $timeBlock;

            if (isset($seenKeys[$dedupKey])) {
                continue;
            }
            $seenKeys[$dedupKey] = true;

            $filtered[] = [
                'action' => $action,
                'action_label' => $actionLabel,
                'entity_type' => $entityType,
                'entity_id' => $event['entity_id'] ?? null,
                'entity_label' => $entityLabel,
                'module' => trim((string)($event['module'] ?? '')),
                'element_label' => $elementLabel,
                'created_at' => $createdAt,
                'created_at_local' => $this->formatLocalDateTime($createdAt),
                'actor' => $actor
            ];
        }

        return $filtered;
    }

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
                  r.name role,
                  CASE
                    WHEN u.deleted_at IS NOT NULL THEN 'En papelera'
                    WHEN u.status = 'active' THEN 'Activo'
                    WHEN u.status = 'inactive' THEN 'Inactivo'
                    WHEN u.status = 'blocked' THEN 'Bloqueado'
                    ELSE u.status
                  END status_label,
                  DATE_FORMAT(u.created_at, '%d/%m/%Y %H:%i') created_at_formatted,
                  CASE WHEN u.last_login_at IS NOT NULL THEN DATE_FORMAT(u.last_login_at, '%d/%m/%Y %H:%i') ELSE 'Nunca' END last_login_formatted
                  FROM users u
                  LEFT JOIN user_roles ur ON ur.user_id=u.id
                  LEFT JOIN roles r ON r.id=ur.role_id
                  LEFT JOIN student_profiles sp ON sp.user_id=u.id
                  LEFT JOIN teacher_profiles tp ON tp.user_id=u.id
                  WHERE u.created_at BETWEEN :from AND :to AND u.purged_at IS NULL
                  ORDER BY u.created_at DESC";
            $headers=['Nombre completo','Correo electrónico','Cédula / Código','Rol','Estado','Fecha de registro','Último acceso'];
            $query=$db->prepare($sql);
            $query->execute($params);
            $rows=$query->fetchAll(PDO::FETCH_ASSOC);
            return ['headers'=>$headers,'rows'=>$rows];
        }elseif($type==='projects'){
            $sql="SELECT p.id, p.code, p.title, pt.name type_name, c.name career_name, ap.name period_name, p.status status_code,
                  CASE
                    WHEN COALESCE(review.pending_count,0)>0 THEN 'pending'
                    WHEN COALESCE(review.addressed_count,0)>0 THEN 'addressed'
                    ELSE 'none'
                  END review_situation,
                  u.full_name tutor_name,
                  DATE_FORMAT(p.created_at, '%d/%m/%Y %H:%i') created_at_formatted,
                  DATE_FORMAT(p.updated_at, '%d/%m/%Y %H:%i') updated_at_formatted
                  FROM projects p
                  JOIN project_types pt ON pt.id=p.project_type_id
                  JOIN careers c ON c.id=p.career_id
                  JOIN academic_periods ap ON ap.id=p.academic_period_id
                  LEFT JOIN users u ON u.id=p.tutor_id
                  LEFT JOIN (SELECT project_id, SUM(status='pending') pending_count, SUM(status IN ('addressed','resolved')) addressed_count FROM project_observations GROUP BY project_id) review ON review.project_id=p.id
                  WHERE p.created_at BETWEEN :from AND :to AND p.deleted_at IS NULL
                    AND p.status IN ('development','under_review','approved','defense','tribunal_approved','published')
                  ORDER BY p.created_at DESC";
            $query=$db->prepare($sql);
            $query->execute($params);
            $rawProjects=$query->fetchAll(PDO::FETCH_ASSOC);

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
                $reviewLabel = match((string)$p['review_situation']) {
                    'pending' => 'Observaciones pendientes',
                    'addressed' => 'Observaciones atendidas',
                    default => 'Sin observaciones registradas'
                };
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
                    'created_at' => $p['created_at_formatted'],
                    'updated_at' => $p['updated_at_formatted'],
                ];
            }
            $headers=['Código','Título','Tipo','Carrera','Período académico','Estado','Situación de revisión','Autor(es)','Tutor','Fecha de registro','Última actualización'];
            return ['headers'=>$headers,'rows'=>$rows];
        }else{
            $sql="SELECT action, action_label, module, entity_type, entity_id, element_label, actor, created_at FROM (
                    SELECT a.action, a.action_label, a.module, a.entity_type, a.entity_id, a.element_label, u.full_name actor, a.created_at FROM admin_audit_log a LEFT JOIN users u ON u.id=a.actor_user_id
                    UNION ALL
                    SELECT p.action, NULL action_label, NULL module, p.entity_type, p.entity_id, NULL element_label, u.full_name actor, p.created_at FROM project_audit_log p LEFT JOIN users u ON u.id=p.user_id
                  ) audit WHERE created_at BETWEEN :from AND :to ORDER BY created_at DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rawAudit = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $processed = $this->processEvents($rawAudit);
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
