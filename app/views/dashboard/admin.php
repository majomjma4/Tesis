<?php
/**
 * Dashboard Administrador — Rediseño Definitivo Basado en el Contrato 1C (Fase 4D).
 *
 * Jerarquía Semántica:
 * A. Contexto Institucional (Período Académico + Próximo Hito)
 * B. Resumen Operativo (Cinta horizontal de 3 métricas)
 * C. Atención Administrativa (Incidencias operativas accionables)
 * D. Panorama Académico (Distribución compacta por etapas del flujo)
 * E. Trazabilidad Administrativa (Auditoría reciente de la plataforma)
 */

$institutionalContext = is_array($dashboard['institutional_context'] ?? null) ? $dashboard['institutional_context'] : [];
$academicPeriod = is_array($institutionalContext['academic_period'] ?? null) ? $institutionalContext['academic_period'] : null;
$nextDate = is_array($institutionalContext['next_institutional_date'] ?? null) ? $institutionalContext['next_institutional_date'] : null;

$summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
$totalProjects = $summary['total_projects'] ?? ['count' => 0, 'route' => route('projects')];
$activeProjects = $summary['active_projects'] ?? ['count' => 0, 'route' => route('projects')];
$approvedProjects = $summary['approved_projects'] ?? ['count' => 0, 'route' => route('projects')];
$publishedProjects = $summary['published_projects'] ?? ['count' => 0, 'route' => route('admin-repository')];
$activeUsers = $summary['active_users'] ?? ['active' => 0, 'total' => 0, 'route' => route('admin-users') . '&status=active'];

$platformStatus = is_array($dashboard['platform_status'] ?? null) ? $dashboard['platform_status'] : [];
$attention = is_array($dashboard['attention'] ?? null) ? $dashboard['attention'] : [];
$trash = is_array($dashboard['trash'] ?? null) ? $dashboard['trash'] : ['total' => 0, 'projects' => 0, 'users' => 0, 'support_materials' => 0, 'route' => route('admin-trash')];
$statusDistribution = is_array($dashboard['project_status_distribution'] ?? null) ? $dashboard['project_status_distribution'] : [];
$recentActivity = is_array($dashboard['recent_admin_activity'] ?? null) ? $dashboard['recent_admin_activity'] : [];
$recentActivityTotal = max(0, (int) ($dashboard['recent_admin_activity_total'] ?? 0));
$updatedAt = !empty($dashboard['updated_at']) ? (string) $dashboard['updated_at'] : null;
?>

