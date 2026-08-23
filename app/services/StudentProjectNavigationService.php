<?php

declare(strict_types=1);

/** Resuelve la presentación navegable de un proyecto estudiantil. */
final class StudentProjectNavigationService
{
    public function resolve(array $project, array $capabilities): array
    {
        $id = (int) ($project['id'] ?? 0);
        $status = (string) ($project['status_key'] ?? '');
        $detail = route('project-detail') . '&id=' . $id;
        $result = [
            'category' => in_array($status, ['published', 'completed'], true) ? 'finished' : 'development',
            'action_url' => $detail,
            'action_label' => 'Ver proyecto',
            'action_tab' => 'summary',
            'can_publish' => !empty($capabilities['publish_project']),
            'phase_context' => ['Etapa' => (string) ($project['stage'] ?? ''), 'Expediente' => (string) ($project['status'] ?? '')],
        ];

        if ($status === 'published' && !empty($project['repository_available'])) {
            $result['action_url'] = route('repository-detail') . '&id=' . $id;
            $result['action_label'] = 'Abrir ficha institucional';
            $result['phase_context'] = ['Publicación' => $project['key_dates'][1]['value'] ?? 'Publicada', 'Disponibilidad' => 'Repositorio institucional'];
            return $result;
        }

        $mapping = [
            'under_review' => ['review', 'Atender revisión'],
            'approved' => ['documents', 'Preparar documentos'],
            'defense' => ['information', 'Consultar evaluación'],
            'tribunal_approved' => ['documents', 'Preparar publicación'],
        ];
        if (isset($mapping[$status])) {
            [$tab, $label] = $mapping[$status];
            $result['action_tab'] = $tab;
            $result['action_label'] = $label;
            $result['action_url'] .= '&tab=' . $tab;
        }
        $result['phase_context'] = match ($status) {
            'under_review' => ['Última entrega' => $project['latest_delivery']['version'] ?? 'Sin entregas', 'Observaciones pendientes' => (int) ($project['review_situation']['pending_count'] ?? 0)],
            'approved' => ['Aprobación' => $project['key_dates'][1]['value'] ?? 'Registrada', 'Documentos finales' => !empty($project['final_documents']) ? 'Disponibles' : 'Pendientes'],
            'defense' => ['Fecha' => $project['key_dates'][2]['value'] ?? 'Por programar', 'Evaluación' => 'En proceso'],
            'tribunal_approved' => ['Tribunal' => 'Aprobado', 'Publicación' => 'Pendiente'],
            'completed' => ['Etapa' => (string) ($project['stage'] ?? 'Finalizado'), 'Expediente' => (string) ($project['status'] ?? 'Completado')],
            default => $result['phase_context'],
        };
        return $result;
    }
}
