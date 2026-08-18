<?php

declare(strict_types=1);

final class DashboardModel
{
    public function getAdminDashboard(): array
    {
        $connection = Database::connection();
        $userSummary = $this->adminUserSummary($connection);
        $projectSummary = $this->adminProjectSummary($connection);
        $trashSummary = $this->adminTrashSummary($connection);
        $institutionalContext = $this->adminInstitutionalContext($connection);

        $summary = [
            'total_projects' => [
                'count' => (int) $projectSummary['total'],
                'route' => route('projects'),
            ],
            'active_projects' => [
                'count' => (int) $projectSummary['in_flow'],
                'route' => route('projects'),
            ],
            'approved_projects' => [
                'count' => (int) $projectSummary['approved'],
                'route' => route('projects'),
            ],
            'published_projects' => [
                'count' => (int) ($projectSummary['published'] ?? 0),
                'route' => route('admin-repository'),
            ],
            'active_users' => [
                'active' => (int) $userSummary['active'],
                'total' => (int) $userSummary['total'],
                'route' => route('admin-users') . '&status=active',
            ],
        ];

        $attention = $this->adminAttentionAlertsClean($connection, $userSummary, $trashSummary['total']);
        $platformStatus = $this->adminPlatformStatus($connection, $userSummary, $trashSummary);
        $statusDistribution = $this->adminStatusDistribution($connection, (int) $projectSummary['total']);
        $recentAdminActivity = $this->adminRecentAdminActivity($connection);
        $upcomingDates = $this->adminUpcomingDates($connection);

        // LEGACY ALIASES (Mantener transitoriamente para compatibilidad)
        $legacyPendingAdjustments = $this->adminPendingAdjustmentsCount($connection);
        $legacyKpis = [
            'active_projects' => $summary['active_projects'],
            'pending_adjustments' => ['count' => $legacyPendingAdjustments, 'route' => route('projects')],
            'projects_with_observations' => ['count' => (int) $projectSummary['pending_observations'], 'route' => route('projects')],
            'active_users' => $summary['active_users'],
        ];

        return [
            // NUEVO CONTRATO DEFINITIVO DE FASE 1C + 4F
            'institutional_context' => $institutionalContext,
            'summary' => $summary,
            'platform_status' => $platformStatus,
            'attention' => $attention,
            'trash' => $trashSummary,
            'project_status_distribution' => $statusDistribution,
            'recent_admin_activity' => $recentAdminActivity,
            'updated_at' => date('Y-m-d H:i:s'),

            // ALIASES DE COMPATIBILIDAD TRANSITORIA — LEGACY / REMOVE AFTER ADMIN DASHBOARD FINAL QA
            'kpis' => $legacyKpis,
            'upcoming_dates' => $upcomingDates,
            'recent_activity' => $recentAdminActivity,
            'users' => $userSummary,
            'projects' => $projectSummary,
            'weekly_activity' => $this->adminWeeklyActivity($connection),
            'activity' => $recentAdminActivity,
            'alerts' => $this->adminAlerts($connection, (int) $projectSummary['pending_observations']),
            'dates' => $upcomingDates,
        ];
    }

