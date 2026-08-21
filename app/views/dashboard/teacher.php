<?php

$dashboard = is_array($teacherDashboard ?? null) ? $teacherDashboard : [];
$context = is_array($dashboard['context'] ?? null) ? $dashboard['context'] : [];
$period = is_array($context['academic_period'] ?? null) ? $context['academic_period'] : null;
$assigned = is_array($dashboard['assigned_projects'] ?? null) ? $dashboard['assigned_projects'] : [];
$projects = is_array($assigned['items'] ?? null) ? $assigned['items'] : [];
$follow = is_array($dashboard['follow_up'] ?? null) ? $dashboard['follow_up'] : [];
$upcoming = is_array($dashboard['upcoming'] ?? null) ? $dashboard['upcoming'] : [];
$notifications = is_array($dashboard['notifications'] ?? null) ? $dashboard['notifications'] : [];
$repository = is_array($dashboard['repository'] ?? null) ? $dashboard['repository'] : [];
$showTeacherTribunalQa = strtolower((string) ($GLOBALS['config']['environment'] ?? 'production')) === 'development'
    && (string) ($_GET['qa_teacher_tribunal'] ?? '') === '1';

$empty = static function (string $title, string $detail = ''): void {
    echo '<div class="teacher-empty-state"><strong>' . e($title) . '</strong>'
        . ($detail !== '' ? '<span>' . e($detail) . '</span>' : '') . '</div>';
};

$relativeTime = static function (string $value): string {
    if ($value === '') return '';
    try {
        $created = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        $seconds = time() - $created->getTimestamp();
    } catch (Throwable) {
        return '';
    }
    if ($seconds < 0) return 'ahora';
    if ($seconds < 60) return 'hace ' . max(1, $seconds) . ' s';
    if ($seconds < 3600) return 'hace ' . intdiv($seconds, 60) . ' min';
    if ($seconds < 86400) return 'hace ' . intdiv($seconds, 3600) . ' h';
        if ($seconds < 604800) return 'hace ' . intdiv($seconds, 86400) . ' d';
        if ($seconds < 2592000) return 'hace ' . intdiv($seconds, 604800) . ' sem';
        $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $local = $created->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return (int) $local->format('j') . ' ' . $months[(int) $local->format('n') - 1];
};

