<?php
$context = is_array($teacherDashboard['context'] ?? null) ? $teacherDashboard['context'] : [];
$period = is_array($context['academic_period'] ?? null) ? $context['academic_period'] : null;
$capabilities = is_array($context['capabilities'] ?? null) ? $context['capabilities'] : [];
$thesisManagement = is_array($context['thesis_management'] ?? null) ? $context['thesis_management'] : [];
$summary = is_array($teacherDashboard['summary'] ?? null) ? $teacherDashboard['summary'] : [];
$projectData = is_array($teacherDashboard['projects'] ?? null) ? $teacherDashboard['projects'] : [];
$projects = is_array($projectData['items'] ?? null) ? $projectData['items'] : [];
$upcoming = is_array($teacherDashboard['upcoming']['items'] ?? null) ? $teacherDashboard['upcoming']['items'] : [];
$visibleProjects = array_slice($projects, 0, 5);
$hasMoreProjects = count($projects) > count($visibleProjects);
?>

<main class="teacher-dashboard" aria-labelledby="teacherDashboardTitle">
    <?php if (!empty($teacherDashboardError)): ?>
        <p class="teacher-dashboard-error" role="alert"><?= e($teacherDashboardError) ?></p>
    <?php endif; ?>

    <header class="teacher-dashboard-header">
        <div>
            <span class="teacher-dashboard-eyebrow">Espacio de trabajo docente</span>
            <h1 id="teacherDashboardTitle">Panel docente</h1>
            <p>Seguimiento de tus proyectos y revisiones académicas.</p>
        </div>
        <div class="teacher-dashboard-context" aria-label="Contexto académico">
            <?php if ($period !== null): ?>
                <strong><?= e((string) ($period['name'] ?? '')) ?></strong>
                <span><?= (int) ($period['days_remaining'] ?? 0) ?> días restantes</span>
            <?php else: ?>
                <span>Sin período académico activo</span>
            <?php endif; ?>
        </div>
    </header>

    <section class="teacher-summary" aria-labelledby="teacherSummaryTitle">
        <h2 id="teacherSummaryTitle">Resumen operativo</h2>
        <div class="teacher-summary-grid">
            <?php
            $summaryItems = [
                ['key' => 'assigned_projects', 'icon' => 'fa-folder-open', 'label' => 'Proyectos asignados', 'description' => 'Bajo tu responsabilidad'],
                ['key' => 'deliveries_to_review', 'icon' => 'fa-file-circle-check', 'label' => 'Entregas por revisar', 'description' => 'Requieren revisión docente'],
                ['key' => 'projects_in_review', 'icon' => 'fa-magnifying-glass', 'label' => 'Proyectos en revisión', 'description' => 'Seguimiento activo'],
                ['key' => 'pending_adjustments', 'icon' => 'fa-pen-to-square', 'label' => 'Ajustes pendientes', 'description' => 'Solicitudes por atender'],
            ];
            foreach ($summaryItems as $item):
                $metric = is_array($summary[$item['key']] ?? null) ? $summary[$item['key']] : ['count' => 0];
                $count = (int) ($metric['count'] ?? 0);
                $route = trim((string) ($metric['route'] ?? ''));
            ?>
                <article class="teacher-summary-item">
                    <i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i>
                    <div>
                        <strong><?= $count ?></strong>
                        <span><?= e($item['label']) ?></span>
                        <small><?= e($count === 0 && $item['key'] === 'pending_adjustments' ? 'Sin solicitudes por atender' : $item['description']) ?></small>
                    </div>
                    <?php if ($route !== ''): ?><a href="<?= e($route) ?>" aria-label="Ver <?= e(strtolower($item['label'])) ?>">Ver</a><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="teacher-projects" aria-labelledby="teacherProjectsTitle">
        <header class="teacher-section-header">
            <div>
                <h2 id="teacherProjectsTitle">Mis proyectos</h2>
                <p>Proyectos en los que participas como tutor, cotutor, evaluador o tribunal.</p>
            </div>
            <?php if ($hasMoreProjects): ?><a href="<?= e(route('assigned-projects')) ?>">Ver todos mis proyectos <span aria-hidden="true">→</span></a><?php endif; ?>
        </header>

        <?php if (!$visibleProjects): ?>
            <p class="teacher-empty-state">No tienes proyectos asignados actualmente.</p>
        <?php else: ?>
            <div class="teacher-project-list">
                <?php foreach ($visibleProjects as $project):
                    $roles = array_values(array_filter(array_map('strval', (array) ($project['roles'] ?? []))));
                    $pendingActions = array_values(array_filter(array_map('strval', (array) ($project['pending_actions'] ?? []))));
                    $projectRoute = trim((string) ($project['route'] ?? ''));
                    $situation = trim((string) ($project['teacher_situation'] ?? ''));
                ?>
                    <article class="teacher-project-item">
                        <div class="teacher-project-identity">
                            <span class="teacher-project-type"><?= e((string) ($project['type'] ?? 'Proyecto')) ?></span>
                            <h3><?= e((string) ($project['title'] ?? '')) ?></h3>
                            <span><?= e((string) ($project['code'] ?? '')) ?></span>
                        </div>
                        <div class="teacher-project-role">
                            <span>Tu relación</span>
                            <strong><?= e($roles ? implode(' · ', $roles) : 'Participante') ?></strong>
                        </div>
                        <div class="teacher-project-status">
                            <span>Estado</span>
                            <strong><?= e((string) ($project['status_label'] ?? '')) ?></strong>
                            <?php if ($situation !== ''): ?><p><?= e($situation) ?></p><?php endif; ?>
                        </div>
                        <div class="teacher-project-context">
                            <span><?= (int) ($project['deliveries_to_review'] ?? 0) ?> entregas por revisar</span>
                            <span><?= (int) ($project['observations_total'] ?? 0) ?> observaciones registradas</span>
                            <?php if ((int) ($project['adjustments_pending'] ?? 0) > 0): ?><span><?= (int) $project['adjustments_pending'] ?> ajustes pendientes</span><?php endif; ?>
                        </div>
                        <div class="teacher-project-action">
                            <?php if ($projectRoute !== ''): ?><a href="<?= e($projectRoute) ?>"><?= e($pendingActions[0] ?? ($situation !== '' ? 'Revisar proyecto' : 'Ver proyecto')) ?> <span aria-hidden="true">→</span></a><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="teacher-upcoming" aria-labelledby="teacherUpcomingTitle">
        <header class="teacher-section-header">
            <div>
                <h2 id="teacherUpcomingTitle">Próximas fechas</h2>
                <p>Compromisos relevantes para tu trabajo docente.</p>
            </div>
            <a href="<?= e(route('calendar')) ?>">Abrir calendario <span aria-hidden="true">→</span></a>
        </header>
        <?php if (!$upcoming): ?>
            <p class="teacher-empty-state">No hay próximas fechas relevantes.</p>
        <?php else: ?>
            <div class="teacher-upcoming-list">
                <?php foreach ($upcoming as $event):
                    $eventRoute = trim((string) ($event['route'] ?? route('calendar')));
                ?>
                    <article class="teacher-upcoming-item">
                        <time datetime="<?= e((string) ($event['date'] ?? '')) ?>"><?= e((string) ($event['date'] ?? '')) ?></time>
                        <div>
                            <h3><?= e((string) ($event['title'] ?? '')) ?></h3>
                            <?php if (!empty($event['project_id']) && !empty($event['project_title'])): ?>
                                <p><?= e((string) $event['project_title']) ?></p>
                            <?php else: ?>
                                <p>Evento personal</p>
                            <?php endif; ?>
                        </div>
                        <a href="<?= e($eventRoute) ?>" aria-label="Abrir <?= e((string) ($event['title'] ?? 'evento')) ?>">Ver <span aria-hidden="true">→</span></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($capabilities['manage_thesis_process']) && !empty($thesisManagement['enabled'])): ?>
        <section class="teacher-thesis-management" aria-labelledby="teacherThesisTitle">
            <h2 id="teacherThesisTitle">Gestión de titulación</h2>
            <p>Accede a los procesos de titulación que tienes habilitados.</p>
            <a href="<?= e((string) ($thesisManagement['route'] ?? route('thesis-management'))) ?>">Abrir gestión de titulación <span aria-hidden="true">→</span></a>
        </section>
    <?php endif; ?>
</main>
