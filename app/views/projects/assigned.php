<?php
/** @var array<int,array<string,mixed>> $assignedProjects */
/** @var list<array{value:string,label:string}> $assignedProjectTypes */
/** @var list<array{value:string,label:string}> $assignedProjectPeriods */
/** @var list<array{value:string,label:string}> $assignedProjectRelations */
$courseCount = count(array_filter($assignedProjects, static fn(array $project): bool => $project['tab'] === 'course'));
$completedCount = count($assignedProjects) - $courseCount;
$typeLabels = ['thesis'=>'TIT','thesis_profile'=>'PFT','practice'=>'PRA','pis'=>'Proyecto PIS','community'=>'VIN'];
$visibleTypeLabel = static function (string $label): string {
    return mb_strtolower(trim($label), 'UTF-8') === 'proyecto integrador de saberes' ? 'Proyecto PIS' : $label;
};
$hasAssignedProjects = $assignedProjects !== [];
?>
<div class="assigned-projects-page__content">
    <div class="ar-page assigned-projects-shell" id="assignedProjectsPage">
        <header class="ar-head">
            <div>
                <span class="ar-eyebrow">Seguimiento académico</span>
                <h1>Proyectos asignados</h1>
                <p>Proyectos académicos en los que participas como docente.</p>
            </div>
        </header>

        <main class="ar-catalog assigned-projects-catalog">
            <nav class="ar-tabs" role="tablist" aria-label="Estado de proyectos asignados">
                <button type="button" class="active" id="assignedTabCourse" role="tab" aria-selected="true" aria-controls="assignedPanelCourse">
                    <i class="fa-solid fa-spinner"></i><span class="ar-tab-label">En curso</span><span class="ar-tab-count" id="assignedBadgeCourse"><?= $courseCount ?></span>
                </button>
                <?php if ($completedCount >= 1): ?>
                <button type="button" id="assignedTabCompleted" role="tab" aria-selected="false" aria-controls="assignedPanelCompleted">
                    <i class="fa-solid fa-circle-check"></i><span class="ar-tab-label">Finalizados</span><span class="ar-tab-count" id="assignedBadgeCompleted"><?= $completedCount ?></span>
                </button>
                <?php endif; ?>
            </nav>

            <?php if ($hasAssignedProjects): ?><div class="ar-tools assigned-projects-tools">
                <label class="ar-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input id="assignedSearch" type="search" role="searchbox" placeholder="Buscar por código, título o estudiante" autocomplete="off">
                    <button id="assignedClearSearch" type="button" aria-label="Limpiar búsqueda" hidden><i class="fa-solid fa-xmark"></i></button>
                </label>
                <?php if (count($assignedProjectTypes) === 1): $type = $assignedProjectTypes[0]; ?><div class="ar-filter-control ar-fixed-filter"><span>Tipo</span><div><i class="fa-solid fa-diagram-project" aria-hidden="true"></i><strong><?= e($visibleTypeLabel((string) $type['label'])) ?></strong></div><input id="assignedType" type="hidden" value="<?= e($type['value']) ?>"></div><?php elseif (count($assignedProjectTypes) > 1): ?><label class="ar-filter-control"><span>Tipo</span><select id="assignedType"><option value="all">Todos</option><?php foreach ($assignedProjectTypes as $type): ?><option value="<?= e($type['value']) ?>"><?= e($visibleTypeLabel((string) $type['label'])) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <?php if (count($assignedProjectPeriods) === 1): $period = $assignedProjectPeriods[0]; ?><div class="ar-filter-control ar-fixed-filter"><span>Período académico</span><div><i class="fa-regular fa-calendar" aria-hidden="true"></i><strong><?= e($period['label']) ?></strong></div><input id="assignedPeriod" type="hidden" value="<?= e($period['value']) ?>"></div><?php else: ?><label class="ar-filter-control"><span>Período académico</span><select id="assignedPeriod"><option value="all">Todos</option><?php foreach ($assignedProjectPeriods as $period): ?><option value="<?= e($period['value']) ?>"><?= e($period['label']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <?php if (count($assignedProjectRelations) === 1): $relation = $assignedProjectRelations[0]; ?><div class="ar-filter-control ar-fixed-filter"><span>Relación</span><div><i class="fa-solid fa-user-tie" aria-hidden="true"></i><strong><?= e($relation['label']) ?></strong></div><input id="assignedRelation" type="hidden" value="<?= e($relation['value']) ?>"></div><?php elseif (count($assignedProjectRelations) > 1): ?><label class="ar-filter-control"><span>Relación</span><select id="assignedRelation"><option value="all">Todos</option><?php foreach ($assignedProjectRelations as $relation): ?><option value="<?= e($relation['value']) ?>"><?= e($relation['label']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
            </div><?php endif; ?>

            <?php foreach (['course'=>['title'=>'Proyectos en curso','count'=>$courseCount,'emptyTitle'=>'No tienes proyectos asignados en curso.','emptyText'=>'Cuando participes como tutor, cotutor o tribunal en un proyecto activo, aparecerá aquí.'], 'completed'=>['title'=>'Proyectos finalizados','count'=>$completedCount,'emptyTitle'=>'No tienes proyectos finalizados todavía.','emptyText'=>'Los proyectos asignados que completen su proceso académico aparecerán aquí.']] as $tab => $section): ?>
                <section class="ar-panel assigned-projects-panel" id="assignedPanel<?= ucfirst($tab) ?>" role="tabpanel" aria-labelledby="assignedTab<?= ucfirst($tab) ?>" <?= $tab === 'completed' ? 'hidden' : '' ?>>
                    <header class="ar-section-head"><div><span>Seguimiento docente</span><h2><?= e($section['title']) ?></h2></div><p><strong data-assigned-count="<?= e($tab) ?>"><?= $section['count'] ?></strong> resultados visibles</p></header>
                    <div class="ar-grid assigned-projects-grid" data-assigned-grid="<?= e($tab) ?>">
                        <?php foreach ($assignedProjects as $project): if ($project['tab'] !== $tab) continue; ?>
                            <?php $relationCodes = array_column((array) $project['relationships'], 'code'); $relationLabels = array_column((array) $project['relationships'], 'label'); ?>
                            <article class="ar-project-card assigned-project-card" data-assigned-card data-tab="<?= e($tab) ?>" data-type-code="<?= e((string)$project['type_code']) ?>" data-period-id="<?= e((string)$project['period_id']) ?>" data-relations="<?= e(implode(' ', $relationCodes)) ?>" data-search="<?= e(mb_strtolower(implode(' ', [$project['code'],$project['title'],$project['students_search'],$project['status'],$project['period'],implode(' ', $relationLabels)]), 'UTF-8')) ?>">
                                <header><span class="ar-code" data-assigned-searchable><?= e((string)$project['code']) ?></span><span class="ar-project-type"><?= e($typeLabels[(string)$project['type_code']] ?? (string)$project['type']) ?></span></header>
                                <div class="ar-card-copy"><h3 data-assigned-searchable title="<?= e((string)$project['title']) ?>"><?= e((string)$project['title']) ?></h3><dl>
                                    <div><dt><i class="fa-solid fa-users" aria-hidden="true"></i> Estudiantes</dt><dd data-assigned-searchable><?= e((string)$project['students']) ?></dd></div>
                                    <div><dt><i class="fa-solid fa-user-tie" aria-hidden="true"></i> Relación</dt><dd class="assigned-relation-list"><?php foreach ((array) $project['relationships'] as $relationship): ?><span class="assigned-relation-chip"><?= e((string) $relationship['label']) ?></span><?php endforeach; ?></dd></div>
                                    <div><dt><i class="fa-solid fa-circle-dot" aria-hidden="true"></i> Estado</dt><dd><span class="assigned-status is-<?= e((string)$project['status_key']) ?>"><?= e((string)$project['status']) ?></span></dd></div>
                                    <?php if (!empty($project['teacher_situation'])): ?><div><dt><i class="fa-solid fa-circle" aria-hidden="true"></i> Situación</dt><dd><span class="assigned-teacher-situation<?= !empty($project['teacher_situation_requires_attention']) ? ' requires-attention' : '' ?>"><i class="fa-solid fa-circle" aria-hidden="true"></i><span class="assigned-teacher-situation-text"><?= e((string)$project['teacher_situation']) ?></span></span></dd></div><?php endif; ?>
                                    <div><dt><i class="fa-regular fa-calendar" aria-hidden="true"></i> Período</dt><dd><?= e((string)$project['period']) ?></dd></div>
                                </dl></div>
                                <?php $package = (array)($project['package'] ?? []); ?><footer class="assigned-project-actions"><a class="ar-primary-action" href="<?= e(route('project-detail') . '&id=' . (int)$project['id']) ?>"><i class="fa-regular fa-eye" aria-hidden="true"></i> Ver proyecto</a><?php if (!empty($package['available']) && !empty($package['download_url'])): ?><a class="ar-icon-action" href="<?= e((string)$package['download_url']) ?>" data-tooltip="Descargar ZIP<?= !empty($package['size']) ? ' · ' . e((string)$package['size']) : '' ?>" aria-label="Descargar paquete ZIP del proyecto"><i class="fa-solid fa-download" aria-hidden="true"></i></a><?php endif; ?></footer>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="ar-empty" data-assigned-empty="<?= e($tab) ?>" data-default-title="<?= e($section['emptyTitle']) ?>" data-default-text="<?= e($section['emptyText']) ?>" hidden><span><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span><h2><?= e($section['emptyTitle']) ?></h2><p><?= e($section['emptyText']) ?></p></div>
                    <footer class="ar-pagination assigned-projects-pagination" data-assigned-pagination="<?= e($tab) ?>" hidden><span data-assigned-summary="<?= e($tab) ?>">Mostrando 0 de 0</span><label>Mostrar <select data-assigned-size="<?= e($tab) ?>"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="75">75</option><option value="100">100</option></select></label><nav data-assigned-pages="<?= e($tab) ?>" aria-label="Paginación de <?= e(mb_strtolower($section['title'], 'UTF-8')) ?>"></nav></footer>
                </section>
            <?php endforeach; ?>
        </main>
    </div>
</div>