    public function emptyAdminDashboard(): array
    {
        return [
            'institutional_context' => [
                'academic_period' => null,
                'next_institutional_date' => null,
            ],
            'summary' => [
                'total_projects' => ['count' => 0, 'route' => route('projects')],
                'active_projects' => ['count' => 0, 'route' => route('projects')],
                'approved_projects' => ['count' => 0, 'route' => route('projects')],
                'published_projects' => ['count' => 0, 'route' => route('admin-repository')],
                'active_users' => ['active' => 0, 'total' => 0, 'route' => route('admin-users') . '&status=active'],
            ],
            'platform_status' => [
                'access' => [
                    'active' => 0,
                    'total' => 0,
                    'percentage' => 100,
                    'expired_credentials' => 0,
                    'blocked_accounts' => 0,
                    'route' => route('admin-users'),
                ],
                'retention' => [
                    'total' => 0,
                    'projects' => 0,
                    'users' => 0,
                    'support_materials' => 0,
                    'retention_days' => [
                        'projects' => 60,
                        'users' => 60,
                        'support_materials' => 60,
                    ],
                    'automatic_purge' => true,
                    'route' => route('admin-trash'),
                ],
            ],
            'attention' => [],
            'trash' => [
                'total' => 0,
                'projects' => 0,
                'users' => 0,
                'support_materials' => 0,
                'route' => route('admin-trash'),
            ],
            'project_status_distribution' => [],
            'recent_admin_activity' => [],
            'updated_at' => null,

            // LEGACY ALIASES
            'kpis' => [
                'active_projects' => ['count' => 0, 'route' => route('projects')],
                'pending_adjustments' => ['count' => 0, 'route' => route('projects')],
                'projects_with_observations' => ['count' => 0, 'route' => route('projects')],
                'active_users' => ['active' => 0, 'total' => 0, 'route' => route('admin-users') . '&status=active'],
            ],
            'upcoming_dates' => [],
            'recent_activity' => [],
            'users' => ['total' => 0, 'active' => 0, 'blocked' => 0, 'recent' => 0],
            'projects' => ['total' => 0, 'active' => 0, 'pending_observations' => 0, 'items' => []],
            'weekly_activity' => 0,
            'activity' => [],
            'alerts' => [],
            'dates' => [],
        ];
    }

