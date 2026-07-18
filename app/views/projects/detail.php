<div class="project-detail">
<?php if ($project === null): ?>
    <section class="detail-empty detail-not-found"><span><i class="fa-regular fa-folder-open"></i></span><h1>Proyecto no encontrado</h1><p>El expediente no existe o no tienes permiso para consultarlo.</p><a href="<?= e(route('projects')) ?>">Volver a Mis proyectos</a></section>
<?php else: ?>
    <nav class="detail-breadcrumb" aria-label="Migas de pan"><a href="<?= e(route('projects')) ?>">Mis proyectos</a><i class="fa-solid fa-chevron-right"></i><span aria-current="page"><?= e($project['title']) ?></span></nav>
    <header class="detail-hero">
        <div class="detail-hero-main"><div class="detail-badges"><span><?= e($project['type']) ?></span><strong class="is-<?= e($project['status_key']) ?>"><?= e($project['status']) ?></strong></div><h1><?= e($project['title']) ?></h1><p><?= e($project['subtitle']) ?></p><ul><li><i class="fa-solid fa-graduation-cap"></i><?= e($project['career']) ?></li><li><i class="fa-regular fa-calendar"></i><?= e($project['period']) ?></li><li><i class="fa-solid fa-user-shield"></i><?= e($project['role']) ?></li></ul></div>
        <div class="detail-hero-side"><span>Tutor académico</span><strong><?= e($project['tutor'] ?: 'Por asignar') ?></strong><small><?= e($project['last_activity']) ?></small><a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=deliveries"><i class="fa-solid fa-plus"></i> Nueva entrega</a></div>
    </header>

    <section class="detail-kpis" aria-label="Resumen ejecutivo">
        <a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=summary"><span><i class="fa-solid fa-route"></i> Etapa actual</span><strong><?= e($project['stage']) ?></strong><small><?= (int) $project['progress'] ?>% del proceso</small></a>
        <a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=deliveries"><span><i class="fa-solid fa-file-arrow-up"></i> Última entrega</span><strong><?= e($project['latest_delivery']['version'] ?? 'Sin entregas') ?></strong><small><?= e($project['latest_delivery']['status'] ?? 'No registrada') ?></small></a>
        <a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=observations"><span><i class="fa-solid fa-list-check"></i> Observaciones</span><strong><?= count($project['observations']) ?> pendientes</strong><small>Consulta y atiende correcciones</small></a>
        <a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=calendar"><span><i class="fa-regular fa-calendar-check"></i> Próxima acción</span><strong><?= e($project['next_action']) ?></strong><small>Ver planificación</small></a>
    </section>

    <nav class="detail-tabs" role="tablist" aria-label="Secciones del proyecto">
        <?php foreach ($tabs as $key => $tab): ?><a role="tab" aria-selected="<?= $activeTab === $key ? 'true' : 'false' ?>" class="<?= $activeTab === $key ? 'is-active' : '' ?>" href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=<?= e($key) ?>"><i class="fa-solid <?= e($tab['icon']) ?>"></i><?= e($tab['label']) ?></a><?php endforeach; ?>
    </nav>

    <main class="detail-panel" role="tabpanel" tabindex="0">
        <?php if ($activeTab === 'summary'): ?>
            <div class="detail-summary-grid">
                <div class="detail-summary-main">
                    <section class="detail-section summary-stages">
                        <header><div><span>Ruta académica</span><h2>Etapas del proyecto</h2><p>El avance se obtiene de la etapa actual del expediente.</p></div><strong><?= e($project['stage']) ?></strong></header>
                        <ol class="summary-stage-list">
                            <?php foreach ($project['stages'] as $index => $stage): ?><li class="is-<?= e($stage['state']) ?>"><span><?= $stage['state'] === 'completed' ? '<i class="fa-solid fa-check"></i>' : (string) ($index + 1) ?></span><div><strong><?= e($stage['label']) ?></strong><small><?= $stage['state'] === 'completed' ? 'Completada' : ($stage['state'] === 'current' ? 'Etapa actual' : 'Próxima etapa') ?></small></div></li><?php endforeach; ?>
                        </ol>
                    </section>

                    <div class="summary-two-columns">
                        <section class="detail-section summary-delivery"><header><div><span>Trazabilidad</span><h2>Última entrega</h2></div><a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=deliveries">Ver entregas</a></header><?php if ($project['latest_delivery']): ?><div class="summary-delivery-file"><i class="fa-solid fa-file-pdf"></i><div><strong><?= e($project['latest_delivery']['title']) ?> · <?= e($project['latest_delivery']['version']) ?></strong><p><?= e($project['latest_delivery']['date']) ?></p><span><?= e($project['latest_delivery']['status']) ?></span></div></div><?php else: ?><p class="detail-inline-empty">Aún no existen entregas para este proyecto.</p><?php endif; ?></section>
                        <section class="detail-section summary-observations"><header><div><span>Revisión</span><h2>Observaciones prioritarias</h2></div><a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=observations">Ver todas</a></header><?php if ($project['observations']): ?><ul><?php foreach (array_slice($project['observations'], 0, 2) as $observation): ?><li><span><?= e($observation['status']) ?></span><strong><?= e($observation['title']) ?></strong><p><?= e($observation['text']) ?></p></li><?php endforeach; ?></ul><?php else: ?><p class="detail-inline-empty">No existen observaciones registradas.</p><?php endif; ?></section>
                    </div>

                    <section class="detail-section"><header><div><span>Trazabilidad reciente</span><h2>Actividad del proyecto</h2></div><a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=history">Ver historial completo</a></header><?php if ($project['activities']): ?><ul class="detail-activity"><?php foreach ($project['activities'] as $activity): ?><li><i class="fa-solid fa-circle-check"></i><div><strong><?= e($activity['title']) ?></strong><small><?= e($activity['date']) ?></small></div></li><?php endforeach; ?></ul><?php else: ?><p class="detail-inline-empty">Las actividades del proyecto aparecerán aquí.</p><?php endif; ?></section>
                </div>

                <aside class="detail-summary-side">
                    <section class="summary-next-action"><span>Acción recomendada</span><i class="fa-solid fa-compass"></i><h2><?= e($project['next_action']) ?></h2><p>Esta recomendación corresponde al estado actual del expediente.</p><a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=<?= $project['status_key'] === 'review' ? 'observations' : 'calendar' ?>">Continuar proceso <i class="fa-solid fa-arrow-right"></i></a></section>
                    <section><span>Participantes</span><h2><?= count($project['participants']) ?> integrantes</h2><div class="summary-participants"><?php foreach (array_slice($project['participants'], 0, 4) as $member): ?><div><b><?= e($member['initial']) ?></b><p><strong><?= e($member['name']) ?></strong><small><?= e($member['role']) ?></small></p></div><?php endforeach; ?></div><a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=participants">Ver equipo completo</a></section>
                    <section><span>Fechas clave</span><dl class="summary-key-dates"><?php foreach ($project['key_dates'] as $date): ?><div><dt><?= e($date['label']) ?></dt><dd><?= e($date['value']) ?></dd></div><?php endforeach; ?></dl><a href="<?= e(route('project-detail')) ?>&id=<?= (int) $project['id'] ?>&tab=calendar">Abrir calendario</a></section>
                    <section><span>Información académica</span><dl><?php foreach ($project['academic_info'] as $item): ?><div><dt><?= e($item['label']) ?></dt><dd><?= e($item['value']) ?></dd></div><?php endforeach; ?></dl></section>
                </aside>
            </div>
        <?php elseif ($activeTab === 'comments'): ?>
            <section class="detail-section detail-comments"><header><div><span>Comunicación general</span><h2>Comentarios del proyecto</h2><p>Conversaciones generales, con o sin relación a una entrega, archivo u observación.</p></div></header><div class="detail-notice"><i class="fa-solid fa-circle-info"></i> La publicación de comentarios se habilitará al conectar la persistencia. Esta vista no simula guardados.</div><?php if ($project['comments']): ?><?php foreach ($project['comments'] as $comment): ?><article><span><?= e(substr($comment['author'], 0, 1)) ?></span><div><header><strong><?= e($comment['author']) ?></strong><time><?= e($comment['date']) ?></time></header><p><?= e($comment['text']) ?></p><small><?= $comment['relation'] ? e($comment['relation']) : 'Comentario general' ?></small></div></article><?php endforeach; ?><?php else: ?><p class="detail-inline-empty">Aún no hay comentarios en este proyecto.</p><?php endif; ?></section>
        <?php elseif ($activeTab === 'deliveries' && $project['latest_delivery']): ?>
            <section class="detail-section"><header><div><span>Trazabilidad documental</span><h2>Entregas del proyecto</h2></div></header><article class="detail-delivery"><i class="fa-solid fa-file-pdf"></i><div><strong><?= e($project['latest_delivery']['title']) ?> · <?= e($project['latest_delivery']['version']) ?></strong><p><?= e($project['latest_delivery']['date']) ?> · <?= e($project['latest_delivery']['status']) ?></p></div></article><div class="detail-notice"><i class="fa-solid fa-circle-info"></i> La carga real de nuevas versiones se implementará con validación backend, permisos y archivos privados.</div></section>
        <?php elseif ($activeTab === 'observations' && $project['observations']): ?>
            <section class="detail-section"><header><div><span>Revisión académica</span><h2>Observaciones y correcciones</h2></div></header><div class="detail-observations"><?php foreach ($project['observations'] as $observation): ?><article><span><?= e($observation['status']) ?></span><h3><?= e($observation['title']) ?></h3><p><?= e($observation['text']) ?></p></article><?php endforeach; ?></div></section>
        <?php else: $emptyMessages = ['deliveries'=>'Aún no existen entregas para este proyecto.','observations'=>'No existen observaciones registradas.','history'=>'Las actividades del proyecto aparecerán aquí.','participants'=>'No se han asignado participantes.','calendar'=>'No hay actividades programadas para este proyecto.','final-documents'=>'Los documentos definitivos aparecerán cuando el proyecto alcance la etapa final.']; ?>
            <section class="detail-empty"><span><i class="fa-solid <?= e($tabs[$activeTab]['icon']) ?>"></i></span><h2><?= e($tabs[$activeTab]['label']) ?></h2><p><?= e($emptyMessages[$activeTab] ?? 'Esta sección se encuentra en preparación.') ?></p><small>La estructura está preparada para incorporarse progresivamente sin simular operaciones.</small></section>
        <?php endif; ?>
    </main>
<?php endif; ?>
</div>
