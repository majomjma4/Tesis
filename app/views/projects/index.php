<div class="projects-page" data-projects-page>
    <header class="projects-heading">
        <div><span class="projects-eyebrow">Gestión académica</span><h1>Mis proyectos</h1><p>Consulta, organiza y continúa el seguimiento de tus expedientes académicos.</p></div>
    </header>

    <?php if ($projects === []): ?>
        <section class="projects-empty projects-empty-primary">
            <span><i class="fa-regular fa-folder-open"></i></span><h2>Aún no tienes proyectos</h2>
            <p>Cuando registres tu primer proyecto académico, su seguimiento aparecerá aquí.</p>
            <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; margin-top: 1rem;">
                <a href="<?= e(route('new-project')) ?>"><i class="fa-solid fa-plus"></i> Crear mi primer proyecto</a>
                <?php if (app_is_development() && !(new AuthSessionService())->hasAdminAccess()): ?>
                    <a href="<?= e(route('project-detail')) ?>&demo=1" style="background: var(--surface-soft, #f1f5f9); color: var(--text, #334155); border: 1px solid var(--line, #cbd5e1); border-radius: 8px; padding: 0.55rem 1rem; text-decoration: none; font-weight: 700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-vial-circle-check" style="color: #0284c7;"></i> Ver proyecto de demostración (Simulación visual)
                    </a>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="projects-toolbar" aria-label="Buscar y filtrar proyectos">
            <label class="projects-search"><i class="fa-solid fa-magnifying-glass"></i><span class="sr-only">Buscar proyectos</span><input type="search" data-project-search placeholder="Buscar por título, tutor, tipo o periodo"></label>
            <label><span class="sr-only">Estado</span><select data-project-status><option value="">Todos los estados</option><option value="development">En desarrollo</option><option value="under_review">En revisión</option><option value="approved">Aprobado</option><option value="defense">En tribunal</option><option value="tribunal_approved">Aprobado por el Tribunal</option><option value="published">Publicado</option></select></label>
            <label><span class="sr-only">Situación de revisión</span><select data-project-situation><option value="">Todas las situaciones</option><option value="pending">Con observaciones pendientes</option></select></label>
            <label><span class="sr-only">Tipo</span><select data-project-type><option value="">Todos los tipos</option><option value="thesis">Titulación</option><option value="thesis_profile">Perfil de tesis</option><option value="pis">Integrador</option><option value="practice">Prácticas</option><option value="community">Vinculación</option></select></label>
            <label><span class="sr-only">Periodo académico</span><select data-project-period><option value="">Todos los periodos</option><option value="2026-I">2026-I</option><option value="2025-II">2025-II</option></select></label>
            <label><span class="sr-only">Ordenar</span><select data-project-sort><option value="activity">Actividad reciente</option><option value="title">Título A–Z</option><option value="progress">Mayor progreso</option></select></label>
            <button type="button" class="projects-clear" data-project-clear hidden><i class="fa-solid fa-xmark"></i> Limpiar</button>
        </section>
        <p class="projects-filter-status" data-filter-status aria-live="polite">Mostrando todos tus proyectos.</p>

        <section class="projects-grid" data-project-grid aria-label="Catálogo de proyectos">
            <?php foreach ($projects as $project):
                $search = mb_strtolower(implode(' ', array_merge([$project['title'] ?? '', $project['subtitle'] ?? '', $project['type'] ?? '', $project['tutor'] ?? '', $project['period'] ?? '', $project['career'] ?? ''], (array) ($project['tags'] ?? []), (array) ($project['technologies'] ?? []))), 'UTF-8');
                $actionUrl = route('project-detail') . '&id=' . (int) $project['id'];
                $cardAction = 'Abrir proyecto';
                $phaseContext = [];
                if ($project['status_key'] === 'under_review') {
                    $phaseContext = ['Última entrega' => $project['latest_delivery']['version'] ?? 'Sin entregas', 'Observaciones pendientes' => count($project['observations'])];
                    $cardAction = 'Atender revisión'; $actionUrl .= '&tab=review';
                } elseif ($project['status_key'] === 'approved') {
                    $phaseContext = ['Aprobación' => $project['key_dates'][1]['value'] ?? 'Registrada', 'Documentos finales' => $project['final_documents'] ? 'Disponibles' : 'Pendientes'];
                    $cardAction = 'Preparar documentos'; $actionUrl .= '&tab=documents';
                } elseif ($project['status_key'] === 'defense') {
                    $phaseContext = ['Fecha' => $project['key_dates'][2]['value'] ?? 'Por programar', 'Evaluación' => 'En proceso'];
                    $cardAction = 'Consultar evaluación'; $actionUrl .= '&tab=information';
                } elseif ($project['status_key'] === 'tribunal_approved') {
                    $phaseContext = ['Tribunal' => 'Aprobado', 'Publicación' => 'Pendiente'];
                    $cardAction = 'Preparar publicación'; $actionUrl .= '&tab=documents';
                } elseif ($project['status_key'] === 'published') {
                    $phaseContext = ['Publicación' => $project['key_dates'][1]['value'] ?? 'Publicada', 'Disponibilidad' => 'Repositorio institucional'];
                    $cardAction = 'Abrir ficha institucional';
                    if (!empty($project['repository_id'])) $actionUrl = route('repository-detail') . '&id=' . (int) $project['repository_id'];
                } else $phaseContext = ['Etapa' => $project['stage'], 'Expediente' => $project['status']];
            ?>
                <?php $hasPendingReview=!empty($project['review_situation']['has_pending_observations']); ?>
                <article class="projects-card" data-project-card data-search="<?= e($search) ?>" data-status="<?= e($project['status_key']) ?>" data-situation="<?=$hasPendingReview?'pending':'none'?>" data-type="<?= e($project['type_key']) ?>" data-period="<?= e($project['period']) ?>" data-metric="<?= e($project['metric_bucket']) ?>" data-title="<?= e($project['title']) ?>" data-progress="<?= (int) $project['progress'] ?>" data-activity="<?= (int) $project['activity_order'] ?>">
                    <header><span class="projects-type"><?= e($project['type']) ?></span><span class="projects-status is-<?= e($project['status_key']) ?>"><?= e($project['status']) ?></span></header>
                    <?php if($hasPendingReview):?><span class="projects-review-situation"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Observaciones pendientes</span><?php endif;?>
                    <div class="projects-card-title"><h2><?= e($project['title']) ?></h2></div>
                    <div class="project-card-tutor"><i class="fa-solid fa-chalkboard-user"></i><span><small>Tutor</small><strong><?= e($project['tutor'] ?: 'Por asignar') ?></strong></span><em><?= e($project['period']) ?></em></div>
                    <dl class="project-card-context"><?php foreach ($phaseContext as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e((string) $value) ?></dd></div><?php endforeach; ?></dl>
                    <div class="project-card-activity"><span><i class="fa-regular fa-clock"></i> Última actividad</span><strong><?= e($project['last_activity']) ?></strong></div>
                    <footer><a href="<?= e($actionUrl) ?>"><?= e($cardAction) ?> <i class="fa-solid fa-arrow-right"></i></a></footer>
                </article>
            <?php endforeach; ?>
        </section>
        <nav class="projects-pagination" data-project-pagination aria-label="Paginación de proyectos"></nav>
        <section class="projects-empty" data-project-empty hidden><span><i class="fa-solid fa-filter-circle-xmark"></i></span><h2>No encontramos proyectos</h2><p>Prueba con otra búsqueda o limpia los filtros aplicados.</p><button type="button" data-project-clear>Limpiar búsqueda y filtros</button></section>
    <?php endif; ?>
</div>
