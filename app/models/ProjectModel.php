<?php

declare(strict_types=1);

/**
 * Contrato temporal del módulo de proyectos.
 * Los datos son simulados, no representan persistencia ni permisos definitivos.
 */
final class ProjectModel
{
    /** Devuelve los expedientes visibles para el usuario indicado. */
    public function getProjectsForUser(int $userId): array
    {
        if (!Database::isEnabled()) return [];
        $statement=Database::connection()->prepare("SELECT DISTINCT p.id,p.code,p.title,p.subtitle,p.status,p.current_stage,p.updated_at,
            pt.code AS type_key,pt.name AS type,c.name AS career,ap.name AS period,t.full_name AS tutor,
            (SELECT pd.version_number FROM project_deliveries pd WHERE pd.project_id=p.id ORDER BY pd.submitted_at DESC,pd.id DESC LIMIT 1) AS latest_delivery_version,
            (SELECT COUNT(*) FROM project_observations po WHERE po.project_id=p.id) AS observation_count,
            (SELECT COUNT(*) FROM project_files pf WHERE pf.project_id=p.id AND pf.deleted_at IS NULL) AS final_document_count
            FROM projects p INNER JOIN project_types pt ON pt.id=p.project_type_id INNER JOIN careers c ON c.id=p.career_id
            INNER JOIN academic_periods ap ON ap.id=p.academic_period_id LEFT JOIN users t ON t.id=p.tutor_id
            LEFT JOIN project_participants pp ON pp.project_id=p.id AND pp.status='active' AND pp.removed_at IS NULL
            WHERE p.deleted_at IS NULL AND (p.created_by = :created_by_user OR pp.user_id= :participant_user) ORDER BY p.updated_at DESC,p.id DESC");
        $statement->execute(['created_by_user'=>$userId,'participant_user'=>$userId]);
        $rows=$statement->fetchAll();
        $situations=(new ProjectReviewSituationService())->forProjects(array_map('intval',array_column($rows,'id')));
        $totalRows=count($rows);
        return array_map(static function(array $row,int $index)use($situations,$totalRows):array{
            $status=(string)$row['status'];
            $labels=project_academic_labels($status);
            $latestDelivery=$row['latest_delivery_version']===null?null:['version'=>'v'.(int)$row['latest_delivery_version']];
            $finalDocumentCount=$status==='published'?(int)($row['final_document_count']??0):0;
            $situation=$situations[(int)$row['id']]??ProjectReviewSituationService::emptySituation();
            $stage=(string)($row['current_stage']??'registration');
            $statusKey=$status==='under_review'?'review':$status;
            $progress=match($status){'published','tribunal_approved'=>100,'approved'=>76,'defense'=>91,'under_review'=>58,default=>25};
            return ['id'=>(int)$row['id'],'code'=>(string)$row['code'],'title'=>(string)$row['title'],'subtitle'=>(string)($row['subtitle']??''),
                'status'=>$labels['status'],'status_key'=>$status,'type'=>(string)$row['type'],'type_key'=>(string)$row['type_key'],
                'career'=>(string)$row['career'],'period'=>(string)$row['period'],'tutor'=>(string)($row['tutor']??''),'stage'=>(string)$row['current_stage'],
                'last_activity'=>'Actualización del expediente · '.date('d/m/Y',strtotime((string)$row['updated_at'])),
                'metric_bucket'=>in_array($status,['published','tribunal_approved'],true)?'finished':($status==='under_review'?'review':'active'),
                'review_situation'=>$situation,
                'observations'=>array_fill(0,(int)($row['observation_count']??0),[]),
                'final_documents'=>array_fill(0,$finalDocumentCount,[]),
                'latest_delivery'=>$latestDelivery,'key_dates'=>[['label'=>'Inicio del expediente','value'=>'Registrado'],['label'=>'Última actividad','value'=>date('d/m/Y',strtotime((string)$row['updated_at']))],['label'=>'Próximo hito','value'=>'Por definir']],
                'progress'=>$progress,'activity_order'=>$totalRows-$index,'tags'=>[],'technologies'=>[],
                'repository_id'=>$status==='published'?(int)$row['id']:null];
        },$rows,array_keys($rows));
    }

    /** Busca un expediente comprobando temporalmente su pertenencia. */
    public function findProjectForUser(int $projectId, int $userId): ?array
    {
        return (new ProjectRecordModel())->find($projectId,$userId,false);
    }

    /** Devuelve un expediente real para la consulta administrativa. */
    public function findProjectForAdministrator(int $projectId): ?array
    {
        if (!Database::isEnabled()) return null;

        $statement = Database::connection()->prepare(
            "SELECT p.id, p.code, p.title, p.subtitle, p.status, p.current_stage, p.updated_at,
                    pt.code AS type_key, pt.name AS type, c.name AS career, ap.name AS period,
                    tutor.full_name AS tutor_name
             FROM projects p
             INNER JOIN project_types pt ON pt.id = p.project_type_id
             LEFT JOIN careers c ON c.id = p.career_id
             LEFT JOIN academic_periods ap ON ap.id = p.academic_period_id
             LEFT JOIN users tutor ON tutor.id = p.tutor_id
             WHERE p.id = :id AND p.deleted_at IS NULL"
        );
        $statement->execute(['id' => $projectId]);
        $row = $statement->fetch();
        if (!$row) return null;

        $participants = Database::connection()->prepare(
            "SELECT u.full_name, pp.role_code
             FROM project_participants pp
             INNER JOIN users u ON u.id = pp.user_id
             WHERE pp.project_id = :id AND pp.status = 'active'
             ORDER BY pp.is_leader DESC, u.full_name"
        );
        $participants->execute(['id' => $projectId]);
        $members = array_map(static fn (array $member): array => [
            'initial' => mb_strtoupper(mb_substr((string) $member['full_name'], 0, 1, 'UTF-8'), 'UTF-8'),
            'name' => (string) $member['full_name'],
            'role' => $member['role_code'] === 'leader' ? 'Líder' : 'Integrante',
        ], $participants->fetchAll());

        $statusLabels = [
            'development' => 'En desarrollo', 'under_review' => 'En revisión',
            'approved' => 'Aprobado',
            'defense' => 'En tribunal', 'tribunal_approved' => 'Aprobado por el Tribunal',
            'published' => 'Publicado',
        ];
        $statusKey = match ((string) $row['status']) {
            'under_review' => 'review',
            default => (string) $row['status'],
        };
        $updatedAt = (string) ($row['updated_at'] ?? '');
        $date = $updatedAt ? date('d/m/Y', strtotime($updatedAt)) : 'Sin actividad registrada';
        $audit = Database::connection()->prepare(
            "SELECT pal.new_state,pal.created_at,u.full_name
             FROM project_audit_log pal
             LEFT JOIN users u ON u.id=pal.user_id
             WHERE pal.project_id=:id AND pal.action='project_updated'
             ORDER BY pal.created_at DESC,pal.id DESC"
        );
        $audit->execute(['id'=>$projectId]);
        $auditHistory=[];
        foreach($audit->fetchAll() as $entry){
            $state=json_decode((string)($entry['new_state']??''),true);
            $changes=is_array($state)?($state['_history_changes']??null):null;
            if(!is_array($changes)||!$changes)continue;
            $auditHistory[]=[
                'type'=>'Modificación','icon'=>'fa-pen-to-square',
                'user'=>(string)($entry['full_name']?:'Administrador'),'role'=>'Administrador',
                'action'=>'Administrador modificó el proyecto',
                'detail'=>'Cambios realizados:','changes'=>$changes,
                'date'=>date('d/m/Y H:i',strtotime((string)$entry['created_at'])),
            ];
        }

        return $this->enrichProject([
            'id' => (int) $row['id'], 'type' => (string) $row['type'],
            'type_key' => (string) $row['type_key'], 'status' => $statusLabels[$row['status']] ?? (string) $row['status'],
            'status_key' => $statusKey, 'title' => (string) $row['title'],
            'subtitle' => (string) ($row['subtitle'] ?? ''), 'career' => (string) ($row['career'] ?? 'Sin carrera'),
            'period' => (string) ($row['period'] ?? 'Sin periodo'), 'role' => 'Administración',
            'tutor' => (string) ($row['tutor_name'] ?? ''), 'participants' => $members,
            'last_activity' => 'Actualización del expediente · ' . $date,
            'stage' => (string) ($row['current_stage'] ?? 'Registro'), 'latest_delivery' => null,
            'observations' => [], 'comments' => [],
            'activities' => [['title' => 'Expediente disponible para administración', 'date' => $date]],
            'persistent_history'=>$auditHistory,
        ]);
    }

    public function getProjectMetrics(array $projects): array
    {
        $counts = ['active' => 0, 'review' => 0, 'pending_observations' => 0, 'finished' => 0];
        foreach ($projects as $project) {
            $bucket = $project['metric_bucket'];
            if (isset($counts[$bucket])) $counts[$bucket]++;
            if (!empty($project['review_situation']['has_pending_observations'])) $counts['pending_observations']++;
        }
        return [
            ['key' => 'active', 'label' => 'Activos', 'icon' => 'fa-folder-open', 'count' => $counts['active']],
            ['key' => 'review', 'label' => 'En revisión', 'icon' => 'fa-magnifying-glass', 'count' => $counts['review']],
            ['key' => 'pending_observations', 'label' => 'Observaciones pendientes', 'icon' => 'fa-triangle-exclamation', 'count' => $counts['pending_observations']],
            ['key' => 'finished', 'label' => 'Publicados', 'icon' => 'fa-circle-check', 'count' => $counts['finished']],
        ];
    }

    public function getDetailTabs(): array
    {
        return [
            'summary' => ['label' => 'Resumen', 'icon' => 'fa-house'],
            'documents' => ['label' => 'Documentos', 'icon' => 'fa-folder-open'],
            'review' => ['label' => 'Revisión', 'icon' => 'fa-list-check'],
            'activity' => ['label' => 'Actividad', 'icon' => 'fa-clock-rotate-left'],
            'information' => ['label' => 'Información', 'icon' => 'fa-circle-info'],
        ];
    }

    /** Completa información derivada sin duplicarla en cada expediente simulado. */
    private function enrichProject(array $project): array
    {
        $stageLabels = $project['type_key'] === 'community'
            ? ['Registro', 'Planificación', 'Ejecución', 'Evaluación', 'Publicación']
            : ['Registro', 'Desarrollo', 'Revisión', 'Tribunal y defensa', 'Cierre y publicación'];
        $currentIndex = match ($project['status_key']) {
            'review' => 2,
            'approved' => 3,
            'defense' => 3,
            'tribunal_approved' => 4,
            'published' => 4,
            default => 1,
        };
        $project['stages'] = array_map(static fn (string $label, int $index): array => [
            'label' => $label,
            'state' => $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : 'upcoming'),
        ], $stageLabels, array_keys($stageLabels));
        $project['progress'] = (int) round(($currentIndex / max(1, count($stageLabels) - 1)) * 100);
        $nextMilestone = match ($project['status_key']) {
            'review' => '22 Jul 2026',
            'approved' => 'Por programar',
            'defense' => '29 Jul 2026 · 10:00',
            'tribunal_approved' => 'Listo para publicación',
            'published' => 'Expediente cerrado',
            default => 'Por definir',
        };
        $project['key_dates'] = [
            ['label' => 'Inicio del expediente', 'value' => '02 Jun 2026'],
            ['label' => 'Última actividad', 'value' => preg_replace('/^.*·\s*/u', '', $project['last_activity'])],
            ['label' => 'Próximo hito', 'value' => $nextMilestone],
        ];
        $project['academic_info'] = [
            ['label' => 'Carrera', 'value' => $project['career']],
            ['label' => 'Periodo', 'value' => $project['period']],
            ['label' => 'Tipo', 'value' => $project['type']],
            ['label' => 'Rol actual', 'value' => $project['role']],
        ];
        $project['deliveries'] = $project['latest_delivery'] === null ? [] : [
            $project['latest_delivery'] + [
                'stage' => $project['stage'], 'author' => $project['participants'][0]['name'],
                'file' => 'Documento.pdf', 'preview_path' => 'Vista Previa/Documento.pdf', 'size' => '2.4 MB',
                'comment' => 'Versión preparada para la revisión académica del tutor.',
            ],
            ['version' => 'v3', 'title' => 'Marco teórico y propuesta', 'date' => '24 Jun 2026', 'status' => 'Correcciones solicitadas', 'stage' => 'Desarrollo', 'author' => $project['participants'][0]['name'], 'file' => 'Informe_v3.docx', 'preview_path' => 'Vista Previa/Informe.docx', 'size' => '1.8 MB', 'comment' => 'Documento de Word conservado como versión anterior.'],
            ['version' => 'v2', 'title' => 'Código y anexos', 'date' => '10 Jun 2026', 'status' => 'Archivada', 'stage' => 'Desarrollo', 'author' => $project['participants'][0]['name'], 'file' => 'respaldo.zip', 'preview_path' => 'respaldo.zip', 'size' => '3.6 MB', 'comment' => 'Paquete navegable con archivos complementarios del proyecto.'],
        ];
        $project['observations'] = array_map(static fn (array $observation, int $index): array => $observation + [
            'id' => $index + 1, 'author' => 'Ing. Tutor Asignado', 'role' => 'Tutor',
            'date' => '17 Jul 2026', 'delivery' => 'Informe metodológico · v4',
            'category' => $index === 0 ? 'Metodología' : 'Formato', 'location' => $index === 0 ? 'Página 28' : 'Referencias',
            'responses' => $index === 0 ? [['author' => 'Carlos Martínez', 'date' => '18 Jul 2026', 'text' => 'La corrección está en preparación para la siguiente versión.']] : [],
        ], $project['observations'], array_keys($project['observations']));
        $project['participant_groups'] = [
            ['label' => 'Estudiantes', 'members' => array_map(static fn (array $member): array => $member + ['email' => strtolower($member['initial']) . '.estudiante@libertador.edu.ec', 'status' => 'Activo', 'assigned_at' => '02 Jun 2026'], $project['participants'])],
            ['label' => 'Tutoría', 'members' => $project['tutor'] ? [['initial' => mb_substr($project['tutor'], 0, 1, 'UTF-8'), 'name' => $project['tutor'], 'role' => 'Tutor', 'email' => 'tutoria@libertador.edu.ec', 'status' => 'Activo', 'assigned_at' => '05 Jun 2026']] : []],
            ['label' => 'Tribunal', 'members' => in_array($project['status_key'], ['defense', 'tribunal_approved', 'published'], true) ? [
                ['initial' => 'J1', 'name' => 'Msc. Ana Morales', 'role' => 'Jurado 1', 'email' => 'ana.morales@libertador.edu.ec', 'status' => 'Asignado', 'assigned_at' => '15 Jul 2026'],
                ['initial' => 'J2', 'name' => 'Ing. Luis Paredes', 'role' => 'Jurado 2', 'email' => 'luis.paredes@libertador.edu.ec', 'status' => 'Asignado', 'assigned_at' => '15 Jul 2026'],
                ['initial' => 'J3', 'name' => 'Msc. Rosa León', 'role' => 'Jurado 3', 'email' => 'rosa.leon@libertador.edu.ec', 'status' => 'Asignado', 'assigned_at' => '15 Jul 2026'],
            ] : []],
        ];
        $project['history'] = isset($project['persistent_history']) ? [
            ...$project['persistent_history'],
            ...array_map(static fn (array $activity): array => ['type' => 'Actividad', 'icon' => 'fa-circle-check', 'user' => 'Sistema académico', 'role' => 'Sistema', 'action' => $activity['title'], 'detail' => 'Registro incorporado a la trazabilidad.', 'date' => $activity['date']], $project['activities']),
        ] : [
            ['type' => 'Estado', 'icon' => 'fa-arrows-rotate', 'user' => $project['tutor'], 'role' => 'Tutor', 'action' => 'Actualizó el estado del proyecto', 'detail' => $project['status'], 'date' => preg_replace('/^.*·\s*/u', '', $project['last_activity'])],
            ...array_map(static fn (array $activity): array => ['type' => 'Actividad', 'icon' => 'fa-circle-check', 'user' => 'Sistema académico', 'role' => 'Sistema', 'action' => $activity['title'], 'detail' => 'Registro incorporado a la trazabilidad.', 'date' => $activity['date']], $project['activities']),
        ];
        $project['final_documents'] = $project['status_key'] === 'published' ? [
            ['label' => 'Informe definitivo', 'file' => 'Informe_final_aprobado.pdf', 'format' => 'PDF', 'status' => 'Publicado'],
            ['label' => 'Código fuente', 'file' => 'Codigo_fuente_final.zip', 'format' => 'ZIP', 'status' => 'Publicado'],
            ['label' => 'Anexos', 'file' => 'Anexos_proyecto.pdf', 'format' => 'PDF', 'status' => 'Publicado'],
        ] : [];
        return $project;
    }

