<?php

declare(strict_types=1);

final class DashboardModel
{
    public function getTeacherDashboard(int $teacherId): array
    {
        if ($teacherId < 1) return $this->emptyTeacherDashboard($teacherId);

        $db = Database::connection();
        try {
            $teacher = $this->teacherIdentity($db, $teacherId);
        } catch (Throwable $error) {
            error_log('Teacher dashboard identity: ' . $error->getMessage());
            $teacher = ['id'=>$teacherId,'name'=>'Usuario','email'=>'','roles'=>['teacher'],'can_tutor'=>false,'can_manage_thesis'=>false];
        }
        try {
            $period = $this->teacherAcademicPeriod($db);
        } catch (Throwable $error) {
            error_log('Teacher dashboard period: ' . $error->getMessage());
            $period = null;
        }
        $periodId = $period === null ? null : (int) $period['id'];
        try {
            $assigned = $period === null
                ? ['projects' => []]
                : (new TeacherAssignedProjectService())->forTeacher($teacherId, $periodId);
        } catch (Throwable $error) {
            error_log('Teacher dashboard assignments: ' . $error->getMessage());
            $assigned = ['projects' => []];
        }
        $projects = $assigned['projects'];
        $projectIds = array_values(array_unique(array_map(static fn(array $item): int => (int) $item['id'], $projects)));
        try {
            $this->addTeacherProjectContext($db, $projects, $projectIds);
            $latestDeliveries = $this->teacherLatestDeliveries($db, $projectIds);
            $adjustments = $this->teacherPendingAdjustments($db, $projectIds);
        } catch (Throwable $error) {
            error_log('Teacher dashboard project context: ' . $error->getMessage());
            $latestDeliveries = [];
            $adjustments = [];
        }
        // La fuente semántica central ya determinó las unidades pendientes.
        $deliveries = [];
        foreach ($projects as $project) {
            $units = (int) (($project['teacher_situation_data']['review_units'] ?? 0));
            if ($units > 0) $deliveries[(int) $project['id']] = $units;
        }
        $this->sortTeacherProjectsForDashboard($projects, $deliveries);
        try {
            $upcoming = $this->teacherUpcomingEvents($db, $teacherId, $projectIds);
        } catch (Throwable $error) {
            error_log('Teacher dashboard calendar: ' . $error->getMessage());
            $upcoming = [];
        }
        try {
            $capabilities = $this->teacherCapabilities($db, $teacherId, $projects, $teacher);
        } catch (Throwable $error) {
            error_log('Teacher dashboard capabilities: ' . $error->getMessage());
            $capabilities = ['is_tutor'=>false,'is_cotutor'=>false,'is_reviewer'=>false,'is_tribunal_member'=>false,'can_manage_thesis'=>false,'manage_thesis_process'=>false];
        }
        $followUp = $this->teacherFollowUp($projects, $deliveries);
        $projectItems = array_map(static function (array $project) use ($deliveries, $latestDeliveries, $adjustments): array {
            $id = (int) $project['id'];
            $situation = (array) ($project['teacher_situation_data'] ?? []);
            // El servicio central siempre entrega esta estructura; si no lo hace,
            // se conserva el contrato neutro sin volver a inferir el workflow.
            if ($situation === []) $situation = ['key'=>'waiting_process','label'=>'En seguimiento','description'=>'No fue posible determinar una acción docente; se requiere revisión del expediente.','requires_attention'=>false,'actor'=>'unknown','review_units'=>0];
            $roleItems = (array) ($project['relationships'] ?? []);
            return [
                'id' => $id,
                'code' => (string) ($project['code'] ?? ''),
                'title' => (string) ($project['title'] ?? ''),
                'type' => (string) ($project['type'] ?? ''),
                'type_key' => (string) ($project['type_code'] ?? '') ?: null,
                'students' => array_values((array) ($project['student_rows'] ?? [])),
                'status' => (string) ($project['status_key'] ?? ''),
                'status_label' => (string) ($project['status'] ?? ''),
                'roles' => array_values(array_map(static fn(array $role): string => (string) ($role['label'] ?? $role['code'] ?? ''), $roleItems)),
                'teacher_situation' => ['key'=>(string)($situation['key']??'waiting_process'),'label'=>(string)($situation['label']??'En seguimiento'),'description'=>(string)($situation['description']??''),'requires_attention'=>(bool)($situation['requires_attention']??false),'actor'=>(string)($situation['actor']??'unknown')],
                'teacher_situation_label' => (string)($situation['label']??'En seguimiento'),
                'teacher_situation_requires_attention' => (bool)($situation['requires_attention']??false),
                'latest_delivery' => $latestDeliveries[$id] ?? null,
                'reviews_pending' => (int) ($situation['review_units'] ?? 0),
                'validation_pending' => 0,
                'adjustments_pending' => (int) ($adjustments[$id] ?? 0),
                'route' => route('project-detail') . '&id=' . $id,
                'action' => ['label'=>(bool)($situation['requires_attention']??false)?'Revisar proyecto':'Ver proyecto','route'=>route('project-detail').'&id='.$id],
            ];
        }, $projects);
        $allCount = count($projects);
        try {
            $notificationModel = new NotificationModel();
            $notifications = $notificationModel->getByUser($teacherId, [], 'teacher', 3);
            $unread = $notificationModel->getCounters($teacherId, 'teacher')['unread'];
        } catch (Throwable $error) {
            error_log('Teacher dashboard notifications: ' . $error->getMessage());
            $notifications = [];
            $unread = 0;
        }
        $notificationItems = array_map(fn(array $notification): array => $this->teacherNotificationItem($notification), $notifications);
        try {
            $repository = Database::isEnabled() ? (new RepositoryModel())->getPublishedProjects() : [];
        } catch (Throwable $error) {
            error_log('Teacher dashboard repository: ' . $error->getMessage());
            $repository = [];
        }
        $repositoryItems = array_map(static fn(array $item): array => ['id'=>(int)$item['id'],'code'=>(string)($item['code']??''),'title'=>(string)$item['title'],'type'=>(string)($item['type']??$item['category']??''),'career'=>$item['career']??null,'authors'=>is_array($item['authors']??null)?$item['authors']:[$item['authors']??''],'period'=>$item['pao_label']??null,'published_at'=>$item['published_at']??null,'route'=>route('repository-detail').'&id='.(int)$item['id']], array_slice($repository,0,6));

        return [
            'context' => [
                'teacher' => $teacher,
                'academic_period' => $period,
                'capabilities' => $capabilities,
                'thesis_management' => [
                    'enabled' => $capabilities['manage_thesis_process'],
                    'route' => route('thesis-management'),
                ],
            ],
            'assigned_projects' => ['count'=>$allCount,'items'=>$projectItems,'has_more'=>false,'route'=>route('assigned-projects')],
            'follow_up' => $followUp,
            'upcoming' => ['items' => $upcoming, 'route' => route('calendar')],
            'notifications' => ['items'=>$notificationItems,'unread'=>(int)$unread,'route'=>route('notifications')],
            'repository' => ['items'=>$repositoryItems,'has_more'=>count($repository)>6,'route'=>route('repository'),'direct_add'=>['supported'=>false,'route'=>null]],
        ];
    }

