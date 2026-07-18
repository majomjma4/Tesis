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
        return array_values(array_filter($this->projects(), static fn (array $project): bool => in_array($userId, $project['user_ids'], true)));
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

    private function projects(): array
    {
        $common = [
            'user_ids' => [1], 'career' => 'Tecnología Superior en Desarrollo de Software',
            'period' => '2026-I', 'role' => 'Estudiante líder',
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
                'tutor' => 'Msc. Elena Ruiz', 'last_activity' => 'Proyecto aprobado · 12 Jul 2026', 'stage' => 'Preparación final', 'progress' => 76,
                'context' => ['Fecha de aprobación' => '12 Jul 2026', 'Documentos pendientes' => 'Ejecutable y anexos', 'Siguiente etapa' => 'Asignación de tribunal'],
                'action_label' => 'Preparar documentos finales', 'next_action' => 'Completar los documentos requeridos para tribunal.', 'latest_delivery' => null, 'observations' => [], 'activities' => [], 'comments' => [],
            ],
            $common + [
                'id' => 3, 'type' => 'Trabajo de titulación', 'type_key' => 'thesis', 'status' => 'En defensa', 'status_key' => 'defense', 'metric_bucket' => 'active',
                'title' => 'Plataforma para seguimiento de prácticas preprofesionales', 'subtitle' => 'Control académico de convenios, evidencias y evaluaciones.',
                'tutor' => 'Ing. Pablo Torres', 'last_activity' => 'Defensa programada · 15 Jul 2026', 'stage' => 'Defensa', 'progress' => 91,
                'context' => ['Tribunal asignado' => '3 docentes', 'Fecha programada' => '29 Jul 2026 · 10:00', 'Evaluación' => 'Pendiente'],
                'action_label' => 'Ver defensa', 'next_action' => 'Preparar la presentación y evidencias.', 'latest_delivery' => null, 'observations' => [], 'activities' => [], 'comments' => [],
            ],
            $common + [
                'id' => 4, 'type' => 'Proyecto de vinculación', 'type_key' => 'community', 'status' => 'Publicado', 'status_key' => 'published', 'metric_bucket' => 'finished',
                'title' => 'Portal comunitario para alfabetización digital', 'subtitle' => 'Recursos tecnológicos para comunidades rurales.',
                'tutor' => 'Msc. Diana Vega', 'last_activity' => 'Publicado en repositorio · 03 Jul 2026', 'stage' => 'Publicado', 'progress' => 100,
                'context' => ['Fecha de publicación' => '03 Jul 2026', 'Repositorio' => 'Disponible públicamente', 'Categoría' => 'Vinculación · Inclusión digital'],
                'action_label' => 'Ver en repositorio', 'repository_id' => 1, 'next_action' => 'El expediente se encuentra concluido y publicado.', 'latest_delivery' => null, 'observations' => [], 'activities' => [], 'comments' => [],
            ],
        ];
    }
}