$projectSentence = static function (int $count, string $ending): string {
    return $count . ' ' . ($count === 1 ? 'proyecto' : 'proyectos') . ' ' . $ending;
};
?>
<main class="teacher-dashboard" aria-labelledby="teacherDashboardTitle">
    <?php if (!empty($teacherDashboardError)): ?>
        <p class="teacher-dashboard-error" role="alert"><?= e($teacherDashboardError) ?></p>
    <?php endif; ?>
    <header class="teacher-dashboard-header">
        <div>
            <p class="teacher-section-label">Panel del docente</p>
            <h1 id="teacherDashboardTitle">Seguimiento académico</h1>
            <p>Seguimiento académico y revisión de proyectos.</p>
        </div>
        <div class="teacher-dashboard-context">
            <span>Período académico</span>
            <strong><?= e($period['name'] ?? 'Sin período activo') ?></strong>
            <?php if ($period !== null && isset($period['days_remaining'])): ?>
                <small><?= (int) $period['days_remaining'] ?> días restantes</small>
            <?php endif; ?>
        </div>
    </header>

    <div class="teacher-dashboard-main">
        <section class="teacher-projects-section" aria-labelledby="teacherProjectsTitle">
            <header class="teacher-section-header">
                <div>
                    <p class="teacher-section-label">Proyectos asignados</p>
                    <h2 id="teacherProjectsTitle">Proyectos del período activo</h2>
                    <p><?= (int) ($assigned['count'] ?? count($projects)) ?> proyecto(s) bajo tu responsabilidad.</p>
                </div>
                <?php if ($projects): ?>
                    <a class="teacher-inline-link" href="<?= e($assigned['route'] ?? route('assigned-projects')) ?>">Ver todos los proyectos →</a>
                <?php endif; ?>
            </header>
            <?php if (!$projects): ?>
                <?php $empty('Sin proyectos asignados en este período.', 'Cuando tengas participación como tutor, cotutor o tribunal, aparecerá aquí.'); ?>
            <?php else: ?>
                <div class="teacher-projects-carousel" data-teacher-projects-carousel>
                    <div class="teacher-projects-carousel__viewport">
                        <div class="teacher-projects-grid" data-carousel-track>
                        <?php foreach ($projects as $project):
                            $situation = is_array($project['teacher_situation'] ?? null) ? $project['teacher_situation'] : [];
                            $students = is_array($project['students'] ?? null) ? $project['students'] : [];
                            $names = array_values(array_filter(array_map(static fn (array $student): string => (string) ($student['name'] ?? ''), $students)));
                            $action = is_array($project['action'] ?? null) ? $project['action'] : [];
                        ?>
                            <article class="teacher-project-card">
                                <div class="teacher-project-meta">
                                    <span><?= e($project['code'] ?? '') ?></span>
                                    <span><?= e($project['type'] ?? 'Proyecto') ?></span>
                                </div>
                                <h3><?= e($project['title'] ?? '') ?></h3>
                                <p class="teacher-project-students"><i class="fa-solid fa-users" aria-hidden="true"></i> <?= e(implode(' · ', array_slice($names, 0, 2)) ?: 'Sin estudiantes registrados') ?><?php if (count($names) > 2): ?> +<?= count($names) - 2 ?><?php endif; ?></p>
                                <p class="teacher-project-role"><span>Rol docente</span><strong><?= e(implode(' · ', (array) ($project['roles'] ?? []))) ?></strong></p>
                                <div class="teacher-project-status">
                                    <span><?= e($project['status_label'] ?? $project['status'] ?? '') ?></span>
                                    <strong><?= e($situation['label'] ?? 'En seguimiento') ?></strong>
                                    <?php if (!empty($situation['description'])): ?><small><?= e($situation['description']) ?></small><?php endif; ?>
                                </div>
                                <?php if (!empty($action['route'])): ?><a class="teacher-project-action" href="<?= e($action['route']) ?>"><?= e($action['label'] ?? 'Ver proyecto') ?> →</a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (count($projects) > 1): ?>
                        <div class="teacher-projects-carousel__navigation" data-carousel-navigation>
                            <span class="teacher-projects-carousel__status" data-carousel-status aria-live="polite"></span>
                            <button type="button" data-carousel-prev aria-label="Mostrar proyectos anteriores"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                            <button type="button" data-carousel-next aria-label="Mostrar proyectos siguientes"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="teacher-agenda" aria-labelledby="teacherAgendaTitle">
            <header class="teacher-section-header">
                <div><p class="teacher-section-label">Agenda</p><h2 id="teacherAgendaTitle">Próximas fechas</h2></div>
                <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
            </header>
            <?php $events = is_array($upcoming['items'] ?? null) ? $upcoming['items'] : []; ?>
            <?php if (!$events): ?>
                <?php $empty('No tienes próximas fechas.'); ?>
            <?php else: ?>
                <ul class="teacher-agenda-list">
                    <?php foreach (array_slice($events, 0, 3) as $event): ?>
                        <li>
                            <time><?= e($event['date'] ?? '') ?></time>
                            <strong><?= e($event['title'] ?? 'Fecha académica') ?></strong>
                            <?php if (!empty($event['context'])): ?><span><?= e($event['context']) ?></span><?php endif; ?>
                            <?php if (!empty($event['time'])): ?><span><?= e($event['time']) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <a class="teacher-inline-link" href="<?= e($upcoming['route'] ?? route('calendar')) ?>">Abrir calendario →</a>
        </section>

        <section class="teacher-follow-up" aria-labelledby="teacherFollowUpTitle">
            <header class="teacher-section-header">
                <div><p class="teacher-section-label">Trabajo pendiente</p><h2 id="teacherFollowUpTitle">Seguimiento</h2><p>Lo que requiere atención o seguimiento docente.</p></div>
            </header>
            <div class="teacher-follow-up-grid">
                <?php $review = is_array($follow['review_required'] ?? null) ? $follow['review_required'] : []; $reviewProjects = (int) ($review['projects_count'] ?? 0); ?>
                <article class="teacher-follow-up-card teacher-follow-up-card--review-required<?= (int) ($review['count'] ?? 0) === 0 ? ' teacher-follow-up-card--empty' : '' ?>">
                    <i class="fa-solid fa-file-circle-check" aria-hidden="true"></i>
                    <div><h3>Por revisar</h3><?php if ((int) ($review['count'] ?? 0) > 0): ?><strong><?= (int) $review['count'] ?></strong><p><?= e($projectSentence($reviewProjects, 'requieren revisión')) ?></p><?php if (!empty($review['route'])): ?><a class="teacher-follow-up-link" href="<?= e($review['route']) ?>">Ver pendientes →</a><?php endif; ?><?php else: ?><p>Todo al día</p><small>No tienes revisiones pendientes</small><?php endif; ?></div>
                </article>
                <?php $waiting = is_array($follow['waiting_student'] ?? null) ? $follow['waiting_student'] : []; $waitingProjects = (int) ($waiting['projects_count'] ?? $waiting['count'] ?? 0); ?>
                <article class="teacher-follow-up-card teacher-follow-up-card--waiting-student<?= (int) ($waiting['count'] ?? 0) === 0 ? ' teacher-follow-up-card--empty' : '' ?>">
                    <i class="fa-solid fa-user-clock" aria-hidden="true"></i>
                    <div><h3>Esperando estudiante</h3><?php if ((int) ($waiting['count'] ?? 0) > 0): ?><strong><?= (int) $waiting['count'] ?></strong><p><?= e($projectSentence($waitingProjects, 'en espera')) ?></p><?php else: ?><p>Sin proyectos en espera</p><?php endif; ?></div>
                </article>
                <?php $tribunal = is_array($follow['tribunal_assignment'] ?? null) ? $follow['tribunal_assignment'] : []; ?>
                <?php if ((!empty($tribunal['visible']) && (int) ($tribunal['count'] ?? 0) > 0) || $showTeacherTribunalQa): ?>
                    <article class="teacher-follow-up-card teacher-follow-up-card--tribunal-assignment">
                        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                        <div><h3>Asignación de tribunal</h3><strong><?= $showTeacherTribunalQa && empty($tribunal['visible']) ? 2 : (int) $tribunal['count'] ?></strong><p>En espera</p></div>
                    </article>
                <?php endif; ?>
            </div>
        </section>

        <section class="teacher-notifications" aria-labelledby="teacherNotificationsTitle">
            <header class="teacher-section-header">
                <div><p class="teacher-section-label">Actualidad</p><h2 id="teacherNotificationsTitle">Notificaciones</h2></div>
                <a class="teacher-inline-link" href="<?= e($notifications['route'] ?? route('notifications')) ?>">Ver todas →</a>
            </header>
            <?php $items = is_array($notifications['items'] ?? null) ? $notifications['items'] : []; ?>
            <?php if (!$items): ?>
                <?php $empty('Todo al día', 'No tienes notificaciones nuevas.'); ?>
            <?php else: ?>
                <ul class="teacher-notification-list">
                    <?php foreach (array_slice($items, 0, 3) as $item):
                        $actionUrl = trim((string) ($item['action_url'] ?? ''));
                        $entryClass = 'teacher-notification-entry' . (empty($item['is_read']) ? ' is-unread' : '');
                    ?>
                        <li class="<?= empty($item['is_read']) ? 'is-unread' : '' ?>">
                            <?php if ($actionUrl !== ''): ?><a class="<?= $entryClass ?>" href="<?= e($actionUrl) ?>"><?php else: ?><div class="<?= $entryClass ?>"><?php endif; ?>
                                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                                <span class="teacher-notification-main">
                                    <span class="teacher-notification-heading"><strong><?= e($item['title'] ?? 'Notificación') ?></strong><time><?= e($relativeTime((string) ($item['created_at'] ?? ''))) ?></time></span>
                                    <?php if (!empty($item['context'])): ?><small><?= e($item['context']) ?></small><?php endif; ?>
                                </span>
                            <?php if ($actionUrl !== ''): ?></a><?php else: ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="teacher-repository" aria-labelledby="teacherRepositoryTitle">
            <header class="teacher-section-header">
                <div><p class="teacher-section-label">Repositorio académico</p><h2 id="teacherRepositoryTitle">Proyectos publicados</h2><p>Trabajos disponibles en el repositorio institucional.</p></div>
                <a class="teacher-inline-link" href="<?= e($repository['route'] ?? route('repository')) ?>">Explorar repositorio →</a>
            </header>
            <?php $repo = is_array($repository['items'] ?? null) ? $repository['items'] : []; ?>
            <?php if (!$repo): ?>
                <?php $empty('Aún no hay proyectos publicados disponibles.'); ?>
            <?php else: ?>
                <div class="teacher-repository-grid">
                    <?php foreach ($repo as $item): ?>
                        <a class="teacher-repository-card" href="<?= e($item['route'] ?? route('repository')) ?>">
                            <div class="teacher-project-meta"><span><?= e($item['code'] ?? '') ?></span><span><?= e($item['type'] ?? 'Proyecto') ?></span></div>
                            <h3><?= e($item['title'] ?? '') ?></h3>
                            <p><?= e(is_array($item['authors'] ?? null) ? implode(' · ', $item['authors']) : ($item['authors'] ?? '')) ?></p>
                            <small><?= e(trim(($item['career'] ?? '') . ' · ' . ($item['period'] ?? ''), ' ·')) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>
