<div class="projects-page" data-projects-page>
    <header class="projects-heading">
        <div><span class="projects-eyebrow">Gestión académica</span><h1>Mis proyectos</h1><p>Consulta, organiza y continúa el seguimiento de tus expedientes académicos.</p></div>
    </header>

    <?php if ($projects === []): ?>
        <section class="projects-empty projects-empty-primary">
            <span><i class="fa-regular fa-folder-open"></i></span><h2>Aún no tienes proyectos</h2>
            <p>Cuando registres tu primer proyecto académico, su seguimiento aparecerá aquí.</p>
            <a href="<?= e(route('new-project')) ?>"><i class="fa-solid fa-plus"></i> Crear mi primer proyecto</a>
        </section>
    <?php else: ?>
        <section class="projects-metrics" aria-label="Resumen y filtros por estado">
            <?php foreach ($metrics as $metric): ?>
                <button type="button" class="projects-metric" data-metric="<?= e($metric['key']) ?>" aria-pressed="false">
                    <span><i class="fa-solid <?= e($metric['icon']) ?>"></i></span><strong><?= (int) $metric['count'] ?></strong><small><?= e($metric['label']) ?></small>
                </button>
            <?php endforeach; ?>
        </section>

        <section class="projects-toolbar" aria-label="Buscar y filtrar proyectos">
            <label class="projects-search"><i class="fa-solid fa-magnifying-glass"></i><span class="sr-only">Buscar proyectos</span><input type="search" data-project-search placeholder="Buscar por título, tutor, tipo o periodo"></label>
            <label><span class="sr-only">Estado</span><select data-project-status><option value="">Todos los estados</option><option value="review">En revisión</option><option value="approved">Aprobado</option><option value="defense">En defensa</option><option value="published">Publicado</option></select></label>
            <label><span class="sr-only">Tipo</span><select data-project-type><option value="">Todos los tipos</option><option value="thesis">Titulación</option><option value="integrator">Integrador</option><option value="community">Vinculación</option></select></label>
            <label><span class="sr-only">Ordenar</span><select data-project-sort><option value="activity">Actividad reciente</option><option value="title">Título A–Z</option><option value="progress">Mayor progreso</option></select></label>
            <button type="button" class="projects-clear" data-project-clear hidden><i class="fa-solid fa-xmark"></i> Limpiar</button>
        </section>
        <p class="projects-filter-status" data-filter-status aria-live="polite">Mostrando todos tus proyectos.</p>

        <section class="projects-grid" data-project-grid aria-label="Catálogo de proyectos">
            <?php foreach ($projects as $project):
                $search = strtolower(implode(' ', [$project['title'], $project['subtitle'], $project['type'], $project['tutor'], $project['period'], $project['career']]));
                $actionUrl = !empty($project['repository_id']) ? route('repository-detail') . '&id=' . (int) $project['repository_id'] : route('project-detail') . '&id=' . (int) $project['id'];
            ?>
                <article class="projects-card" data-project-card data-search="<?= e($search) ?>" data-status="<?= e($project['status_key']) ?>" data-type="<?= e($project['type_key']) ?>" data-metric="<?= e($project['metric_bucket']) ?>" data-title="<?= e($project['title']) ?>" data-progress="<?= (int) $project['progress'] ?>">
                    <header><span class="projects-type"><?= e($project['type']) ?></span><span class="projects-status is-<?= e($project['status_key']) ?>"><?= e($project['status']) ?></span></header>
                    <div class="projects-card-title"><h2><?= e($project['title']) ?></h2><p><?= e($project['subtitle']) ?></p></div>
                    <dl class="projects-core"><div><dt><i class="fa-solid fa-chalkboard-user"></i> Tutor o responsable</dt><dd><?= e($project['tutor'] ?: 'Por asignar') ?></dd></div><div><dt><i class="fa-regular fa-clock"></i> Última actividad</dt><dd><?= e($project['last_activity']) ?></dd></div></dl>
                    <?php if ($project['progress'] < 100): ?><div class="projects-progress"><div><span><?= e($project['stage']) ?></span><strong><?= (int) $project['progress'] ?>%</strong></div><progress value="<?= (int) $project['progress'] ?>" max="100"><?= (int) $project['progress'] ?>%</progress></div><?php endif; ?>
                    <dl class="projects-context"><?php foreach ($project['context'] as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?></dl>
                    <footer><a href="<?= e($actionUrl) ?>"><?= e($project['action_label']) ?> <i class="fa-solid fa-arrow-right"></i></a></footer>
                </article>
            <?php endforeach; ?>
        </section>
        <section class="projects-empty" data-project-empty hidden><span><i class="fa-solid fa-filter-circle-xmark"></i></span><h2>No encontramos proyectos</h2><p>Prueba con otra búsqueda o limpia los filtros aplicados.</p><button type="button" data-project-clear>Limpiar búsqueda y filtros</button></section>
    <?php endif; ?>
</div>