    private function projects(): array
    {
        $common = [
            'user_ids' => [1], 'career' => 'Desarrollo de Software',
            'period' => '2026-I', 'role' => 'Estudiante líder', 'tags' => [], 'technologies' => [],
            'participants' => [
                ['initial' => 'C', 'name' => 'Carlos Martínez', 'role' => 'Líder'],
                ['initial' => 'A', 'name' => 'Andrés Pérez', 'role' => 'Integrante'],
                ['initial' => 'L', 'name' => 'Lucía Gómez', 'role' => 'Integrante'],
            ],
        ];

        return [
            $common + [
                'id' => 1, 'type' => 'Titulación', 'type_key' => 'thesis', 'status' => 'En revisión', 'status_key' => 'review', 'metric_bucket' => 'review',
                'title' => 'Sistema de Gestión Documental Académica', 'subtitle' => 'Seguimiento, revisión y publicación de proyectos académicos.',
                'tags' => ['Gestión académica', 'Trazabilidad'], 'technologies' => ['PHP', 'MariaDB'], 'activity_order' => 4,
                'tutor' => 'Ing. Tutor Asignado', 'last_activity' => 'Revisión del tutor · 17 Jul 2026', 'stage' => 'Revisión académica', 'progress' => 58,
                'context' => ['Última entrega' => 'Informe metodológico · v4', 'Próxima revisión' => '22 Jul 2026', 'Observaciones pendientes' => '2 de prioridad alta'],
                'action_label' => 'Continuar seguimiento', 'next_action' => 'Atender las observaciones de metodología y referencias.',
                'latest_delivery' => ['version' => 'v4', 'title' => 'Informe metodológico', 'date' => '16 Jul 2026', 'status' => 'En revisión'],
                'observations' => [
                    ['title' => 'Marco metodológico', 'text' => 'Ampliar el enfoque y justificar los instrumentos.', 'status' => 'Pendiente'],
                    ['title' => 'Referencias', 'text' => 'Unificar citas y bibliografía en formato APA 7.', 'status' => 'Atendida'],
                    ['title' => 'Formato de anexos', 'text' => 'La estructura de anexos fue revisada y aprobada.', 'status' => 'Resuelta'],
                ],
                'activities' => [['title' => 'Versión 4 registrada', 'date' => '16 Jul'], ['title' => 'Tutor inició la revisión', 'date' => '17 Jul'], ['title' => 'Equipo confirmó la recepción', 'date' => '18 Jul']],
                'comments' => [
                    ['author' => 'Ing. Tutor Asignado', 'date' => '17 Jul 2026 · 09:20', 'text' => 'Revisaremos primero metodología. Este comentario es general y no está asociado a un archivo.', 'relation' => null],
                    ['author' => 'Carlos Martínez', 'date' => '17 Jul 2026 · 11:05', 'text' => 'Entendido. El equipo organizará los cambios y preparará una nueva versión.', 'relation' => null],
                    ['author' => 'Lucía Gómez', 'date' => '18 Jul 2026 · 08:40', 'text' => 'Ya actualicé la bibliografía compartida para que todos trabajemos con el mismo formato.', 'relation' => null],
                ],
            ],
            $common + [
                'id' => 2, 'type' => 'Proyecto integrador', 'type_key' => 'integrator', 'status' => 'Aprobado', 'status_key' => 'approved', 'metric_bucket' => 'active',
                'title' => 'Aplicación móvil de apoyo al aprendizaje inclusivo', 'subtitle' => 'Herramientas accesibles para acompañamiento académico.',
                'tags' => ['Accesibilidad', 'Educación'], 'technologies' => ['Flutter'], 'activity_order' => 3,
                'tutor' => 'Msc. Elena Ruiz', 'last_activity' => 'Proyecto aprobado · 12 Jul 2026', 'stage' => 'Preparación final', 'progress' => 76,
                'context' => ['Fecha de aprobación' => '12 Jul 2026', 'Documentos pendientes' => 'Ejecutable y anexos', 'Siguiente etapa' => 'Asignación de tribunal'],
                'action_label' => 'Preparar documentos finales', 'next_action' => 'Completar los documentos requeridos para tribunal.', 'latest_delivery' => null, 'observations' => [], 'activities' => [], 'comments' => [],
            ],
            $common + [
                'id' => 3, 'type' => 'Titulación', 'type_key' => 'thesis', 'status' => 'En tribunal', 'status_key' => 'defense', 'metric_bucket' => 'active',
                'title' => 'Plataforma para seguimiento de prácticas preprofesionales', 'subtitle' => 'Control académico de convenios, evidencias y evaluaciones.',
                'tags' => ['Prácticas', 'Evaluación'], 'technologies' => ['Laravel'], 'activity_order' => 2,
                'tutor' => 'Ing. Pablo Torres', 'last_activity' => 'Defensa programada · 15 Jul 2026', 'stage' => 'Defensa', 'progress' => 91,
                'context' => ['Tribunal asignado' => '3 docentes', 'Fecha programada' => '29 Jul 2026 · 10:00', 'Evaluación' => 'Pendiente'],
                'action_label' => 'Ver defensa', 'next_action' => 'Preparar la presentación y evidencias.', 'latest_delivery' => null, 'observations' => [], 'activities' => [], 'comments' => [],
            ],
            $common + [
                'id' => 4, 'type' => 'Proyecto de vinculación', 'type_key' => 'community', 'status' => 'Publicado', 'status_key' => 'published', 'metric_bucket' => 'finished',
                'title' => 'Portal comunitario para alfabetización digital', 'subtitle' => 'Recursos tecnológicos para comunidades rurales.', 'period' => '2025-II',
                'tags' => ['Vinculación', 'Inclusión digital'], 'technologies' => ['WordPress'], 'activity_order' => 1,
                'tutor' => 'Msc. Diana Vega', 'last_activity' => 'Publicado en repositorio · 03 Jul 2026', 'stage' => 'Publicado', 'progress' => 100,
                'context' => ['Fecha de publicación' => '03 Jul 2026', 'Repositorio' => 'Disponible públicamente', 'Categoría' => 'Vinculación · Inclusión digital'],
                'action_label' => 'Ver en repositorio', 'repository_id' => 3, 'next_action' => 'El expediente se encuentra concluido y publicado.', 'latest_delivery' => null, 'observations' => [], 'activities' => [], 'comments' => [],
            ],
        ];
    }
}
