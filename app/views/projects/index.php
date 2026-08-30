<div class="projects-page" data-projects-page>
    <header class="projects-heading">
        <div><span class="projects-eyebrow">Gestión académica</span><h1>Mis proyectos</h1><p>Consulta, organiza y continúa el seguimiento de tus expedientes académicos.</p></div>
    </header>

    <?php if (($projectsStatus ?? 'error') === 'error'): ?>
        <section class="projects-empty projects-empty-primary"><span><i class="fa-solid fa-triangle-exclamation"></i></span><h2>No fue posible cargar tus proyectos</h2><p><?= e((string) ($projectsMessage ?? 'Inténtalo nuevamente más tarde.')) ?></p></section>
    <?php elseif ($projects === []): ?>
        <section class="projects-empty projects-empty-primary">
            <span><i class="fa-regular fa-folder-open"></i></span><h2>Aún no tienes proyectos</h2>
            <p>Cuando registres tu primer proyecto académico, su seguimiento aparecerá aquí.</p>
            <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; margin-top: 1rem;">
                <?php if (!empty($canCreateStudentProject)): ?><a class="projects-empty-action-secondary" href="<?= e((string) ($newStudentProjectUrl ?? route('new-project'))) ?>"><i class="fa-solid fa-plus"></i> Subir proyecto</a><?php endif; ?>
                <?php if (false): ?>
                    <a href="<?= e(route('project-detail')) ?>&demo=1" style="background: var(--surface-soft, #f1f5f9); color: var(--text, #334155); border: 1px solid var(--line, #cbd5e1); border-radius: 8px; padding: 0.55rem 1rem; text-decoration: none; font-weight: 700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-vial-circle-check" style="color: #0284c7;"></i> Ver proyecto de demostración (Simulación visual)
                    </a>
                <?php endif; ?>
            </div>
        </section>
    <?php else:
        $groups = (array) ($projectGroups ?? []);
        $activeGroupKey = count($groups) === 1 ? (string) ($groups[0]['key'] ?? 'development') : 'development';
        ?>
        <nav class="ar-tabs projects-tabs" role="tablist" aria-label="Secciones de tus proyectos">
            <?php foreach ($groups as $groupIndex => $group): $groupKey = (string) ($group['key'] ?? 'development'); $isActiveGroup = $groupKey === $activeGroupKey; ?>
                <button type="button" class="<?= $isActiveGroup ? 'active' : '' ?>" id="projects-tab-<?= e($groupKey) ?>" role="tab" aria-selected="<?= $isActiveGroup ? 'true' : 'false' ?>" aria-controls="projects-panel-<?= e($groupKey) ?>" tabindex="<?= $isActiveGroup ? '0' : '-1' ?>" data-project-tab="<?= e($groupKey) ?>">
                    <span class="ar-tab-label"><?= e($group['title']) ?></span><span class="ar-tab-count"><?= count($group['items']) ?></span>
                </button>
            <?php endforeach; ?>
        </nav>
        <?php foreach ($groups as $groupIndex => $group): $groupKey = (string) ($group['key'] ?? 'development'); ?>
        <section class="projects-project-panel" id="projects-panel-<?= e($groupKey) ?>" role="tabpanel" aria-labelledby="projects-tab-<?= e($groupKey) ?>" data-project-panel="<?= e($groupKey) ?>"<?= $groupKey === $activeGroupKey ? '' : ' hidden' ?>>
        <div class="projects-section" aria-labelledby="projects-tab-<?= e($groupKey) ?>">
            <?php if ($group['items'] === []): ?><p class="projects-section-empty"><?= e($group['empty']) ?></p>
            <?php else: ?><div class="projects-grid" aria-label="<?= e($group['title']) ?>">
            <?php foreach ($group['items'] as $project):
                $search = mb_strtolower(implode(' ', array_merge([$project['title'] ?? '', $project['subtitle'] ?? '', $project['type'] ?? '', $project['tutor'] ?? '', $project['period'] ?? '', $project['career'] ?? '', $project['publisher'] ?? ''], (array) ($project['tags'] ?? []), (array) ($project['technologies'] ?? []))), 'UTF-8');
                /* Legacy state presentation removed; navigation is resolved by the controller. */
                /*
                $actionUrl = route('project-detail') . '&id=' . (int) $project['id'];
                $cardAction = 'Abrir proyecto';
                $phaseContext = [];
                if (false) {
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
                }
                */
                $navigation = (array) ($project['navigation'] ?? []);
                $actionUrl = (string) ($navigation['action_url'] ?? (route('project-detail') . '&id=' . (int) $project['id']));
                $cardAction = (string) ($navigation['action_label'] ?? 'Ver proyecto');
                $phaseContext = (array) ($navigation['phase_context'] ?? []);
                $projectTypeLabel = (string) ($project['type'] ?? 'Proyecto academico');
                if (str_contains(strtolower($projectTypeLabel), 'integrador') || in_array(strtolower((string) ($project['type_key'] ?? '')), ['pis', 'integrator'], true)) {
                    $projectTypeLabel = 'Proyecto PIS';
                }
            ?>
                <?php $hasPendingReview=!empty($project['review_situation']['has_pending_observations']); ?>
                <article class="projects-card" data-project-card data-search="<?= e($search) ?>" data-status="<?= e($project['status_key']) ?>" data-situation="<?=$hasPendingReview?'pending':'none'?>" data-type="<?= e($project['type_key']) ?>" data-period="<?= e($project['period']) ?>" data-title="<?= e($project['title']) ?>" data-activity="<?= (int) ($project['activity_order'] ?? 0) ?>">
                    <header><span class="projects-type"><?= e($projectTypeLabel) ?></span><span class="projects-status is-<?= e($project['status_key']) ?>"><?= e($project['status']) ?></span></header>
                    <?php if (!empty($project['is_direct_repository'])): ?><p class="projects-direct-origin"><i class="fa-solid fa-book-open" aria-hidden="true"></i> Publicado directamente en el repositorio<?php if (!empty($project['publisher'])): ?> · Publicado por: <?= e((string) $project['publisher']) ?><?php endif; ?></p><?php endif; ?>
                    <?php if($hasPendingReview):?><span class="projects-review-situation"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Observaciones pendientes</span><?php endif;?>
                    <div class="projects-card-title"><h2><?= e($project['title']) ?></h2></div>
                    <div class="project-card-tutor"><i class="fa-solid fa-chalkboard-user"></i><span><small>Tutor</small><strong><?= e($project['tutor'] ?: 'Por asignar') ?></strong></span><em><?= e($project['period']) ?></em></div>
                    <dl class="project-card-context"><?php foreach ($phaseContext as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e((string) $value) ?></dd></div><?php endforeach; ?></dl>
                    <div class="project-card-activity"><span><i class="fa-regular fa-clock"></i> Última actividad</span><strong><?= e($project['last_activity']) ?></strong></div>
                    <footer><a href="<?= e($actionUrl) ?>"><?= e($cardAction) ?> <i class="fa-solid fa-arrow-right"></i></a><?php if (!empty($navigation['can_publish'])): ?><button type="button" class="projects-publish-action" data-project-publish data-project-id="<?= (int) $project['id'] ?>"><i class="fa-solid fa-upload" aria-hidden="true"></i>Publicar</button><?php endif; ?></footer>
                </article>
            <?php endforeach; ?>
            </div><?php endif; ?>
        </div></section>
        <?php endforeach; ?>
        <?php require __DIR__ . '/_student-project-publish.php'; ?>
    <?php endif; ?>
</div>
