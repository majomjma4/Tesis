<?php
$dashboard = is_array($studentDashboard ?? null) ? $studentDashboard : [];
$projectStatus = (string) ($dashboard['projects']['status'] ?? 'error');
$projects = (array) ($studentProjects ?? []);
$report = is_array($studentProject ?? null) ? $studentProject : null;
$index = (int) ($studentProjectIndex ?? 0);
$projectCount = count($projects);
$index = $projectCount > 0 ? max(0, min($index, $projectCount - 1)) : 0;
$upcoming = (array) ($dashboard['upcoming']['items'] ?? []);
$notifications = array_slice((array) ($dashboard['notifications']['items'] ?? []), 0, 3);
$resources = array_slice((array) ($dashboard['resources']['items'] ?? []), 0, 7);
$urls = (array) ($report['urls'] ?? []);
?>
<main class="student-dashboard" aria-labelledby="studentDashboardTitle">
    <header class="student-context">
        <div><span class="student-context__eyebrow">Panel del estudiante</span>
            <h1 id="studentDashboardTitle">Seguimiento de tu proceso academico</h1>
        </div>
        <?php if ($report): ?>
            <div class="student-context__period"><i class="fa-regular fa-calendar" aria-hidden="true"></i>
                <div><span>Periodo academico</span><div class="student-project-dependent" data-project-dependent-group="period">
                    <?php foreach ($projects as $projectIndex => $project): ?><strong data-project-index="<?= $projectIndex ?>" <?= $projectIndex === $index ? '' : 'hidden' ?> aria-hidden="<?= $projectIndex === $index ? 'false' : 'true' ?>"><?= e((string) ($project['semester'] ?? 'Periodo no disponible')) ?></strong><?php endforeach; ?>
                </div>
                </div>
            </div><?php endif; ?>
    </header>

    <div class="student-dashboard-main">
        <?php if ($projectStatus === 'error'): ?>
            <section class="student-empty student-project-empty"><span class="student-empty__icon"><i
                        class="fa-solid fa-triangle-exclamation"></i></span>
                <p class="student-section-label">Proyecto del periodo</p>
                <h2>No fue posible cargar tus proyectos</h2>
                <p><?= e((string) ($dashboard['projects']['message'] ?? 'Intentelo nuevamente mas tarde.')) ?></p>
            </section>
        <?php elseif (!$report): ?>
            <section class="student-empty student-project-empty"><span class="student-empty__icon"><i
                        class="fa-regular fa-folder-open"></i></span>
                <p class="student-section-label">Proyecto del periodo</p>
                <h2>Aun no tienes un proyecto activo asociado</h2>
                <p>Cuando exista un proyecto autorizado para tu cuenta aparecera aqui.</p><a
                    class="student-button is-primary" href="<?= e(route('new-project')) ?>"><i class="fa-solid fa-plus"></i>
                    Registrar nuevo proyecto</a>
            </section>
        <?php else: ?>
            <div class="student-project-carousel <?= $projectCount > 1 ? 'is-multiple' : 'is-single' ?>"
                data-student-project-carousel data-project-count="<?= $projectCount ?>" data-initial-index="<?= $index ?>">
                <?php if ($projectCount > 1): ?>
                    <button class="student-project-carousel__control is-prev" type="button" data-project-prev
                        aria-label="Proyecto anterior"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    <div class="student-project-carousel__indicator" data-project-indicator aria-live="polite">Proyecto <?= $index + 1 ?> de <?= $projectCount ?></div>
                    <button class="student-project-carousel__control is-next" type="button" data-project-next
                        aria-label="Proyecto siguiente"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                <?php endif; ?>
                <div class="student-project-carousel__viewport">
                    <?php foreach ($projects as $projectIndex => $project):
                        $projectUrls = (array) ($project['urls'] ?? []);
                        $projectReview = (array) ($project['review'] ?? []);
                        $projectTypeLabel = (string) ($project['type'] ?? 'Proyecto academico');
                        if (str_contains(strtolower($projectTypeLabel), 'integrador') || in_array(strtolower((string) ($project['type_key'] ?? '')), ['pis', 'integrator'], true)) {
                            $projectTypeLabel = 'Proyecto PIS';
                        }
                        $projectTone = !empty($projectReview['needs_attention']) ? 'is-warning' : (($project['status'] ?? '') === 'published' ? 'is-success' : (($project['status'] ?? '') === 'defense' ? 'is-violet' : 'is-info'));
                        $isActiveProject = $projectIndex === $index;
                    ?>
                        <section class="student-project-focus <?= e($projectTone) ?>" data-project-index="<?= $projectIndex ?>"
                            <?= $isActiveProject ? '' : 'hidden' ?> aria-hidden="<?= $isActiveProject ? 'false' : 'true' ?>"
                            aria-labelledby="studentProjectTitle-<?= $projectIndex ?>">
                            <div class="student-project-focus__identity">
                                <div class="student-project-focus__topline"><span class="student-project-type"><?= e(mb_strtoupper($projectTypeLabel, 'UTF-8')) ?></span><span class="student-status"><i class="fa-solid fa-circle"></i><?= e((string) ($project['status_label'] ?? 'Estado no disponible')) ?></span></div>
                                <h2 id="studentProjectTitle-<?= $projectIndex ?>"><?= e((string) ($project['title'] ?? 'Proyecto sin titulo')) ?></h2>
                                <div class="student-project-meta">
                                    <?php if (!empty($project['code'])): ?><span><?= e((string) $project['code']) ?></span><?php endif; ?><span><i class="fa-solid fa-flag"></i>Estado del proyecto: <?= e((string) ($project['status_label'] ?? 'Estado no disponible')) ?></span><span><i class="fa-solid fa-route"></i>Etapa actual: <?= e((string) ($project['stage_label'] ?? 'Etapa no disponible')) ?></span><span><i class="fa-solid fa-user-tie"></i><?= e((string) ($project['tutor'] ?: 'Tutor aun no asignado')) ?></span>
                                </div>
                            </div>
                            <div class="student-review-state"><span class="student-review-state__icon"><i class="fa-solid <?= e((string) ($projectReview['icon'] ?? 'fa-file')) ?>"></i></span><div><p class="student-section-label"><?= e((string) ($projectReview['eyebrow'] ?? 'Situacion academica')) ?></p><h3><?= e((string) ($projectReview['title'] ?? 'Estado no disponible')) ?></h3><p><?= e((string) ($projectReview['text'] ?? 'Consulta el proyecto para conocer su situacion actual.')) ?></p><div class="student-actions"><a class="student-button is-primary" href="<?= e((string) ($projectReview['url'] ?? $projectUrls['summary'] ?? route('projects'))) ?>"><?= e((string) ($projectReview['action'] ?? 'Ver proyecto')) ?><i class="fa-solid fa-arrow-right"></i></a></div></div></div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <aside class="student-upcoming teacher-agenda" aria-labelledby="studentUpcomingTitle">
            <header class="teacher-section-header">
                <div>
                    <p class="teacher-section-label">Agenda</p>
                    <h2 id="studentUpcomingTitle">Proximas fechas</h2>
                </div>
                <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
            </header>
            <?php if (($dashboard['upcoming']['status'] ?? '') === 'error'): ?>
                <div class="teacher-empty-state"><strong>No fue posible cargar la
                        agenda.</strong><span><?= e((string) ($dashboard['upcoming']['message'] ?? 'Intentelo nuevamente mas tarde.')) ?></span>
                </div>
            <?php elseif ($upcoming === []): ?>
                <div class="teacher-empty-state"><strong>No tienes proximas fechas.</strong><span>No hay eventos futuros
                        registrados para tu cuenta.</span></div>
            <?php else: ?>
                <ul class="teacher-agenda-list"><?php foreach (array_slice($upcoming, 0, 3) as $event): ?>
                        <li><time><?= e((string) ($event['date'] ?? '-')) ?></time><strong><?= e((string) ($event['title'] ?? 'Fecha academica')) ?></strong><?php if (!empty($event['context'])): ?><span><?= e((string) $event['context']) ?></span><?php endif; ?><?php if (!empty($event['time'])): ?><span><?= e((string) $event['time']) ?></span><?php endif; ?>
                        </li><?php endforeach; ?>
                </ul><?php endif; ?><a class="teacher-inline-link" href="<?= e(route('calendar')) ?>">Abrir calendario <span
                    aria-hidden="true">→</span></a>
        </aside>

        <?php if ($report): ?>
            <section class="student-follow-up" aria-label="Seguimiento del proyecto">
                <div class="student-project-dependent" data-project-dependent-group="observations">
                    <?php foreach ($projects as $projectIndex => $project):
                        $projectUrls = (array) ($project['urls'] ?? []);
                        $projectObservations = (array) ($project['observations'] ?? []);
                        $projectAddressed = count(array_filter($projectObservations, static fn(array $item): bool => in_array(strtolower((string) ($item['status'] ?? '')), ['addressed', 'resolved'], true)));
                        $isActiveProject = $projectIndex === $index;
                    ?>
                        <article class="student-observations" data-project-index="<?= $projectIndex ?>" <?= $isActiveProject ? '' : 'hidden' ?> aria-hidden="<?= $isActiveProject ? 'false' : 'true' ?>">
                            <header class="student-card-heading"><div><p class="student-section-label">Seguimiento</p><h2>Observaciones</h2></div><span class="student-card-icon"><i class="fa-regular fa-comments"></i></span></header>
                            <?php if (($project['pending_observations'] ?? 0) > 0 || $projectAddressed > 0): ?>
                                <div class="student-observation-counts"><div class="is-pending"><strong><?= (int) ($project['pending_observations'] ?? 0) ?></strong><span>pendientes</span></div><div><strong><?= $projectAddressed ?></strong><span>atendidas o resueltas</span></div></div>
                                <p class="student-observation-message"><?= ($project['pending_observations'] ?? 0) > 0 ? 'Requieren tu atencion.' : 'No tienes observaciones pendientes.' ?></p><a class="student-inline-link" href="<?= e((string) ($projectUrls['observations'] ?? route('projects'))) ?>">Ver observaciones <i class="fa-solid fa-arrow-right"></i></a>
                            <?php else: ?><div class="student-compact-empty is-inline"><i class="fa-regular fa-circle-check"></i><p>No hay observaciones registradas.</p></div><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="student-project-dependent" data-project-dependent-group="delivery">
                    <?php foreach ($projects as $projectIndex => $project):
                        $projectUrls = (array) ($project['urls'] ?? []);
                        $projectDelivery = is_array($project['latest_delivery'] ?? null) ? $project['latest_delivery'] : null;
                        $isActiveProject = $projectIndex === $index;
                    ?>
                        <article class="student-delivery" data-project-index="<?= $projectIndex ?>" <?= $isActiveProject ? '' : 'hidden' ?> aria-hidden="<?= $isActiveProject ? 'false' : 'true' ?>">
                            <header class="student-card-heading"><div><p class="student-section-label">Documento enviado</p><h2>Ultima entrega</h2></div><span class="student-card-icon"><i class="fa-regular fa-file-lines"></i></span></header>
                            <?php if ($projectDelivery): ?><div class="student-delivery__summary"><div><span>Estado</span><strong><?= e(project_delivery_status_label((string) ($projectDelivery['status'] ?? ''))) ?></strong></div><div><span>Fecha</span><strong><?= e((string) ($projectDelivery['submitted_at'] ?? 'Fecha no disponible')) ?></strong></div></div><p class="student-delivery__situation">Entrega documental registrada.</p><a class="student-inline-link" href="<?= e((string) ($projectUrls['deliveries'] ?? route('projects'))) ?>">Ver entregas <i class="fa-solid fa-arrow-right"></i></a>
                            <?php else: ?><div class="student-compact-empty is-inline"><i class="fa-regular fa-file"></i><p>Aun no hay entregas registradas.</p></div><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <article class="student-notifications teacher-notifications" aria-labelledby="studentNotificationsTitle">
                    <header class="teacher-section-header">
                        <div>
                            <p class="teacher-section-label">Actualidad</p>
                            <h2 id="studentNotificationsTitle">Notificaciones recientes</h2>
                        </div><a class="teacher-inline-link" href="<?= e(route('notifications')) ?>">Ver todas <span
                                aria-hidden="true">→</span></a>
                    </header><?php if (($dashboard['notifications']['status'] ?? '') === 'error'): ?>
                        <div class="teacher-empty-state"><strong>Notificaciones no
                                disponibles</strong><span><?= e((string) ($dashboard['notifications']['message'] ?? 'Intentelo nuevamente mas tarde.')) ?></span>
                        </div><?php elseif ($notifications === []): ?>
                        <div class="teacher-empty-state"><strong>Todo al dia</strong><span>0 notificaciones pendientes.</span>
                        </div><?php else: ?>
                        <ul class="teacher-notification-list"><?php foreach ($notifications as $notification): ?>
                                <li class="<?= empty($notification['is_read']) ? 'is-unread' : '' ?>"><a
                                        class="teacher-notification-entry"
                                        href="<?= e((string) ($notification['action_url'] ?? route('notifications'))) ?>"><i
                                            class="fa-regular fa-bell" aria-hidden="true"></i><span
                                            class="teacher-notification-main"><span
                                                class="teacher-notification-heading"><strong><?= e((string) ($notification['title'] ?? 'Notificacion')) ?></strong><time><?= e((string) ($notification['created_at'] ?? '')) ?></time></span><small><?= e((string) ($notification['message'] ?? '')) ?></small></span></a>
                                </li><?php endforeach; ?>
                        </ul><?php endif; ?>
                </article>
            </section><?php endif; ?>

        <section class="student-resources" aria-labelledby="studentResourcesTitle">
            <header class="student-section-header">
                <div>
                    <p class="student-section-label">Repositorio institucional</p>
                    <h2 id="studentResourcesTitle">Recursos para ti</h2>
                    <p>Materiales y proyectos publicados disponibles para estudiantes.</p>
                </div><a class="student-inline-link" href="<?= e(route('repository')) ?>">Explorar repositorio <i
                        class="fa-solid fa-arrow-right"></i></a>
            </header><?php if (($dashboard['resources']['status'] ?? '') === 'error'): ?>
                <div class="student-resource-empty"><i class="fa-solid fa-triangle-exclamation"></i><strong>Recursos no
                        disponibles</strong><span><?= e((string) ($dashboard['resources']['message'] ?? 'Intentelo nuevamente mas tarde.')) ?></span>
                </div><?php elseif ($resources === []): ?>
                <div class="student-resource-empty"><i class="fa-regular fa-folder-open"></i><strong>Aun no hay recursos
                        disponibles.</strong><span>Los recursos publicados apareceran aqui cuando existan.</span></div>
            <?php else: ?>
                <div class="student-carousel" data-student-carousel><button class="student-carousel__control is-prev"
                        type="button" data-carousel-prev aria-label="Recursos anteriores"><i
                            class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    <div class="student-carousel__viewport">
                        <div class="student-carousel__track" tabindex="0"><?php foreach ($resources as $resource): ?><a
                                    class="student-resource-card"
                                    href="<?= e((string) ($resource['route'] ?? route('repository'))) ?>">
                                    <div class="student-resource-card__visual">
                                        <span><?= e((string) ($resource['badge'] ?? 'Recurso')) ?></span><i
                                            class="fa-solid <?= e((string) ($resource['icon'] ?? 'fa-book-open')) ?>"
                                            aria-hidden="true"></i></div>
                                    <h3><?= e((string) ($resource['title'] ?? 'Recurso academico')) ?></h3>
                                    <p><?= e((string) ($resource['description'] ?? '')) ?></p>
                                    <small><?= e((string) ($resource['meta'] ?? '')) ?></small><strong>Ver recurso <i
                                            class="fa-solid fa-arrow-right" aria-hidden="true"></i></strong>
                                </a><?php endforeach; ?></div>
                    </div><button class="student-carousel__control is-next" type="button" data-carousel-next
                        aria-label="Recursos siguientes"><i class="fa-solid fa-chevron-right"
                            aria-hidden="true"></i></button>
                </div><?php endif; ?>
        </section>
    </div>
</main>