<div class="admin-dashboard-v4">

    <!-- A. CONTEXTO INSTITUCIONAL (PERÍODO ACADÉMICO + PRÓXIMO HITO) -->
    <header class="admin-institutional-hero">
        <div class="hero-main">
            <div class="hero-identity">
                <span class="hero-badge"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Período Académico Activo</span>
                <h1 class="hero-title"><?= e((string) ($academicPeriod['name'] ?? 'Período Lectivo')) ?></h1>
                <p class="hero-subtitle">
                    <?php if ($academicPeriod !== null && !empty($academicPeriod['starts_on']) && !empty($academicPeriod['ends_on'])): ?>
                        Vigencia: <?= date('d M', strtotime((string)$academicPeriod['starts_on'])) ?> — <?= date('d M Y', strtotime((string)$academicPeriod['ends_on'])) ?>
                    <?php else: ?>
                        Vigencia institucional regular
                    <?php endif; ?>
                </p>
            </div>

            <div class="hero-side">
                <?php
                $daysRemaining = null;
                if (isset($academicPeriod['days_remaining'])) {
                    $daysRemaining = (int) $academicPeriod['days_remaining'];
                } elseif ($academicPeriod !== null && !empty($academicPeriod['ends_on'])) {
                    $daysRemaining = max(0, (int) ceil((strtotime((string)$academicPeriod['ends_on']) - time()) / 86400));
                }
                ?>

                <?php if ($daysRemaining !== null): ?>
                    <div class="hero-countdown">
                        <div class="countdown-num"><?= $daysRemaining ?></div>
                        <div class="countdown-label">días restantes</div>
                    </div>
                <?php endif; ?>

                <?php if ($nextDate !== null && !empty($nextDate['title'])):
                    $milestoneDays = $nextDate['days_left'] ?? $nextDate['days_remaining'] ?? $daysRemaining;
                ?>
                    <a class="hero-milestone" href="<?= e((string) ($nextDate['route'] ?? route('admin-academic'))) ?>">
                        <span class="milestone-tag">Próximo hito</span>
                        <strong class="milestone-name"><?= e((string) $nextDate['title']) ?></strong>
                        <span class="milestone-date">
                            <?php if ($milestoneDays !== null): ?>
                                En <?= (int)$milestoneDays ?> <?= (int)$milestoneDays === 1 ? 'día' : 'días' ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <?php else: ?>
                                Ver detalles <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($academicPeriod !== null && !empty($academicPeriod['starts_on']) && !empty($academicPeriod['ends_on'])):
            $startTs = strtotime((string)$academicPeriod['starts_on']);
            $endTs = strtotime((string)$academicPeriod['ends_on']);
            $nowTs = time();
            $totalDays = max(1, (int) round(($endTs - $startTs) / 86400));
            $elapsedDays = max(0, min($totalDays, (int) round(($nowTs - $startTs) / 86400)));
            $progressPct = (int) round(($elapsedDays / $totalDays) * 100);
        ?>
            <div class="hero-progress-wrapper" aria-label="Progreso del período lectivo">
                <div class="hero-progress-bar">
                    <div class="hero-progress-fill" style="width: <?= $progressPct ?>%;"></div>
                </div>
                <span class="hero-progress-meta">Avance lectivo: <?= $progressPct ?>%</span>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($dashboardError)): ?>
        <div class="v4-alert-banner" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>No fue posible actualizar algunos indicadores administrativos.</span>
        </div>
    <?php endif; ?>

    <!-- B. RESUMEN OPERATIVO (FILA 1: 3 CARDS | FILA 2: 2 CARDS CENTRADAS) -->
    <div class="v4-summary-strip" aria-label="Resumen operativo">
        <div class="summary-row row-top">
            <!-- 1. TOTAL DE PROYECTOS -->
            <a class="strip-card" href="<?= e((string) $totalProjects['route']) ?>">
                <div class="strip-card-icon is-total">
                    <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                </div>
                <div class="strip-card-body">
                    <strong class="strip-card-val"><?= (int) $totalProjects['count'] ?></strong>
                    <span class="strip-card-label">Proyectos totales</span>
                    <small class="strip-card-sub">Expedientes registrados</small>
                </div>
            </a>

            <!-- 2. PROYECTOS EN FLUJO -->
            <a class="strip-card" href="<?= e((string) $activeProjects['route']) ?>">
                <div class="strip-card-icon is-active">
                    <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                </div>
                <div class="strip-card-body">
                    <strong class="strip-card-val"><?= (int) $activeProjects['count'] ?></strong>
                    <span class="strip-card-label">Proyectos en flujo</span>
                    <small class="strip-card-sub">Actualmente en proceso</small>
                </div>
            </a>

            <!-- 3. PROYECTOS APROBADOS -->
            <a class="strip-card" href="<?= e((string) $approvedProjects['route']) ?>">
                <div class="strip-card-icon is-approved">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                </div>
                <div class="strip-card-body">
                    <strong class="strip-card-val"><?= (int) $approvedProjects['count'] ?></strong>
                    <span class="strip-card-label">Aprobados</span>
                    <small class="strip-card-sub">Con aprobación académica final</small>
                </div>
            </a>
        </div>

        <div class="summary-row row-bottom">
            <!-- 4. PROYECTOS PUBLICADOS -->
            <a class="strip-card" href="<?= e((string) $publishedProjects['route']) ?>">
                <div class="strip-card-icon is-published">
                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                </div>
                <div class="strip-card-body">
                    <strong class="strip-card-val"><?= (int) $publishedProjects['count'] ?></strong>
                    <span class="strip-card-label">Proyectos publicados</span>
                    <small class="strip-card-sub">Disponibles en repositorio</small>
                </div>
            </a>

            <!-- 5. USUARIOS ACTIVOS -->
            <a class="strip-card" href="<?= e((string) $activeUsers['route']) ?>">
                <div class="strip-card-icon is-users">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                </div>
                <div class="strip-card-body">
                    <strong class="strip-card-val"><?= (int) $activeUsers['active'] ?> <small class="val-total">/ <?= (int) $activeUsers['total'] ?></small></strong>
                    <span class="strip-card-label">Usuarios activos</span>
                    <small class="strip-card-sub">Cuentas operativas</small>
                </div>
            </a>
        </div>
    </div>

    <!-- C. ESTADO DE LA PLATAFORMA (CONTROL OPERATIVO Y RETENCIÓN) -->
    <?php
    $access = is_array($platformStatus['access'] ?? null) ? $platformStatus['access'] : [
        'active' => (int)($activeUsers['active'] ?? 0),
        'total' => (int)($activeUsers['total'] ?? 0),
        'percentage' => 100,
        'expired_credentials' => 0,
        'blocked_accounts' => 0,
        'route' => route('admin-users'),
    ];
    $retention = is_array($platformStatus['retention'] ?? null) ? $platformStatus['retention'] : [
        'total' => (int)($trash['total'] ?? 0),
        'projects' => (int)($trash['projects'] ?? 0),
        'users' => (int)($trash['users'] ?? 0),
        'support_materials' => (int)($trash['support_materials'] ?? 0),
        'retention_days' => ['projects' => 60, 'users' => 60, 'support_materials' => 60],
        'automatic_purge' => true,
        'route' => route('admin-trash'),
    ];
    ?>
    <section class="v4-section platform-status-v4-section" aria-label="Estado de la plataforma">
        <div class="section-v4-header">
            <div>
                <span class="v4-tag">Control Operativo</span>
                <h2 class="v4-title">Estado de la plataforma</h2>
            </div>
        </div>

        <div class="platform-v4-grid">
            <!-- COMPONENTE 1: ACCESOS Y SEGURIDAD -->
            <div class="platform-v4-card">
                <div class="platform-card-header">
                    <div class="platform-card-icon is-access">
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                    </div>
                    <div class="platform-card-title">
                        <h3>Accesos y seguridad</h3>
                        <span class="platform-card-sub">Control de credenciales y accesos</span>
                    </div>
                </div>

                <div class="platform-card-body">
                    <?php if ((int) $access['expired_credentials'] > 0 || (int) $access['blocked_accounts'] > 0): ?>
                        <ul class="platform-status-list">
                            <?php if ((int) $access['expired_credentials'] > 0): ?>
                                <li class="status-item is-warning">
                                    <span class="item-badge"><?= (int) $access['expired_credentials'] ?></span>
                                    <span><?= (int) $access['expired_credentials'] === 1 ? 'credencial temporal vencida' : 'credenciales temporales vencidas' ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if ((int) $access['blocked_accounts'] > 0): ?>
                                <li class="status-item is-critical">
                                    <span class="item-badge"><?= (int) $access['blocked_accounts'] ?></span>
                                    <span><?= (int) $access['blocked_accounts'] === 1 ? 'cuenta de usuario bloqueada' : 'cuentas de usuario bloqueadas' ?></span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php else: ?>
                        <div class="platform-status-healthy">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <div>
                                <strong>Sin incidencias de acceso</strong>
                                <span class="healthy-sub">No hay credenciales vencidas ni cuentas bloqueadas.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="platform-card-footer">
                    <a href="<?= e((string) $access['route']) ?>" class="v4-row-cta">Gestionar accesos <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>

            <!-- COMPONENTE 2: RETENCIÓN Y PAPELERA -->
            <div class="platform-v4-card">
                <div class="platform-card-header">
                    <div class="platform-card-icon is-retention">
                        <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                    </div>
                    <div class="platform-card-title">
                        <h3>Retención y papelera</h3>
                        <span class="platform-card-sub">
                            <?php if ((int) $retention['total'] > 0): ?>
                                <?= (int) $retention['total'] ?> <?= (int) $retention['total'] === 1 ? 'elemento en retención activa' : 'elementos en retención activa' ?>
                            <?php else: ?>
                                Sin elementos en retención activa
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="platform-card-body">
                    <?php
                    $entityParts = [];
                    if ((int) ($retention['projects'] ?? 0) > 0) {
                        $entityParts[] = (int) $retention['projects'] . ' ' . ((int) $retention['projects'] === 1 ? 'proyecto' : 'proyectos');
                    }
                    if ((int) ($retention['users'] ?? 0) > 0) {
                        $entityParts[] = (int) $retention['users'] . ' ' . ((int) $retention['users'] === 1 ? 'usuario' : 'usuarios');
                    }
                    if ((int) ($retention['support_materials'] ?? 0) > 0) {
                        $entityParts[] = (int) $retention['support_materials'] . ' ' . ((int) $retention['support_materials'] === 1 ? 'material' : 'materiales');
                    }

                    $daysProjects = (int) ($retention['retention_days']['projects'] ?? 60);
                    $daysUsers = (int) ($retention['retention_days']['users'] ?? 60);
                    $daysMaterials = (int) ($retention['retention_days']['support_materials'] ?? 60);

                    if ($daysProjects === $daysUsers && $daysUsers === $daysMaterials) {
                        $policyText = $daysProjects . ' días de salvaguarda';
                    } else {
                        $policyText = "Proyectos: {$daysProjects}d · Usuarios: {$daysUsers}d · Materiales: {$daysMaterials}d";
                    }
                    ?>

                    <?php if ((int) $retention['total'] > 0 && !empty($entityParts)): ?>
                        <div class="platform-retention-breakdown">
                            <span class="breakdown-items"><?= e(implode(' · ', $entityParts)) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="platform-status-healthy">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                            <span>Papelera vacía en este momento.</span>
                        </div>
                    <?php endif; ?>

                    <div class="platform-policy-meta">
                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                        <span>Política: <?= e($policyText) ?> · Purga automática al vencer</span>
                    </div>
                </div>

                <div class="platform-card-footer">
                    <a href="<?= e((string) $retention['route']) ?>" class="v4-row-cta">Abrir papelera <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- D. PANORAMA ACADÉMICO (COMPOSICIÓN HORIZONTAL COMPACTA POR ETAPAS) -->
    <section class="v4-section status-v4-section">
        <div class="section-v4-header">
            <div>
                <span class="v4-tag">Flujo de Expedientes</span>
                <h2 class="v4-title">Panorama académico</h2>
            </div>
            <a href="<?= e(route('projects')) ?>" class="v4-header-link">Ver expedientes completos</a>
        </div>

        <?php
        // Total vigente: denominador dinámico para el texto "X de N · Y% del total"
        $panoramaTotal = (int) ($totalProjects['count'] ?? 0);
        // Dividir las 5 categorías en fila superior (3) e inferior (2)
        $panoramaTop    = array_slice($statusDistribution, 0, 3);
        $panoramaBottom = array_slice($statusDistribution, 3);
        ?>
        <div class="status-v4-compact-grid">
            <div class="status-row row-top">
                <?php foreach ($panoramaTop as $statusItem):
                    $itemRoute  = !empty($statusItem['route']) ? (string) $statusItem['route'] : null;
                    $percentage = (float) ($statusItem['percentage'] ?? 0.0);
                    $count      = (int) ($statusItem['count'] ?? 0);
                    $tagElement = $itemRoute !== null ? 'a' : 'div';
                ?>
                    <<?= $tagElement ?> class="status-v4-node" <?= $itemRoute !== null ? 'href="' . e($itemRoute) . '"' : '' ?>>
                        <div class="node-head">
                            <span class="node-label"><?= e((string) ($statusItem['label'] ?? '')) ?></span>
                            <strong class="node-count"><?= $count ?></strong>
                        </div>
                        <div class="node-track">
                            <div class="node-fill" style="width: <?= min(100, max(0, $percentage)) ?>%;"></div>
                        </div>
                        <span class="node-pct"><?= $count ?> de <?= $panoramaTotal ?> proyectos &middot; <?= $percentage ?>%</span>
                    </<?= $tagElement ?>>
                <?php endforeach; ?>
            </div>

            <?php if ($panoramaBottom !== []): ?>
            <div class="status-row row-bottom">
                <?php foreach ($panoramaBottom as $statusItem):
                    $itemRoute  = !empty($statusItem['route']) ? (string) $statusItem['route'] : null;
                    $percentage = (float) ($statusItem['percentage'] ?? 0.0);
                    $count      = (int) ($statusItem['count'] ?? 0);
                    $tagElement = $itemRoute !== null ? 'a' : 'div';
                ?>
                    <<?= $tagElement ?> class="status-v4-node" <?= $itemRoute !== null ? 'href="' . e($itemRoute) . '"' : '' ?>>
                        <div class="node-head">
                            <span class="node-label"><?= e((string) ($statusItem['label'] ?? '')) ?></span>
                            <strong class="node-count"><?= $count ?></strong>
                        </div>
                        <div class="node-track">
                            <div class="node-fill" style="width: <?= min(100, max(0, $percentage)) ?>%;"></div>
                        </div>
                        <span class="node-pct"><?= $count ?> de <?= $panoramaTotal ?> proyectos &middot; <?= $percentage ?>%</span>
                    </<?= $tagElement ?>>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- E. TRAZABILIDAD ADMINISTRATIVA (TIMELINE SECUENCIAL) -->
    <section class="v4-section activity-v4-section">
        <?php
        $recentActivityCount = count($recentActivity);
        $recentActivityTotal = (int) ($dashboard['recent_admin_activity_total'] ?? 0);
        $activityCountLabel = $recentActivityTotal === 0
            ? '0 eventos'
            : $recentActivityCount . ' de ' . $recentActivityTotal . ' ' . ($recentActivityTotal === 1 ? 'evento' : 'eventos');
        ?>
        <div class="section-v4-header">
            <div>
                <span class="v4-tag">Auditoría Institucional</span>
                <h2 class="v4-title">Trazabilidad administrativa</h2>
            </div>
            <div class="v4-header-actions">
                <span class="v4-header-count"><?= e($activityCountLabel) ?></span>
                <a href="<?= e(route('admin-reports')) ?>" class="v4-header-link">Abrir registro de auditoría <span aria-hidden="true">-&gt;</span></a>
            </div>
        </div>

        <?php if ($recentActivity === []): ?>
            <div class="v4-empty-inline">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <span>0 registros</span>
            </div>
        <?php else: ?>
            <ol class="activity-v4-timeline">
                <?php foreach ($recentActivity as $actItem):
                    $actRoute = !empty($actItem['url']) ? (string) $actItem['url'] : (!empty($actItem['route']) ? (string) $actItem['route'] : null);
                    $tagElement = $actRoute !== null ? 'a' : 'div';
                ?>
                    <<?= $tagElement ?> class="timeline-v4-item" <?= $actRoute !== null ? 'href="' . e($actRoute) . '"' : '' ?>>
                        <span class="v4-bullet"></span>
                        <div class="timeline-v4-row">
                            <div class="v4-timeline-body">
                                <span class="v4-act-title"><?= e((string) ($actItem['action'] ?? '')) ?></span>
                                <p class="v4-act-resource"><?= e((string) ($actItem['resource'] ?? ($actItem['detail'] ?? ''))) ?> · <?= e((string) ($actItem['actor'] ?? ($actItem['user'] ?? 'Administración'))) ?></p>
                            </div>
                            <time class="v4-act-ts"><?= e((string) ($actItem['date'] ?? ($actItem['occurred_at'] ?? ''))) ?></time>
                        </div>
                    </<?= $tagElement ?>>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

</div>