    private function adminInstitutionalContext(PDO $connection): array
    {
        $period = null;
        try {
            $stmt = $connection->query("SELECT id, code, name, starts_on, ends_on, status FROM academic_periods WHERE status = 'active' ORDER BY id DESC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $endsOn = !empty($row['ends_on']) ? strtotime((string) $row['ends_on']) : null;
                $daysRemaining = null;
                if ($endsOn !== null) {
                    $diff = (int) ceil(($endsOn - time()) / 86400);
                    $daysRemaining = max(0, $diff);
                }
                $period = [
                    'id' => (int) $row['id'],
                    'code' => (string) ($row['code'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'status' => (string) ($row['status'] ?? 'active'),
                    'starts_on' => (string) ($row['starts_on'] ?? ''),
                    'ends_on' => (string) ($row['ends_on'] ?? ''),
                    'days_remaining' => $daysRemaining,
                    'route' => route('admin-academic'),
                ];
            }
        } catch (Throwable $e) {
            error_log('adminInstitutionalContext error: ' . $e->getMessage());
        }

        $nextDate = null;
        if ($period !== null && !empty($period['ends_on'])) {
            $nextDate = [
                'title' => 'Cierre de ' . $period['name'],
                'date' => $period['ends_on'],
                'days_remaining' => $period['days_remaining'],
                'route' => route('calendar'),
            ];
        }

        return [
            'academic_period' => $period,
            'next_institutional_date' => $nextDate,
        ];
    }

    private function adminTrashSummary(PDO $connection): array
    {
        $projects = (int) $connection->query("SELECT COUNT(*) FROM projects WHERE deleted_at IS NOT NULL")->fetchColumn();
        $users = (int) $connection->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NOT NULL")->fetchColumn();
        $supportMaterials = (int) $connection->query("SELECT COUNT(*) FROM support_materials WHERE deleted_at IS NOT NULL")->fetchColumn();
        $total = $projects + $users + $supportMaterials;

        return [
            'total' => $total,
            'projects' => $projects,
            'users' => $users,
            'support_materials' => $supportMaterials,
            'route' => route('admin-trash'),
        ];
    }

    private function adminPendingAdjustmentsCount(PDO $connection): int
    {
        return (int) $connection->query("SELECT COUNT(*) FROM project_adjustment_requests WHERE status='pending'")->fetchColumn();
    }

    private function adminPlatformStatus(PDO $connection, array $userSummary, array $trashSummary): array
    {
        $expiredTempPassCount = (int) $connection->query(
            "SELECT COUNT(*) FROM users WHERE temporary_password_expires_at IS NOT NULL AND temporary_password_expires_at <= CURRENT_TIMESTAMP AND deleted_at IS NULL AND purged_at IS NULL"
        )->fetchColumn();

        $activeUsers = (int) ($userSummary['active'] ?? 0);
        $totalUsers = (int) ($userSummary['total'] ?? 0);
        $blockedUsers = (int) ($userSummary['blocked'] ?? 0);
        $percentage = $totalUsers > 0 ? (int) round(($activeUsers / $totalUsers) * 100) : 100;

        $settings = (new SystemSettingModel())->all();

        return [
            'access' => [
                'active' => $activeUsers,
                'total' => $totalUsers,
                'percentage' => $percentage,
                'expired_credentials' => $expiredTempPassCount,
                'blocked_accounts' => $blockedUsers,
                'route' => route('admin-users'),
            ],
            'retention' => [
                'total' => (int) ($trashSummary['total'] ?? 0),
                'projects' => (int) ($trashSummary['projects'] ?? 0),
                'users' => (int) ($trashSummary['users'] ?? 0),
                'support_materials' => (int) ($trashSummary['support_materials'] ?? 0),
                'retention_days' => [
                    'projects' => (int) ($settings['retention_projects_days'] ?? 60),
                    'users' => (int) ($settings['retention_users_days'] ?? 60),
                    'support_materials' => (int) ($settings['retention_materials_days'] ?? 60),
                ],
                'automatic_purge' => true,
                'route' => route('admin-trash'),
            ],
        ];
    }

    private function adminAttentionAlertsClean(PDO $connection, array $userSummary, int $trashTotal): array
    {
        $expiredTempPassCount = (int) $connection->query(
            "SELECT COUNT(*) FROM users WHERE temporary_password_expires_at IS NOT NULL AND temporary_password_expires_at <= CURRENT_TIMESTAMP AND deleted_at IS NULL AND purged_at IS NULL"
        )->fetchColumn();

        $blockedCount = (int) ($userSummary['blocked'] ?? 0);

        $alerts = [];
        if ($expiredTempPassCount > 0) {
            $alerts[] = [
                'key' => 'expired_temporary_passwords',
                'severity' => 'critical',
                'count' => $expiredTempPassCount,
                'title' => 'Contraseñas temporales vencidas',
                'description' => $expiredTempPassCount . ' ' . ($expiredTempPassCount === 1 ? 'persona debe' : 'personas deben') . ' actualizar su acceso.',
                'route' => route('admin-users'),
            ];
        }
        if ($blockedCount > 0) {
            $alerts[] = [
                'key' => 'blocked_accounts',
                'severity' => 'high',
                'count' => $blockedCount,
                'title' => 'Cuentas bloqueadas',
                'description' => $blockedCount . ' ' . ($blockedCount === 1 ? 'cuenta requiere' : 'cuentas requieren') . ' revisión.',
                'route' => route('admin-users') . '&status=blocked',
            ];
        }
        if ($trashTotal > 0) {
            $alerts[] = [
                'key' => 'trash_items',
                'severity' => 'medium',
                'count' => $trashTotal,
                'title' => 'Elementos en papelera',
                'description' => $trashTotal . ' ' . ($trashTotal === 1 ? 'elemento permanece recuperable.' : 'elementos permanecen recuperables.'),
                'route' => route('admin-trash'),
            ];
        }

        $severityOrder = ['critical' => 3, 'high' => 2, 'medium' => 1];
        usort($alerts, static fn(array $a, array $b): int => ($severityOrder[$b['severity']] ?? 0) <=> ($severityOrder[$a['severity']] ?? 0));
        return $alerts;
    }

    private function adminRecentAdminActivity(PDO $connection): array
    {
        try {
            $stmt = $connection->query("SELECT id, action, action_label, module, entity_type, element_label, created_at FROM admin_audit_log ORDER BY id DESC LIMIT 6");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $activity = [];
            foreach ($rows as $r) {
                $actionKey = (string) ($r['action'] ?? '');
                $actionLabel = !empty($r['action_label']) ? (string) $r['action_label'] : $this->humanizeAction($actionKey);
                $resource = !empty($r['element_label']) ? (string) $r['element_label'] : (!empty($r['module']) ? (string) $r['module'] : 'Sistema');
                $activity[] = [
                    'action' => $actionLabel,
                    'label' => $actionLabel,
                    'actor' => 'Administración',
                    'resource' => $resource,
                    'occurred_at' => (string) ($r['created_at'] ?? ''),
                    'date' => (string) ($r['created_at'] ?? ''),
                    'route' => route('admin-reports'),
                ];
            }
            return $activity;
        } catch (Throwable $e) {
            error_log('adminRecentAdminActivity error: ' . $e->getMessage());
            return [];
        }
    }

    private function humanizeAction(string $action): string
    {
        return match ($action) {
            'notification_sent' => 'Comunicado institucional enviado',
            'admin_access_granted' => 'Acceso administrativo otorgado',
            'user_updated' => 'Usuario actualizado',
            'session_expired_inactivity' => 'Sesión expirada por inactividad',
            'user_status_changed' => 'Estado de usuario actualizado',
            default => str_replace('_', ' ', ucfirst($action)),
        };
    }

    private function adminUserSummary(PDO $connection): array
    {
        $statement = $connection->query(
            "SELECT COUNT(*) total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) active, SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) blocked, SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) recent FROM users WHERE deleted_at IS NULL AND purged_at IS NULL"
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'blocked' => (int) ($row['blocked'] ?? 0),
            'recent' => (int) ($row['recent'] ?? 0),
        ];
    }

    private function adminProjectSummary(PDO $connection): array
    {
        $statement = $connection->query(
            "SELECT
                COUNT(*) total,
                SUM(CASE WHEN p.status = 'published' THEN 1 ELSE 0 END) published,
                SUM(CASE
                    WHEN p.status = 'completed' THEN 1
                    WHEN p.status = 'tribunal_approved' AND pt.code = 'thesis' THEN 1
                    WHEN p.status = 'approved' AND pt.code != 'thesis' THEN 1
                    ELSE 0
                END) approved,
                SUM(CASE
                    WHEN p.status IN ('development', 'under_review', 'corrections_requested', 'changes_required') THEN 1
                    WHEN p.status = 'approved' AND pt.code = 'thesis' THEN 1
                    WHEN p.status = 'defense' AND pt.code = 'thesis' THEN 1
                    ELSE 0
                END) in_flow
             FROM projects p
             INNER JOIN project_types pt ON pt.id = p.project_type_id
             WHERE p.deleted_at IS NULL"
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        $pendingObs = (int) $connection->query(
            "SELECT COUNT(DISTINCT p.id) FROM projects p INNER JOIN project_observations o ON o.project_id = p.id WHERE p.deleted_at IS NULL AND p.status != 'published' AND o.status = 'pending'"
        )->fetchColumn();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'in_flow' => (int) ($row['in_flow'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
            'active' => (int) ($row['in_flow'] ?? 0),
            'pending_observations' => $pendingObs,
            'items' => [],
        ];
    }

    private function adminStatusDistribution(PDO $connection, int $totalProjects): array
    {
        $statusLabels = [
            'development' => 'En desarrollo',
            'under_review' => 'En revisión',
            'approved' => 'Aprobados',
            'defense' => 'En tribunal',
            'published' => 'Publicados',
            'completed' => 'Finalizados',
        ];

        $statement = $connection->query("SELECT status, COUNT(*) count FROM projects WHERE deleted_at IS NULL GROUP BY status");
        $rows = $statement->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $totalDenominator = max(1, array_sum(array_map(intval(...), $rows)));

        $result = [];
        foreach ($statusLabels as $status => $label) {
            $count = (int) ($rows[$status] ?? 0);
            $percentage = round(($count / $totalDenominator) * 100, 1);
            $route = $status === 'published' ? route('admin-repository') : route('projects') . '&status=' . $status;

            $result[] = [
                'status' => $status,
                'label' => $label,
                'count' => $count,
                'percentage' => $percentage,
                'route' => $route,
            ];
        }

        return $result;
    }

    private function adminUpcomingDates(PDO $connection): array
    {
        $periodContext = $this->adminInstitutionalContext($connection);
        $dates = [];

        if (!empty($periodContext['next_institutional_date'])) {
            $item = $periodContext['next_institutional_date'];
            $dates[] = [
                'date' => date('d M', strtotime((string) $item['date'])),
                'label' => $item['title'],
                'kind' => 'Periodo académico',
                'days' => (int) $item['days_remaining'],
                'route' => $item['route'],
            ];
        }

        return $dates;
    }

    private function adminActivity(PDO $connection): array
    {
        return $this->adminRecentAdminActivity($connection);
    }

    private function adminWeeklyActivity(PDO $connection): int
    {
        return (int) $connection->query("SELECT COUNT(*) FROM admin_audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    }

    private function adminAlerts(PDO $connection, int $pendingObservations): array
    {
        return [];
    }

    // MÉTODOS PARA DASHBOARD GENERAL (DOCENTE / ESTUDIANTE)
    public function getSummary(): array
    {
        return [
            ['cardClass' => 'is-primary', 'title' => 'Entregables Activos', 'label' => 'Entregables Activos', 'value' => 5, 'subtitle' => '1 pendiente de revision', 'description' => '1 pendiente de revision', 'meta' => '1 pendiente de revision', 'icon' => 'file-lines', 'tone' => 'primary'],
            ['cardClass' => 'is-amber', 'title' => 'Observaciones', 'label' => 'Observaciones', 'value' => 2, 'subtitle' => 'Requieren atencion', 'description' => 'Requieren atencion', 'meta' => 'Requieren atencion', 'icon' => 'comment-dots', 'tone' => 'amber'],
            ['cardClass' => 'is-emerald', 'title' => 'Progreso General', 'label' => 'Progreso General', 'value' => '68%', 'subtitle' => 'Fase 2 de 4 completada', 'description' => 'Fase 2 de 4 completada', 'meta' => 'Fase 2 de 4 completada', 'icon' => 'chart-line', 'tone' => 'emerald'],
            ['cardClass' => 'is-indigo', 'title' => 'Proxima Entrega', 'label' => 'Proxima Entrega', 'value' => '04 Nov', 'subtitle' => 'Quedan 3 dias habiles', 'description' => 'Quedan 3 dias habiles', 'meta' => 'Quedan 3 dias habiles', 'icon' => 'calendar-day', 'tone' => 'indigo'],
        ];
    }

    public function getCurrentReport(): array
    {
        return [
            'id' => 1,
            'title' => 'Sistema de Gestion Academica para Titulacion Universitario',
            'description' => 'Sistema de Gestion Academica para Titulacion Universitario',
            'document' => 'informe_borrador_final_v2.pdf',
            'version' => 'v2.1',
            'lastDelivery' => '18 Ago 2026',
            'semester' => 'I PAO 2026',
            'career' => 'Ingenieria en Sistemas',
            'tutor' => 'Dr. Carlos Mendoza',
            'lastReview' => '16 Ago 2026',
            'pendingObservations' => '2 pendientes',
            'code' => 'TESIS-2026-004',
            'author' => 'Juan Perez & Maria Rodriguez',
            'advisor' => 'Dr. Carlos Mendoza',
            'reviewer' => 'Dra. Ana Gomez',
            'status' => 'under_review',
            'status_label' => 'En Revision de Formato',
            'statusClass' => 'is-amber',
            'progress' => 68,
            'current_phase' => 'Fase 2: Revision de Avance Metodologico',
            'last_update' => '2026-08-18 10:30',
        ];
    }

    public function getTeamMembers(): array
    {
        return [
            ['id' => 1, 'name' => 'Juan Perez', 'initial' => 'JP', 'role' => 'Estudiante Lead', 'avatar' => null, 'status' => 'active'],
            ['id' => 2, 'name' => 'Maria Rodriguez', 'initial' => 'MR', 'role' => 'Co-autor', 'avatar' => null, 'status' => 'active'],
            ['id' => 3, 'name' => 'Dr. Carlos Mendoza', 'initial' => 'CM', 'role' => 'Tutor Academico', 'avatar' => null, 'status' => 'active'],
        ];
    }

    public function getObservations(): array
    {
        return [
            ['id' => 1, 'title' => 'Ajustar metodología', 'author' => 'Dra. Ana Gomez', 'role' => 'Revisora', 'date' => '2026-08-16', 'text' => 'Ajustar la seccion 3.2 referente a la metodologia cualitativa.', 'status' => 'pending', 'statusClass' => 'is-amber', 'file' => 'capitulo_3_v2.pdf'],
            ['id' => 2, 'title' => 'Referencias APA 7', 'author' => 'Dr. Carlos Mendoza', 'role' => 'Tutor', 'date' => '2026-08-14', 'text' => 'Formato de referencias corregido segun norma APA 7.', 'status' => 'resolved', 'statusClass' => 'is-emerald', 'file' => 'referencias.pdf'],
        ];
    }

    public function getRecentActivity(): array
    {
        return [
            ['action' => 'Entrega subida', 'title' => 'Entrega subida', 'detail' => 'Capitulo 3 Metodologia v2.pdf', 'text' => 'Capitulo 3 Metodologia v2.pdf', 'user' => 'Juan Perez', 'date' => 'Hace 2 horas', 'time' => 'Hace 2 horas', 'type' => 'upload', 'icon' => 'fa-upload'],
            ['action' => 'Observacion agregada', 'title' => 'Observacion agregada', 'detail' => 'Revisar seccion 3.2', 'text' => 'Revisar seccion 3.2', 'user' => 'Dra. Ana Gomez', 'date' => 'Hace 1 dia', 'time' => 'Hace 1 dia', 'type' => 'comment', 'icon' => 'fa-comment'],
            ['action' => 'Estado actualizado', 'title' => 'Estado actualizado', 'detail' => 'Cambio a En Revision', 'text' => 'Cambio a En Revision', 'user' => 'Sistema', 'date' => 'Hace 2 dias', 'time' => 'Hace 2 dias', 'type' => 'status', 'icon' => 'fa-circle-check'],
        ];
    }

    public function getProcessDates(): array
    {
        return [
            ['date' => '04 Nov', 'value' => '04 Nov', 'event' => 'Cierre de Revision Metodologica', 'label' => 'Cierre de Revision Metodologica', 'status' => 'upcoming', 'days_left' => 3],
            ['date' => '18 Nov', 'value' => '18 Nov', 'event' => 'Entrega de Borrador Final', 'label' => 'Entrega de Borrador Final', 'status' => 'future', 'days_left' => 17],
            ['date' => '02 Dic', 'value' => '02 Dic', 'event' => 'Defensa Borrador Ante Tribunal', 'label' => 'Defensa Borrador Ante Tribunal', 'status' => 'future', 'days_left' => 31],
        ];
    }

    public function getNotifications(): array
    {
        return [
            ['id' => 1, 'title' => 'Nueva observacion recibida', 'message' => 'Dra. Ana Gomez agrego comentarios a tu ultima entrega.', 'text' => 'Dra. Ana Gomez agrego comentarios a tu ultima entrega.', 'date' => 'Hace 1 dia', 'time' => 'Hace 1 dia', 'read' => false],
            ['id' => 2, 'title' => 'Recordatorio de entrega', 'message' => 'La fecha limite para la Fase 2 es el 04 de Noviembre.', 'text' => 'La fecha limite para la Fase 2 es el 04 de Noviembre.', 'date' => 'Hace 3 dias', 'time' => 'Hace 3 dias', 'read' => true],
        ];
    }

    public function getReminders(): array
    {
        return [
            ['title' => 'Reunion con Tutor', 'text' => 'Reunion con Tutor', 'date' => 'Manana 10:00 AM', 'urgent' => true],
            ['title' => 'Subir correcciones APA', 'text' => 'Subir correcciones APA', 'date' => '02 Nov 11:59 PM', 'urgent' => false],
        ];
    }
}