    public function emptyTeacherDashboard(int $teacherId = 0): array
    {
        return [
            'context' => [
                'teacher' => ['id' => $teacherId, 'name' => 'Usuario', 'roles' => ['teacher']],
                'academic_period' => null,
                'capabilities' => ['is_tutor' => false, 'is_cotutor' => false, 'is_tribunal_member' => false, 'can_manage_thesis' => false, 'is_reviewer' => false, 'manage_thesis_process' => false],
                'thesis_management' => ['enabled' => false, 'route' => route('thesis-management')],
            ],
            'assigned_projects' => ['count'=>0,'items'=>[],'has_more'=>false,'route'=>route('assigned-projects')],
            'follow_up' => ['review_required'=>['count'=>0,'projects_count'=>0,'route'=>route('assigned-projects')],'waiting_student'=>['count'=>0,'projects_count'=>0,'route'=>route('assigned-projects')],'tribunal_assignment'=>['visible'=>false,'count'=>0,'route'=>null]],
            'upcoming' => ['items' => [], 'route' => route('calendar')],
            'notifications' => ['items'=>[],'unread'=>0,'route'=>route('notifications')],
            'repository' => ['items'=>[],'has_more'=>false,'route'=>route('repository'),'direct_add'=>['supported'=>false,'route'=>null]],
        ];
    }

