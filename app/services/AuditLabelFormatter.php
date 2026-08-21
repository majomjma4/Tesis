<?php

declare(strict_types=1);

final class AuditLabelFormatter
{
    private const ACTIONS = [
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

    private const CONTEXTS = [
        'delivery' => 'Entrega del proyecto',
        'project' => 'Proyecto',
        'project_delivery' => 'Entrega del proyecto',
        'project_file' => 'Archivo del proyecto',
        'project_file_version_change' => 'Cambio de versión del archivo',
        'project_review' => 'Revisión del proyecto',
        'notification' => 'Notificación',
        'support_material' => 'Material de apoyo',
        'user' => 'Usuario',
    ];

    public static function action(string $code, string $storedLabel = ''): string
    {
        return self::ACTIONS[$code] ?? (trim($storedLabel) !== '' ? trim($storedLabel) : 'Actividad auditada (evento no catalogado)');
    }

    public static function context(string $code): string
    {
        return self::CONTEXTS[$code] ?? 'Contexto no catalogado';
    }
}
