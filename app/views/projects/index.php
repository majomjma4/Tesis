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
        <div class="projects-section" aria-labelledby="projects-<?= e(strtolower(str_replace(' ', '-', $group['title']))) ?>">
            <header class="projects-section-heading"><div><h2 id="projects-<?= e(strtolower(str_replace(' ', '-', $group['title']))) ?>"><?= e($group['title']) ?></h2><p><?= e($group['description']) ?></p></div></header>
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
        <div class="projects-publish-modal" data-project-publish-modal hidden>
            <section class="projects-publish-dialog" role="dialog" aria-modal="true" aria-labelledby="studentPublishTitle" aria-describedby="studentPublishMessage" tabindex="-1">
                <header class="projects-publish-header"><div><span>Repositorio Académico</span><h2 id="studentPublishTitle">Publicar proyecto</h2><p id="studentPublishMessage">Revisa los archivos que formarán parte de la publicación definitiva en el Repositorio Académico.</p></div><button type="button" class="projects-publish-close" data-project-publish-close aria-label="Cerrar publicación"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
                <p class="sr-only" aria-hidden="true">Este proyecto está listo para incorporarse al Repositorio Académico.</p>
                <div class="projects-publish-body" data-project-publish-ui>
                    <section class="projects-publish-available"><div><strong>Archivos aprobados disponibles</strong><b data-publish-ui-count>0 archivos</b></div><ul data-publish-ui-preview></ul><button type="button" class="projects-publish-text-action" data-publish-ui-toggle-files hidden>Ver lista completa</button></section>
                    <section data-publish-ui-choice><p class="projects-publish-question">¿Deseas mantener estos archivos o actualizar alguno antes de publicar?</p><div class="projects-publish-options" role="group" aria-label="Cómo preparar los archivos"><button type="button" class="projects-publish-option is-selected" data-publish-ui-keep aria-pressed="true"><i class="fa-solid fa-file-circle-check" aria-hidden="true"></i><span><strong>Mantener archivos actuales</strong><small>Publica los archivos aprobados sin realizar cambios.</small></span></button><button type="button" class="projects-publish-option" data-publish-ui-update aria-pressed="false"><i class="fa-solid fa-file-pen" aria-hidden="true"></i><span><strong>Actualizar archivos</strong><small>Agrega, reemplaza o quita archivos antes de publicar.</small></span></button></div></section>
                    <section class="projects-publish-manage" data-publish-ui-manage hidden><div class="projects-publish-section-head"><div><h3>Archivos de la publicación</h3><p>Agrega, reemplaza o quita archivos. Las versiones anteriores permanecerán en el historial.</p></div><strong data-publish-ui-live-count>0 archivos se publicarán</strong></div><ul data-publish-ui-files></ul><div class="projects-publish-excluded" data-publish-ui-excluded hidden></div><div class="projects-publish-add"><input type="file" data-publish-ui-add-file aria-label="Archivos para agregar a la publicación" multiple><button type="button" data-publish-ui-add><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar archivos</button></div><p class="projects-publish-error" data-publish-ui-empty role="alert" hidden>Debes incluir al menos un archivo para publicar el proyecto.</p></section>
                    <section class="projects-publish-summary" data-publish-ui-summary hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><h3>Publicación lista</h3><p data-publish-ui-summary-count></p></div><ul data-publish-ui-summary-files></ul><button type="button" class="projects-publish-text-action" data-publish-ui-summary-toggle>Ver archivos incluidos</button><p class="projects-publish-warning">Al confirmar, los archivos seleccionados pasarán a formar parte de la publicación definitiva del proyecto.</p></section>
                    <section class="projects-publish-remove-confirm" data-publish-ui-remove-confirm hidden role="alertdialog" aria-labelledby="publishRemoveTitle"><h3 id="publishRemoveTitle">Quitar de la publicación</h3><p>Este archivo no formará parte de la publicación final. Su versión anterior permanecerá guardada en el historial.</p><div><button type="button" data-publish-ui-remove-cancel>Cancelar</button><button type="button" data-publish-ui-remove-confirm>Quitar</button></div></section><p class="projects-publish-error" data-publish-ui-error role="alert" hidden></p>
                    <section class="projects-publish-final-confirm" data-publish-ui-final-confirm hidden role="alertdialog" aria-labelledby="publishFinalTitle" aria-describedby="publishFinalText"><h3 id="publishFinalTitle">Confirmar publicación</h3><p id="publishFinalText" data-publish-ui-final-text>Se publicarán 0 archivos en el Repositorio Académico.</p><p>Al confirmar, el proyecto pasará a estado Publicado y estará disponible en el Repositorio Académico.</p><div><button type="button" data-publish-ui-final-cancel>Cancelar</button><button type="button" data-publish-ui-final-accept>Sí, publicar</button></div></section>
                </div>
                <footer class="projects-publish-footer"><button type="button" data-publish-ui-cancel>Cancelar</button><button type="button" data-publish-ui-back hidden>Volver</button><button type="button" class="projects-publish-next" data-publish-ui-next>Continuar</button><button type="button" class="projects-publish-confirm" data-publish-ui-confirm hidden>Publicar proyecto</button></footer>
            </section>
        </div>
        <div data-student-project-publish-config data-endpoint="<?= e((string) ($studentProjectPublishEndpoint ?? '')) ?>" data-csrf="<?= e((string) ($studentProjectPublishCsrf ?? '')) ?>"></div>
    <?php endif; ?>
</div>