    private function teacherIdentity(PDO $db, int $teacherId): array
    {
        $query = $db->prepare("SELECT u.id,u.full_name,u.email,tp.can_tutor,tp.can_manage_thesis,GROUP_CONCAT(DISTINCT r.code ORDER BY r.code) roles
            FROM users u JOIN teacher_profiles tp ON tp.user_id=u.id
            LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id
            WHERE u.id=:id AND u.deleted_at IS NULL AND u.purged_at IS NULL GROUP BY u.id,u.full_name,u.email,tp.can_tutor,tp.can_manage_thesis");
        $query->execute(['id' => $teacherId]);
        $row = $query->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'id' => (int) ($row['id'] ?? $teacherId),
            'name' => (string) ($row['full_name'] ?? 'Usuario'),
            'email' => (string) ($row['email'] ?? ''),
            'roles' => $row['roles'] ? explode(',', (string) $row['roles']) : ['teacher'],
            'can_tutor' => (bool) ($row['can_tutor'] ?? false),
            'can_manage_thesis' => (bool) ($row['can_manage_thesis'] ?? false),
        ];
    }

    private function teacherAcademicPeriod(PDO $db): ?array
    {
        $row = $db->query("SELECT id,code,name,status,starts_on,ends_on FROM academic_periods WHERE status='active' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $ends = strtotime((string) $row['ends_on']);
        return [
            'id' => (int) $row['id'], 'code' => (string) $row['code'], 'name' => (string) $row['name'],
            'status' => (string) $row['status'], 'starts_on' => (string) $row['starts_on'], 'ends_on' => (string) $row['ends_on'],
            'days_remaining' => max(0, (int) ceil(($ends - time()) / 86400)),
        ];
    }

    private function teacherCapabilities(PDO $db, int $teacherId, array $projects, array $teacher): array
    {
        $tribunal = false; $cotutor = false; $tutor = false; $reviewer = false;
        foreach ($projects as $project) foreach ((array) ($project['relationships'] ?? []) as $relation) {
            $code = strtolower((string) ($relation['code'] ?? ''));
            $tribunal = $tribunal || $code === 'tribunal'; $cotutor = $cotutor || $code === 'cotutor'; $tutor = $tutor || $code === 'tutor';
            $reviewer = $reviewer || in_array($code, ['tutor', 'cotutor'], true);
        }
        return ['is_tutor'=>$tutor,'is_cotutor'=>$cotutor,'is_reviewer'=>$reviewer,'is_tribunal_member'=>$tribunal,'can_manage_thesis'=>(bool)$teacher['can_manage_thesis'],'manage_thesis_process'=>(bool)$teacher['can_manage_thesis']];
    }

    private function addTeacherProjectContext(PDO $db, array &$projects, array $projectIds): void
    {
        if (!$projectIds) return;
        $marks = implode(',', array_fill(0, count($projectIds), '?'));
        $query = $db->prepare("SELECT project_id,COUNT(*) observations_total,SUM(status='pending') observations_pending FROM project_observations WHERE project_id IN ($marks) GROUP BY project_id");
        $query->execute($projectIds); $context = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $context[(int) $row['project_id']] = $row;
        foreach ($projects as &$project) {
            $row = $context[(int) $project['id']] ?? [];
            $project['observations_total'] = (int) ($row['observations_total'] ?? 0);
            $project['observations_pending'] = (int) ($row['observations_pending'] ?? 0);
        }
        unset($project);
    }

    private function teacherPendingAdjustments(PDO $db, array $projectIds): array
    {
        if (!$projectIds) return [];
        $marks = implode(',', array_fill(0, count($projectIds), '?'));
        $query = $db->prepare("SELECT project_id,COUNT(*) total FROM project_adjustment_requests WHERE project_id IN ($marks) AND status='pending' GROUP BY project_id");
        $query->execute($projectIds); $result = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $result[(int) $row['project_id']] = (int) $row['total'];
        return $result;
    }

    private function teacherLatestDeliveries(PDO $db, array $projectIds): array
    {
        if (!$projectIds) return [];
        $outerMarks = implode(',', array_fill(0, count($projectIds), '?'));
        $innerMarks = implode(',', array_fill(0, count($projectIds), '?'));
        $query = $db->prepare("SELECT d.id,d.project_id,d.status,d.submitted_at
            FROM project_deliveries d
            WHERE d.project_id IN ($outerMarks)
              AND NOT EXISTS (
                SELECT 1 FROM project_deliveries newer
                WHERE newer.project_id=d.project_id
                  AND newer.project_id IN ($innerMarks)
                  AND (COALESCE(newer.submitted_at,'1000-01-01 00:00:00') > COALESCE(d.submitted_at,'1000-01-01 00:00:00') OR (COALESCE(newer.submitted_at,'1000-01-01 00:00:00') = COALESCE(d.submitted_at,'1000-01-01 00:00:00') AND newer.id > d.id))
              )");
        $query->execute(array_merge($projectIds, $projectIds));
        $result = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string) $row['status'];
            $result[(int) $row['project_id']] = [
                'id' => (int) $row['id'],
                'status' => $status,
                'status_label' => function_exists('project_delivery_status_label') ? project_delivery_status_label($status) : $status,
                'submitted_at' => (string) ($row['submitted_at'] ?? ''),
                'route' => route('project-detail') . '&id=' . (int) $row['project_id'],
            ];
        }
        return $result;
    }

