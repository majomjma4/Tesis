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
    <?php else:
        $isFinished = static fn(array $project): bool => (string)($project['status_key'] ?? '') === 'published';
        $groups = [
            ['title' => 'En desarrollo', 'description' => 'Proyectos que todavía se encuentran en seguimiento o tienen acciones pendientes.', 'items' => array_values(array_filter($projects, fn(array $project): bool => !$isFinished($project))), 'empty' => 'No tienes proyectos en seguimiento actualmente.'],
            ['title' => 'Terminados', 'description' => 'Proyectos que ya finalizaron su proceso de seguimiento.', 'items' => array_values(array_filter($projects, $isFinished)), 'empty' => 'Todavía no tienes proyectos terminados.'],
        ];
        ?>
        <nav class="ar-tabs projects-tabs" role="tablist" aria-label="Secciones de tus proyectos">
            <?php foreach ($groups as $groupIndex => $group): $groupKey = $groupIndex === 0 ? 'development' : 'finished'; ?>
                <button type="button" class="<?= $groupIndex === 0 ? 'active' : '' ?>" id="projects-tab-<?= e($groupKey) ?>" role="tab" aria-selected="<?= $groupIndex === 0 ? 'true' : 'false' ?>" aria-controls="projects-panel-<?= e($groupKey) ?>" tabindex="<?= $groupIndex === 0 ? '0' : '-1' ?>" data-project-tab="<?= e($groupKey) ?>">
                    <span class="ar-tab-label"><?= e($group['title']) ?></span><span class="ar-tab-count"><?= count($group['items']) ?></span>
                </button>
            <?php endforeach; ?>
        </nav>
        <?php foreach ($groups as $groupIndex => $group): $groupKey = $groupIndex === 0 ? 'development' : 'finished'; ?>
        <section class="projects-project-panel" id="projects-panel-<?= e($groupKey) ?>" role="tabpanel" aria-labelledby="projects-tab-<?= e($groupKey) ?>" data-project-panel="<?= e($groupKey) ?>"<?= $groupIndex === 0 ? '' : ' hidden' ?>>
        <div class="projects-section" aria-labelledby="projects-<?= e(strtolower(str_replace(' ', '-', $group['title']))) ?>">
            <header class="projects-section-heading"><div><h2 id="projects-<?= e(strtolower(str_replace(' ', '-', $group['title']))) ?>"><?= e($group['title']) ?></h2><p><?= e($group['description']) ?></p></div></header>
            <?php if ($group['items'] === []): ?><p class="projects-section-empty"><?= e($group['empty']) ?></p>
            <?php else: ?><div class="projects-grid" aria-label="<?= e($group['title']) ?>">
            <?php foreach ($group['items'] as $project):
                $search = mb_strtolower(implode(' ', array_merge([$project['title'] ?? '', $project['subtitle'] ?? '', $project['type'] ?? '', $project['tutor'] ?? '', $project['period'] ?? '', $project['career'] ?? ''], (array) ($project['tags'] ?? []), (array) ($project['technologies'] ?? []))), 'UTF-8');
                $actionUrl = route('project-detail') . '&id=' . (int) $project['id'];
                $cardAction = 'Abrir proyecto';
                $phaseContext = [];
                if ($project['status_key'] === 'under_review') {
                    $phaseContext = ['Última entrega' => $project['latest_delivery']['version'] ?? 'Sin entregas', 'Observaciones pendientes' => count($project['observations'] ?? [])];
                    $cardAction = 'Atender revisión'; $actionUrl .= '&tab=review';
                } elseif ($project['status_key'] === 'approved') {
                    $phaseContext = ['Aprobación' => $project['key_dates'][1]['value'] ?? 'Registrada', 'Documentos finales' => !empty($project['final_documents']) ? 'Disponibles' : 'Pendientes'];
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
                <article class="projects-card" data-project-card data-search="<?= e($search) ?>" data-status="<?= e($project['status_key']) ?>" data-situation="<?=$hasPendingReview?'pending':'none'?>" data-type="<?= e($project['type_key']) ?>" data-period="<?= e($project['period']) ?>" data-metric="<?= e($project['metric_bucket']) ?>" data-title="<?= e($project['title']) ?>" data-progress="<?= (int) ($project['progress'] ?? 0) ?>" data-activity="<?= (int) ($project['activity_order'] ?? 0) ?>">
                    <header><span class="projects-type"><?= e($project['type']) ?></span><span class="projects-status is-<?= e($project['status_key']) ?>"><?= e($project['status']) ?></span></header>
                    <?php if($hasPendingReview):?><span class="projects-review-situation"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Observaciones pendientes</span><?php endif;?>
                    <div class="projects-card-title"><h2><?= e($project['title']) ?></h2></div>
                    <div class="project-card-tutor"><i class="fa-solid fa-chalkboard-user"></i><span><small>Tutor</small><strong><?= e($project['tutor'] ?: 'Por asignar') ?></strong></span><em><?= e($project['period']) ?></em></div>
                    <dl class="project-card-context"><?php foreach ($phaseContext as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e((string) $value) ?></dd></div><?php endforeach; ?></dl>
                    <div class="project-card-activity"><span><i class="fa-regular fa-clock"></i> Última actividad</span><strong><?= e($project['last_activity']) ?></strong></div>
                    <footer><a href="<?= e($actionUrl) ?>"><?= e($cardAction) ?> <i class="fa-solid fa-arrow-right"></i></a><?php if (($project['status_key'] ?? '') === 'approved' && ($project['type_key'] ?? '') !== 'thesis'): ?><button type="button" class="projects-publish-action" data-publish-demo><i class="fa-solid fa-upload" aria-hidden="true"></i>Publicar</button><?php endif; ?></footer>
                </article>
            <?php endforeach; ?>
            </div><?php endif; ?>
        </div></section>
        <?php endforeach; ?><p class="projects-demo-notice" data-publish-demo-notice role="status" aria-live="polite" hidden>La publicación definitiva se implementará en la siguiente fase.</p>
    <?php endif; ?>
</div>
