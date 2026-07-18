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
        $projects = array_filter($this->projects(), static fn (array $project): bool => in_array($userId, $project['user_ids'], true));
        return array_values(array_map(fn (array $project): array => $this->enrichProject($project), $projects));
    }

    /** Busca un expediente comprobando temporalmente su pertenencia. */
    public function findProjectForUser(int $projectId, int $userId): ?array
    {
        foreach ($this->getProjectsForUser($userId) as $project) {
            if ($project['id'] === $projectId) {
                return $project;
            }
        }
        return null;
    }

    public function getProjectMetrics(array $projects): array
    {
        $counts = ['active' => 0, 'review' => 0, 'changes' => 0, 'finished' => 0];
        foreach ($projects as $project) {
            $bucket = $project['metric_bucket'];
            if (isset($counts[$bucket])) $counts[$bucket]++;
        }
        return [
            ['key' => 'active', 'label' => 'Activos', 'icon' => 'fa-folder-open', 'count' => $counts['active']],
            ['key' => 'review', 'label' => 'En revisión', 'icon' => 'fa-magnifying-glass', 'count' => $counts['review']],
            ['key' => 'changes', 'label' => 'Requieren cambios', 'icon' => 'fa-triangle-exclamation', 'count' => $counts['changes']],
            ['key' => 'finished', 'label' => 'Finalizados', 'icon' => 'fa-circle-check', 'count' => $counts['finished']],
        ];
    }

    public function getDetailTabs(): array
    {
        return [
            'summary' => ['label' => 'Resumen', 'icon' => 'fa-chart-pie'],
            'deliveries' => ['label' => 'Entregas', 'icon' => 'fa-file-arrow-up'],
            'observations' => ['label' => 'Observaciones', 'icon' => 'fa-list-check'],
            'comments' => ['label' => 'Comentarios', 'icon' => 'fa-comments'],
            'history' => ['label' => 'Historial', 'icon' => 'fa-clock-rotate-left'],
            'participants' => ['label' => 'Participantes', 'icon' => 'fa-users'],
            'calendar' => ['label' => 'Calendario', 'icon' => 'fa-calendar-days'],
            'final-documents' => ['label' => 'Documentos finales', 'icon' => 'fa-box-archive'],
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
                'file' => 'Informe_metodologico_v4.pdf', 'size' => '2.4 MB',
                'comment' => 'Versión preparada para la revisión académica del tutor.',
            ],
            ['version' => 'v3', 'title' => 'Marco teórico y propuesta', 'date' => '24 Jun 2026', 'status' => 'Requiere cambios', 'stage' => 'Desarrollo', 'author' => $project['participants'][0]['name'], 'file' => 'Informe_proyecto_v3.pdf', 'size' => '2.1 MB', 'comment' => 'Entrega anterior conservada como parte de la trazabilidad.'],
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
            ['label' => 'Tribunal', 'members' => in_array($project['status_key'], ['defense', 'published'], true) ? [
                ['initial' => 'J1', 'name' => 'Msc. Ana Morales', 'role' => 'Jurado 1', 'email' => 'ana.morales@libertador.edu.ec', 'status' => 'Asignado', 'assigned_at' => '15 Jul 2026'],
                ['initial' => 'J2', 'name' => 'Ing. Luis Paredes', 'role' => 'Jurado 2', 'email' => 'luis.paredes@libertador.edu.ec', 'status' => 'Asignado', 'assigned_at' => '15 Jul 2026'],
                ['initial' => 'J3', 'name' => 'Msc. Rosa León', 'role' => 'Jurado 3', 'email' => 'rosa.leon@libertador.edu.ec', 'status' => 'Asignado', 'assigned_at' => '15 Jul 2026'],
            ] : []],
        ];
        $project['history'] = [
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
            'user_ids' => [1], 'career' => 'Tecnología Superior en Desarrollo de Software',
            'period' => '2026-I', 'role' => 'Estudiante líder', 'tags' => [], 'technologies' => [],
            'participants' => [
                ['initial' => 'C', 'name' => 'Carlos Martínez', 'role' => 'Líder'],
                ['initial' => 'A', 'name' => 'Andrés Pérez', 'role' => 'Integrante'],
                ['initial' => 'L', 'name' => 'Lucía Gómez', 'role' => 'Integrante'],
            ],
        ];

        return [
            $common + [
                'id' => 1, 'type' => 'Trabajo de titulación', 'type_key' => 'thesis', 'status' => 'En revisión', 'status_key' => 'review', 'metric_bucket' => 'review',
                'title' => 'Sistema de Gestión Documental Académica', 'subtitle' => 'Seguimiento, revisión y publicación de proyectos académicos.',
                'tags' => ['Gestión académica', 'Trazabilidad'], 'technologies' => ['PHP', 'MariaDB'], 'activity_order' => 4,
                'tutor' => 'Ing. Tutor Asignado', 'last_activity' => 'Revisión del tutor · 17 Jul 2026', 'stage' => 'Revisión académica', 'progress' => 58,
                'context' => ['Última entrega' => 'Informe metodológico · v4', 'Próxima revisión' => '22 Jul 2026', 'Observaciones pendientes' => '2 de prioridad alta'],
                'action_label' => 'Continuar seguimiento', 'next_action' => 'Atender las observaciones de metodología y referencias.',
                'latest_delivery' => ['version' => 'v4', 'title' => 'Informe metodológico', 'date' => '16 Jul 2026', 'status' => 'En revisión'],
                'observations' => [
                    ['title' => 'Marco metodológico', 'text' => 'Ampliar el enfoque y justificar los instrumentos.', 'status' => 'Pendiente'],
                    ['title' => 'Referencias', 'text' => 'Unificar citas y bibliografía en formato APA 7.', 'status' => 'Pendiente'],
                ],
                'activities' => [['title' => 'Versión 4 registrada', 'date' => '16 Jul'], ['title' => 'Tutor inició la revisión', 'date' => '17 Jul']],
                'comments' => [['author' => 'Ing. Tutor Asignado', 'date' => '17 Jul 2026', 'text' => 'Revisaremos primero metodología. Este comentario es general y no está asociado a un archivo.', 'relation' => null]],
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
                'id' => 3, 'type' => 'Trabajo de titulación', 'type_key' => 'thesis', 'status' => 'En defensa', 'status_key' => 'defense', 'metric_bucket' => 'active',
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
                'action_label' => 'Ver en repositorio', 'repository_id' => 1, 'next_action' => 'El expediente se encuentra concluido y publicado.', 'latest_delivery' => null, 'observations' => [], 'activities' => [], 'comments' => [],
            ],
        ];
    }
}