    private function teacherFollowUp(array $projects, array $deliveries): array
    {
        $reviewCount = 0; $reviewProjects = 0; $waitingProjects = 0;
        foreach ($projects as $project) {
            $id = (int) ($project['id'] ?? 0);
            $key = (string) (($project['teacher_situation_data']['key'] ?? 'waiting_process'));
            if ($key === 'waiting_student') { $waitingProjects++; continue; }
            $pending = (int) ($project['teacher_situation_data']['review_units'] ?? $deliveries[$id] ?? 0);
            if ($key === 'review_required' && $pending > 0) { $reviewCount += $pending; $reviewProjects++; }
        }
        return [
            'review_required' => ['count'=>$reviewCount,'projects_count'=>$reviewProjects,'route'=>route('assigned-projects')],
            'waiting_student' => ['count'=>$waitingProjects,'projects_count'=>$waitingProjects,'route'=>route('assigned-projects')],
            'tribunal_assignment' => ['visible'=>false,'count'=>0,'route'=>null],
        ];
    }

    private function sortTeacherProjectsForDashboard(array &$projects, array $deliveries): void
    {
        usort($projects, function (array $left, array $right) use ($deliveries): int {
            $leftId = (int) ($left['id'] ?? 0);
            $rightId = (int) ($right['id'] ?? 0);
            $leftAttention = (($left['teacher_situation_data']['key'] ?? '') === 'review_required') && (int)($left['teacher_situation_data']['review_units'] ?? $deliveries[$leftId] ?? 0) > 0;
            $rightAttention = (($right['teacher_situation_data']['key'] ?? '') === 'review_required') && (int)($right['teacher_situation_data']['review_units'] ?? $deliveries[$rightId] ?? 0) > 0;

            if ($leftAttention !== $rightAttention) {
                return $leftAttention ? -1 : 1;
            }

            $leftUpdated = (string) ($left['updated_at'] ?? '');
            $rightUpdated = (string) ($right['updated_at'] ?? '');
            if ($leftUpdated !== $rightUpdated) {
                return $leftUpdated < $rightUpdated ? 1 : -1;
            }

            return $rightId <=> $leftId;
        });
    }

