<?php

declare(strict_types=1);

final class AuditLabelFormatter
{
    private const RELEVANCE = [
        1 => [
            'academic_period_started', 'academic_period_closed', 'academic_period_activated', 'academic_period_planned',
            'academic_period_closure_reverted', 'admin_access_granted', 'thesis_management_granted',
            'thesis_management_revoked', 'password_changed', 'password_reset',
            'session_expired_inactivity', 'session_replaced', 'settings_updated',
            'trash_permanently_deleted', 'trash_batch_restored', 'user_trashed', 'user_restored',
            'notification_sent',
        ],
        2 => [
            'user_created', 'user_updated', 'user_status_changed', 'project_created', 'academic_type_created',
            'project_approved', 'project_published', 'project_republished', 'project_unpublished',
            'project_trashed', 'project_restored', 'project_status_changed',
            'project_submitted_for_review', 'project_document_review_completed', 'delivery_submitted',
            'project_publication_reverted', 'project_tribunal_approved', 'project_corrections_requested',
            'tribunal_assigned', 'tribunal_updated', 'tribunal_result_registered',
            'thesis_defense_information_updated', 'defense_attempt_started',
            'project_adjustment_request_created', 'project_adjustment_request_responded',
            'project_adjustment_request_addressed', 'project_adjustment_request_closed',
            'project_adjustment_request_approved', 'project_adjustment_request_rejected',
            'project_reopened_for_adjustment',
            'support_material_created', 'support_material_published', 'support_material_withdrawn',
            'support_material_restored', 'support_material_availability_changed', 'support_material.created',
            'support_material.published', 'support_material.withdrawn', 'support_material.restored',
            'support_material.availability_changed', 'support_material.trashed', 'support_material_trash_purged',
        ],
        3 => [
            'project_updated', 'project_description_updated', 'project_authors_updated',
            'project_workspace_file_added', 'project_workspace_file_removed',
            'project_workspace_file_replaced', 'project_publication_file_added',
            'project_publication_file_replaced', 'project_publication_file_excluded',
            'project_document_versions_archived', 'support_material.updated', 'support_material_updated',
            'support_material.file_added', 'academic_type_description_added',
            'academic_type_description_updated', 'academic_type_description_removed',
            'academic_keyword_created', 'academic_keyword_deleted', 'academic_keyword_updated',
            'academic_period_plan_updated', 'academic_period_plan_deleted',
            'academic_material_type_created', 'academic_material_type_updated',
            'avatar_updated', 'avatar_removed',
        ],
        4 => [
            'demo_teacher_updated', 'demo_users_imported', 'demo_catalog_configured',
            'profile_updated', 'support_material.presentation_selected',
            'support_material.presentation_changed', 'support_material.presentation_removed',
        ],
    ];

    private const ACTIONS = [
        'project_adjustment_request_approved' => 'Solicitud de modificación aprobada',
        'project_adjustment_request_rejected' => 'Solicitud de modificación rechazada',
        'project_reopened_for_adjustment' => 'Proyecto reabierto para modificación',
        'admin_access_granted' => 'Acceso administrativo otorgado',
        'avatar_updated' => 'Imagen de perfil actualizada',
        'delivery_submitted' => 'Entrega académica registrada',
        'notification_sent' => 'Comunicado institucional enviado',
        'password_reset' => 'Contraseña restablecida',
        'project_approved' => 'Proyecto aprobado',
        'project_document_review_completed' => 'Revisión documental completada',
        'project_published' => 'Proyecto publicado en el repositorio',
        'project_republished' => 'Publicación del proyecto actualizada',
        'project_restored' => 'Proyecto restaurado',
        'project_submitted_for_review' => 'Proyecto enviado a revisión',
        'project_trashed' => 'Proyecto enviado a Papelera',
        'project_unpublished' => 'Proyecto retirado del repositorio',
        'project_updated' => 'Información del proyecto actualizada',
        'project_workspace_file_added' => 'Archivo agregado al proyecto',
        'project_workspace_file_removed' => 'Archivo retirado del proyecto',
        'project_workspace_file_replaced' => 'Archivo del proyecto reemplazado',
        'session_expired_inactivity' => 'Sesión expirada por inactividad',
        'session_replaced' => 'Sesión activa reemplazada',
        'support_material.file_added' => 'Archivo agregado al material de apoyo',
        'user_status_changed' => 'Estado de usuario modificado',
        'user_updated' => 'Información de usuario actualizada',
    ];

