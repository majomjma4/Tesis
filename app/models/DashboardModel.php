<?php

declare(strict_types=1);

final class DashboardModel
{
    public function getAdminDashboard(): array
    {
        $connection = Database::connection();
        return [
            'users' => $this->adminUserSummary($connection),
            'projects' => $this->adminProjectSummary($connection),
            'activity' => $this->adminActivity($connection),
            'alerts' => $this->adminAlerts($connection),
            'dates' => $this->adminUpcomingDates($connection),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function emptyAdminDashboard(): array
    {
        return [
            'users' => ['total' => 0, 'active' => 0, 'blocked' => 0, 'recent' => 0],
            'projects' => ['total' => 0, 'active' => 0, 'items' => []],
            'activity' => [],
            'alerts' => [],
            'dates' => [],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function adminUserSummary(PDO $connection): array
    {
        $row = $connection->query("SELECT COUNT(*) total, SUM(status='active') active, SUM(status='blocked') blocked, SUM(status='active' AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY)) recent FROM users WHERE deleted_at IS NULL AND purged_at IS NULL")->fetch() ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'blocked' => (int) ($row['blocked'] ?? 0),
            'recent' => (int) ($row['recent'] ?? 0),
        ];
    }

    private function adminProjectSummary(PDO $connection): array
    {
        $row=$connection->query("SELECT COUNT(*) total,SUM(status IN ('development','under_review','changes_required')) active,SUM(status='development') development,SUM(status='under_review') review,SUM(status='changes_required') attention,SUM(status IN ('approved','completed','published')) finished FROM projects WHERE deleted_at IS NULL")->fetch()?:[];
        $items=[
            ['status'=>'development','label'=>'En desarrollo','count'=>(int)($row['development']??0),'url'=>route('projects').'&status=development'],
            ['status'=>'under_review','label'=>'En revisión','count'=>(int)($row['review']??0),'url'=>route('projects').'&status=under_review'],
            ['status'=>'changes_required','label'=>'Requieren cambios','count'=>(int)($row['attention']??0),'url'=>route('projects').'&status=changes_required'],
            ['status'=>'finished','label'=>'Cerrados o publicados','count'=>(int)($row['finished']??0),'url'=>route('projects')],
        ];
        return ['total'=>(int)($row['total']??0),'active'=>(int)($row['active']??0),'items'=>$items];
    }

    private function adminActivity(PDO $connection): array
    {
        $statement = $connection->query("SELECT action, detail, created_at, full_name, project_id FROM (SELECT pal.action,COALESCE(pal.reason,p.title) detail,pal.created_at,u.full_name,p.id project_id FROM project_audit_log pal LEFT JOIN users u ON u.id=pal.user_id INNER JOIN projects p ON p.id=pal.project_id UNION ALL SELECT aal.action,CONCAT('Cuenta de ',COALESCE(target.full_name,'usuario')) detail,aal.created_at,actor.full_name,NULL project_id FROM admin_audit_log aal LEFT JOIN users actor ON actor.id=aal.actor_user_id LEFT JOIN users target ON aal.entity_type='user' AND target.id=aal.entity_id) activity ORDER BY created_at DESC LIMIT 6");
        $labels=['project_created'=>'Proyecto creado','project_updated'=>'Proyecto actualizado','project_trashed'=>'Proyecto enviado a la papelera','project_restored'=>'Proyecto restaurado','project_published'=>'Proyecto publicado','project_unpublished'=>'Publicación retirada','delivery_submitted'=>'Entrega registrada','users_bulk_imported'=>'Usuarios importados','user_created'=>'Usuario creado','user_updated'=>'Usuario actualizado','user_trashed'=>'Usuario enviado a la papelera','user_restored'=>'Usuario restaurado','password_reset'=>'Contraseña restablecida','notification_sent'=>'Notificación enviada','demo_users_imported'=>'Usuarios de prueba importados','demo_teacher_updated'=>'Docente de prueba actualizado','demo_catalog_configured'=>'Catálogo configurado'];
        return array_map(static fn(array $row): array => [
            'action' => $labels[(string)$row['action']] ?? 'Actividad administrativa',
            'detail' => (string) $row['detail'],
            'user' => (string) ($row['full_name'] ?: 'Sistema'),
            'date' => date('d/m/Y H:i', strtotime((string) $row['created_at'])),
            'url' => $row['project_id'] ? route('project-detail') . '&id=' . (int) $row['project_id'] : route('admin-users'),
        ], $statement->fetchAll());
    }

    private function adminAlerts(PDO $connection): array
    {
        $counts = [
            'blocked' => (int) $connection->query("SELECT COUNT(*) FROM users WHERE status='blocked' AND deleted_at IS NULL AND purged_at IS NULL")->fetchColumn(),
            'observations' => (int) $connection->query("SELECT COUNT(*) FROM project_observations o INNER JOIN projects p ON p.id=o.project_id WHERE o.status='pending' AND p.deleted_at IS NULL")->fetchColumn(),
            'trash' => (int) $connection->query("SELECT (SELECT COUNT(*) FROM projects WHERE deleted_at IS NOT NULL)+(SELECT COUNT(*) FROM users WHERE deleted_at IS NOT NULL AND purged_at IS NULL)")->fetchColumn(),
            'temporary' => (int) $connection->query("SELECT COUNT(*) FROM users u WHERE u.must_change_password=1 AND u.deleted_at IS NULL AND u.purged_at IS NULL AND u.temporary_password_expires_at IS NOT NULL AND u.temporary_password_expires_at <= CURRENT_TIMESTAMP AND NOT EXISTS(SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.code='administrator')")->fetchColumn(),
        ];
        $alerts = [];
        if ($counts['blocked'] > 0) $alerts[] = ['tone'=>'danger','icon'=>'fa-user-lock','title'=>'Cuentas bloqueadas','text'=>$counts['blocked'].' '.($counts['blocked'] === 1 ? 'cuenta requiere' : 'cuentas requieren').' revisión.','count'=>$counts['blocked'],'url'=>route('admin-users').'&status=blocked'];
        if ($counts['temporary'] > 0) $alerts[] = ['tone'=>'warning','icon'=>'fa-key','title'=>'Contraseñas vencidas','text'=>$counts['temporary'].' '.($counts['temporary'] === 1 ? 'persona debe' : 'personas deben').' actualizar su acceso.','count'=>$counts['temporary'],'url'=>route('admin-users')];
        if ($counts['observations'] > 0) $alerts[] = ['tone'=>'warning','icon'=>'fa-comment-dots','title'=>'Observaciones pendientes','text'=>$counts['observations'].' observaciones siguen abiertas.','count'=>$counts['observations'],'url'=>route('projects')];
        if ($counts['trash'] > 0) $alerts[] = ['tone'=>'neutral','icon'=>'fa-trash-can','title'=>'Elementos en papelera','text'=>$counts['trash'].' '.($counts['trash'] === 1 ? 'elemento permanece' : 'elementos permanecen').' recuperables.','count'=>$counts['trash'],'url'=>route('admin-trash')];
        return array_slice($alerts, 0, 4);
    }

    private function adminUpcomingDates(PDO $connection): array
    {
        $sql = "SELECT label, event_date, kind FROM (SELECT CONCAT('Inicio de ', name) label, starts_on event_date, 'period' kind FROM academic_periods WHERE starts_on >= CURRENT_DATE UNION ALL SELECT CONCAT('Cierre de ', name), ends_on, 'period' FROM academic_periods WHERE ends_on >= CURRENT_DATE UNION ALL SELECT title, event_date, 'event' FROM project_events WHERE event_date >= CURRENT_DATE AND is_completed=0) dates ORDER BY event_date ASC LIMIT 5";
        $rows = $connection->query($sql)->fetchAll();
        return array_map(static fn(array $row): array => [
            'label'=>(string)$row['label'],
            'date'=>date('d M Y', strtotime((string)$row['event_date'])),
            'days'=>max(0, (int) floor((strtotime((string)$row['event_date']) - strtotime(date('Y-m-d'))) / 86400)),
            'kind'=>(string)$row['kind'],
        ], $rows);
    }

    public function getSummary(): array
    {
        // Resumen superior enfocado en decisiones rapidas del estudiante.
        return [
            [
                'cardClass' => 'approved-card',
                'icon' => 'fa-circle-check',
                'label' => 'Estado del informe',
                'title' => 'En revision del tutor',
                'description' => 'La version enviada ya fue recibida y esta siendo evaluada.',
                'meta' => 'Version 4 enviada',
            ],
            [
                'cardClass' => 'review-card',
                'icon' => 'fa-list-check',
                'label' => 'Pendiente clave',
                'title' => 'Corregir metodologia',
                'description' => 'Es la observacion mas importante antes de reenviar el documento.',
                'meta' => 'Prioridad alta',
            ],
            [
                'cardClass' => 'changes-card',
                'icon' => 'fa-calendar-check',
                'label' => 'Proxima accion',
                'title' => 'Revisar observaciones',
                'description' => 'Bloque de trabajo recomendado para cerrar pendientes recientes.',
                'meta' => '05 Jul - 09:00',
            ],
            [
                'cardClass' => 'documents-card',
                'icon' => 'fa-hourglass-half',
                'label' => 'Tiempo restante',
                'title' => '6 dias para correcciones',
                'description' => 'Fecha objetivo para entregar una version corregida del informe.',
                'meta' => 'Limite sugerido: 10 Jul',
            ],
        ];
    }

    public function getCurrentReport(): array
    {
        // Informe principal del estudiante hasta conectar el modulo con persistencia real.
        return [
            'statusClass' => 'revision',
            'status' => 'En revision',
            'title' => 'Sistema de Gestion Documental',
            'description' => 'La version actual esta en revision. El foco ahora es resolver las observaciones de metodologia y referencias antes del siguiente envio.',
            'semester' => 'Septimo semestre',
            'tutor' => 'Ing. Tutor Asignado',
            'version' => 'Version 4',
            'document' => 'Informe_actualizado_v4.pdf',
            'lastDelivery' => '03 Jul 2026',
            'lastReview' => '04 Jul 2026',
            'pendingObservations' => '2 observaciones pendientes',
        ];
    }

    public function getTeamMembers(): array
    {
        // Integrantes de ejemplo hasta conectar el proyecto con usuarios reales.
        return [
            ['initial' => 'C', 'name' => 'Carlos Martinez', 'role' => 'Lider'],
            ['initial' => 'A', 'name' => 'Andres Perez', 'role' => 'Integrante'],
            ['initial' => 'L', 'name' => 'Lucia Gomez', 'role' => 'Integrante'],
        ];
    }

    public function getObservations(): array
    {
        // Observaciones accionables del informe actual.
        return [
            [
                'statusClass' => 'pending',
                'status' => 'Pendiente',
                'title' => 'Marco metodologico',
                'text' => 'Ampliar la descripcion del enfoque utilizado y justificar la tecnica de recoleccion de datos.',
                'date' => '04 Jul 2026',
            ],
            [
                'statusClass' => 'pending',
                'status' => 'Pendiente',
                'title' => 'Formato de referencias',
                'text' => 'Unificar el formato de citas y referencias bibliograficas antes del siguiente envio.',
                'date' => '04 Jul 2026',
            ],
        ];
    }

    public function getRecentActivity(): array
    {
        // Actividad resumida con eventos distintos entre si.
        return [
            ['icon' => 'fa-upload', 'title' => 'Version 4 enviada', 'text' => 'El informe actualizado fue registrado para revision.', 'time' => '03 Jul 2026'],
            ['icon' => 'fa-pen-to-square', 'title' => 'Revision del tutor', 'text' => 'Se registraron observaciones sobre la ultima version.', 'time' => '04 Jul 2026'],
            ['icon' => 'fa-folder-open', 'title' => 'Documento disponible', 'text' => 'La version revisada esta lista para consultar detalles y comentarios.', 'time' => '04 Jul 2026'],
        ];
    }

    public function getProcessDates(): array
    {
        // Fechas clave que ayudan a entender el avance real del proceso.
        return [
            ['label' => 'Ultima entrega', 'value' => '03 Jul 2026'],
            ['label' => 'Ultima revision', 'value' => '04 Jul 2026'],
            ['label' => 'Entrega objetivo', 'value' => '10 Jul 2026'],
        ];
    }

    public function getNotifications(): array
    {
        // Alertas utiles, sin repetir el listado de observaciones.
        return [
            ['title' => 'Revision finalizada', 'text' => 'Ya puedes consultar los comentarios de la version 4.', 'time' => 'Hace 2 horas'],
            ['title' => 'Prioridad sugerida', 'text' => 'Atiende primero la observacion del marco metodologico.', 'time' => 'Hoy'],
            ['title' => 'Entrega objetivo definida', 'text' => 'Planifica el reenvio corregido para el 10 de julio.', 'time' => 'Hoy'],
        ];
    }

    public function getReminders(): array
    {
        // Proximas acciones personales del usuario autenticado.
        return [
            ['date' => '05 Jul', 'title' => 'Corregir metodologia', 'text' => 'Ampliar enfoque, tecnica e instrumentos.'],
            ['date' => '07 Jul', 'title' => 'Normalizar referencias', 'text' => 'Unificar citas y bibliografia del informe.'],
            ['date' => '10 Jul', 'title' => 'Enviar version corregida', 'text' => 'Subir el documento final para nueva revision.'],
        ];
    }

    public function getCalendar(): array
    {
        // Estructura simple para pintar el calendario mensual en la vista.
        return [
            'month' => 'Julio 2026',
            'subtitle' => 'Recordatorios del semestre',
            'weekDays' => ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'],
            'days' => [
                ['number' => '29', 'class' => 'muted-day'],
                ['number' => '30', 'class' => 'muted-day'],
                ['number' => '1', 'class' => 'current-day'],
                ['number' => '2', 'class' => 'reminder-day'],
                ['number' => '3', 'class' => ''],
                ['number' => '4', 'class' => ''],
                ['number' => '5', 'class' => 'reminder-day'],
                ['number' => '6', 'class' => ''],
                ['number' => '7', 'class' => ''],
                ['number' => '8', 'class' => ''],
                ['number' => '9', 'class' => ''],
                ['number' => '10', 'class' => 'reminder-day'],
                ['number' => '11', 'class' => ''],
                ['number' => '12', 'class' => ''],
                ['number' => '13', 'class' => ''],
                ['number' => '14', 'class' => ''],
                ['number' => '15', 'class' => ''],
                ['number' => '16', 'class' => ''],
                ['number' => '17', 'class' => ''],
                ['number' => '18', 'class' => ''],
                ['number' => '19', 'class' => ''],
                ['number' => '20', 'class' => ''],
                ['number' => '21', 'class' => 'reminder-day'],
                ['number' => '22', 'class' => ''],
                ['number' => '23', 'class' => ''],
                ['number' => '24', 'class' => ''],
                ['number' => '25', 'class' => ''],
                ['number' => '26', 'class' => ''],
                ['number' => '27', 'class' => ''],
                ['number' => '28', 'class' => ''],
                ['number' => '29', 'class' => ''],
                ['number' => '30', 'class' => ''],
                ['number' => '31', 'class' => ''],
                ['number' => '1', 'class' => 'muted-day'],
                ['number' => '2', 'class' => 'muted-day'],
            ],
        ];
    }
}