    private function teacherNotificationItem(array $notification): array
    {
        $title = trim((string) ($notification['title'] ?? 'Notificación'));
        $context = trim((string) ($notification['project_name'] ?? ''));
        if (preg_match('/^(?:Hoy|Recordatorio):\s*(.+)$/u', $title, $matches)) {
            $title = 'Recordatorio';
            $context = preg_replace('/\s+en\s+\d+\s+d[ií]as?$/u', '', trim((string) $matches[1])) ?: trim((string) $matches[1]);
        }
        if ($context === '' || strtolower($context) === 'notificacion general' || strtolower($context) === 'notificación general') {
            $context = trim((string) ($notification['message'] ?? ''));
        }
        return [
            'id'=>(int) ($notification['id'] ?? 0),
            'title'=>$title,
            'context'=>$context,
            'is_read'=>(bool) ($notification['is_read'] ?? false),
            'created_at'=>(string) ($notification['created_at'] ?? ''),
            'action_url'=>$this->teacherSafeNotificationUrl($notification['action_url'] ?? null),
            'action_label'=>$notification['action_label'] ?? null,
        ];
    }

    private function teacherSafeNotificationUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '' || str_contains($url, "\0") || str_contains($url, '..')) return null;
        $parts = parse_url($url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || str_starts_with($url, '//')) return null;
        $path = '/' . ltrim((string) ($parts['path'] ?? ''), '/');
        if (!preg_match('#(?:^|/)index\\.php$#', $path)) return null;
        parse_str((string) ($parts['query'] ?? ''), $query);
        $page = strtolower(trim((string) ($query['page'] ?? '')));
        if (!in_array($page, ['dashboard','assigned-projects','projects','project-detail','calendar','notifications','repository','repository-detail','thesis-management'], true)) return null;
        $requiredIds = [
            'project-detail' => ['id'],
            'repository-detail' => ['id'],
        ];
        foreach ($requiredIds[$page] ?? [] as $key) {
            if (!$this->teacherPositiveUrlId($query[$key] ?? null)) return null;
        }
        foreach (['id', 'project_id', 'delivery_id', 'event_id'] as $key) {
            if (array_key_exists($key, $query) && !$this->teacherPositiveUrlId($query[$key])) return null;
        }
        $relative = 'index.php'
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
        return base_url($relative);
    }

    private function teacherPositiveUrlId(mixed $value): bool
    {
        if (!is_string($value) && !is_int($value)) return false;
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    private function teacherUpcomingEvents(PDO $db, int $teacherId, array $projectIds): array
    {
        return (new TeacherCalendarVisibilityService())->upcoming($db, $teacherId, $projectIds);
    }
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
            'recent_admin_activity_total' => $this->adminRecentAdminActivityTotal($connection),
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
            'recent_admin_activity_total' => 0,
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
            $period = (new AcademicPeriodModel())->active();
            if (!$period) {
                return [];
            }
            $events = (new AdminReportModel())->auditEvents((string) $period['starts_on'], (string) $period['ends_on']);
            $rows = array_slice($events, 0, 6);
            $activity = [];
            foreach ($rows as $r) {
                $actionLabel = (string) ($r['action_label'] ?? '');
                $resource = !empty($r['element_label']) ? (string) $r['element_label'] : (string) ($r['entity_label'] ?? 'Sistema');
                $actor = !empty($r['actor']) ? (string) $r['actor'] : 'Administración';
                $activity[] = [
                    'action' => $actionLabel,
                    'label' => $actionLabel,
                    'actor' => $actor,
                    'resource' => $resource,
                    'occurred_at' => (string) ($r['created_at_local'] ?? $r['created_at'] ?? ''),
                    'date' => (string) ($r['created_at_local'] ?? $r['created_at'] ?? ''),
                    'route' => route('admin-reports'),
                ];
            }
            return $activity;
        } catch (Throwable $e) {
            error_log('adminRecentAdminActivity error: ' . $e->getMessage());
            return [];
        }
    }

    private function adminRecentAdminActivityTotal(PDO $connection): int
    {
        try {
            $period = (new AcademicPeriodModel())->active();
            if (!$period) {
                return 0;
            }
            return count((new AdminReportModel())->auditEvents((string) $period['starts_on'], (string) $period['ends_on']));
        } catch (Throwable $e) {
            error_log('adminRecentAdminActivityTotal error: ' . $e->getMessage());
            return 0;
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
        /*
         * PANORAMA ACADÉMICO — 5 categorías visuales consolidadas.
         *
         * Categorías y reglas de agrupación:
         *   En desarrollo → status = 'development'
         *   En revisión   → status = 'under_review'
         *                   + tesis (pt.code='thesis') con status = 'approved'
         *                     NOTA: Esta agrupación es EXCLUSIVAMENTE visual/panorámica.
         *                     La tesis sigue en flujo académico; su aprobación final
         *                     ocurre sólo en tribunal_approved. No cambia el status real en BD.
         *   Aprobados     → non-thesis con status = 'approved'
         *                   + tesis con status = 'tribunal_approved'
         *                   + status = 'completed' (legacy, compatibilidad histórica)
         *   En tribunal   → tesis con status = 'defense'
         *   Publicados    → status = 'published'
         *
         * "Finalizados" eliminado del panorama: `completed` se consolida en Aprobados.
         * El denominador es el total real de proyectos vigentes (deleted_at IS NULL).
         */
        $totalDenominator = max(1, $totalProjects);

        $statement = $connection->query(
            "SELECT
                SUM(CASE
                    WHEN p.status = 'development' THEN 1
                    ELSE 0
                END) AS development,
                SUM(CASE
                    WHEN p.status = 'under_review' THEN 1
                    WHEN p.status = 'approved' AND pt.code = 'thesis' THEN 1
                    ELSE 0
                END) AS under_review,
                SUM(CASE
                    WHEN p.status = 'completed' THEN 1
                    WHEN p.status = 'tribunal_approved' AND pt.code = 'thesis' THEN 1
                    WHEN p.status = 'approved' AND pt.code != 'thesis' THEN 1
                    ELSE 0
                END) AS approved,
                SUM(CASE
                    WHEN p.status = 'defense' AND pt.code = 'thesis' THEN 1
                    ELSE 0
                END) AS defense,
                SUM(CASE
                    WHEN p.status = 'published' THEN 1
                    ELSE 0
                END) AS published
             FROM projects p
             INNER JOIN project_types pt ON pt.id = p.project_type_id
             WHERE p.deleted_at IS NULL"
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        $categories = [
            'development' => ['label' => 'En desarrollo', 'route' => route('projects') . '&status=development'],
            'under_review' => ['label' => 'En revisión',   'route' => route('projects') . '&status=under_review'],
            'approved'     => ['label' => 'Aprobados',     'route' => route('projects') . '&status=approved'],
            'defense'      => ['label' => 'En tribunal',   'route' => route('projects') . '&status=defense'],
            'published'    => ['label' => 'Publicados',    'route' => route('admin-repository')],
        ];

        $result = [];
        foreach ($categories as $key => $meta) {
            $count = (int) ($row[$key] ?? 0);
            $percentage = round(($count / $totalDenominator) * 100, 1);
            $result[] = [
                'status'     => $key,
                'label'      => $meta['label'],
                'count'      => $count,
                'percentage' => $percentage,
                'route'      => $meta['route'],
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