    /** Labels safe for the project audit drawer; raw action codes must never reach its UI. */
    private const PROJECT_ACTIONS = [
        'project_created' => 'Registró el proyecto',
        'project_updated' => 'Actualizó la información del proyecto',
        'project_description_updated' => 'Actualizó la descripción del proyecto',
        'project_authors_updated' => 'Actualizó los integrantes del proyecto',
        'project_published' => 'Publicó el proyecto',
        'project_republished' => 'Volvió a publicar el proyecto',
        'project_unpublished' => 'Retiró el proyecto del repositorio',
        'project_withdrawn' => 'Retiró el proyecto del repositorio',
        'project_reincorporated' => 'Reincorporó el proyecto al repositorio',
        'project_availability_changed' => 'Cambió la disponibilidad del proyecto',
        'project_publication_reverted' => 'Revirtió la publicación del proyecto',
        'project_qa_prepared' => 'Preparó el proyecto para Gestión académica',
        'project_trashed' => 'Envió el proyecto a la Papelera',
        'project_restored' => 'Restauró el proyecto',
        'project.file_added' => 'Agregó un archivo al proyecto',
        'project.file_replaced' => 'Reemplazó un archivo del proyecto',
        'project.file_removed' => 'Retiró un archivo del proyecto',
        'project.file_restored' => 'Restauró un archivo del proyecto',
        'project.file_purged' => 'Eliminó definitivamente un archivo del proyecto',
        'project_file_added' => 'Agregó un archivo al proyecto',
        'project_file_replaced' => 'Reemplazó un archivo del proyecto',
        'project_file_removed' => 'Retiró un archivo del proyecto',
        'project_file_restored' => 'Restauró un archivo del proyecto',
        'project_file_purged' => 'Eliminó definitivamente un archivo del proyecto',
        'project_workspace_file_added' => 'Agregó un archivo al proyecto',
        'project_workspace_file_replaced' => 'Reemplazó un archivo del proyecto',
        'project_workspace_file_removed' => 'Retiró un archivo del proyecto',
        'project_workspace_file_restored' => 'Restauró un archivo del proyecto',
        'project_publication_file_added' => 'Agregó un archivo a la publicación',
        'project_publication_file_replaced' => 'Reemplazó un archivo de la publicación',
        'project_publication_file_excluded' => 'Excluyó un archivo de la publicación',
        'project_document_version_created' => 'Creó una nueva versión documental',
        'project_document_versions_archived' => 'Archivó versiones documentales',
        'project.presentation_selected' => 'Seleccionó el archivo de presentación',
        'project.presentation_changed' => 'Cambió el archivo de presentación',
        'project.presentation_removed' => 'Retiró el archivo de presentación',
        'project_submitted_for_review' => 'Envió el proyecto a revisión',
        'delivery_submitted' => 'Envió el proyecto a revisión',
        'project_document_review_completed' => 'Completó la revisión documental',
        'project_corrections_requested' => 'Solicitó correcciones',
        'review_requested_changes' => 'Solicitó correcciones',
        'project_approved' => 'Aprobó la revisión',
        'review_approved' => 'Aprobó la revisión',
        'project_tribunal_approved' => 'Registró la aprobación del tribunal',
        'tribunal_approved' => 'Registró la aprobación del tribunal',
        'project_adjustment_request_created' => 'Solicitó una modificación',
        'adjustment_requested' => 'Solicitó una modificación',
        'project_adjustment_request_responded' => 'Respondió la solicitud de modificación',
        'project_adjustment_request_addressed' => 'Atendió la solicitud de modificación',
        'project_adjustment_request_closed' => 'Cerró la solicitud de modificación',
        'project_adjustment_request_approved' => 'Aprobó la solicitud de modificación',
        'adjustment_approved' => 'Aprobó la solicitud de modificación',
        'project_adjustment_request_rejected' => 'Rechazó la solicitud de modificación',
        'adjustment_rejected' => 'Rechazó la solicitud de modificación',
        'project_reopened_for_adjustment' => 'Reabrió el proyecto para modificación',
        'project_legacy_status_migrated' => 'Normalizó un estado académico heredado',
        'project_status_changed' => 'Cambió el estado del proyecto',
        'tribunal_assigned' => 'Asignó el tribunal',
        'tribunal_updated' => 'Actualizó el tribunal',
        'tribunal_result_registered' => 'Registró el resultado del tribunal',
        'thesis_defense_information_updated' => 'Actualizó la información de la defensa',
        'defense_attempt_started' => 'Inició un intento de defensa',
        'repository_direct_publish' => 'Publicó el proyecto en el repositorio',
    ];

    private const CONTEXTS = [
        'project_adjustment_request' => 'Solicitud de modificación',
        'delivery' => 'Entrega del proyecto',
        'project' => 'Proyecto',
        'project_delivery' => 'Entrega del proyecto',
        'project_file' => 'Archivo del proyecto',
        'project_file_version_change' => 'Cambio de versión del archivo',
        'project_review' => 'Revisión del proyecto',
        'notification' => 'Notificación',
        'support_material' => 'Material de apoyo',
        'users' => 'Usuarios',
        'projects' => 'Proyectos',
        'materials' => 'Materiales de apoyo',
        'user' => 'Usuario',
    ];

    public static function action(string $code, string $storedLabel = ''): string
    {
        return self::ACTIONS[$code] ?? (trim($storedLabel) !== '' ? trim($storedLabel) : 'Actividad auditada (evento no catalogado)');
    }

    public static function projectAction(string $code): string
    {
        return self::PROJECT_ACTIONS[$code] ?? 'Registró una actividad en el proyecto';
    }

    public static function context(string $code): string
    {
        return self::CONTEXTS[$code] ?? 'Contexto no catalogado';
    }

    public static function relevance(string $code): int
    {
        foreach (self::RELEVANCE as $level => $actions) {
            if (in_array($code, $actions, true)) return $level;
        }
        return 3;
    }

    /** @return array{action_label:string,relevance:int} */
    public static function present(string $code, string $storedLabel = ''): array
    {
        return [
            'action_label' => self::action($code, $storedLabel),
            'relevance' => self::relevance($code),
        ];
    }

    /** @return list<string> */
    public static function actionsForMaxLevel(int $maxLevel): array
    {
        $actions = [];
        foreach (self::RELEVANCE as $level => $codes) {
            if ($level <= $maxLevel) $actions = array_merge($actions, $codes);
        }
        return array_values(array_unique($actions));
    }
}
