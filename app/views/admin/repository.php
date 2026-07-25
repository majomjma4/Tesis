<?php
$publishedProjects = array_values(array_filter(
    $repositoryProjects,
    static fn (array $project): bool => $project['status'] === 'published'
));
$materialCategoryLabels = array_values(array_unique(array_column($supportMaterials, 'category_label')));
$withdrawnCount = count($withdrawnPublications) + count($withdrawnMaterials);
$formatDate = static function (?string $date): string {
    if (!$date) return 'Sin fecha registrada';
    return (new DateTimeImmutable($date))->format('d/m/Y');
};
?>

<div class="ar-page" id="arPage">
    <header class="ar-head">
        <div>
            <span class="ar-eyebrow">Biblioteca digital institucional</span>
            <h1>Repositorio institucional</h1>
            <p>Administra el contenido publicado que estará disponible para estudiantes y docentes.</p>
        </div>
        <?php if ($withdrawnCount > 0): ?>
            <button class="ar-withdrawn-button" id="arOpenWithdrawn" type="button">
                <i class="fa-solid fa-eye-slash"></i>
                <span>Publicaciones retiradas</span>
                <strong><?= $withdrawnCount ?></strong>
            </button>
        <?php endif; ?>
    </header>

    <?php if ($repositoryError): ?>
        <p class="ar-error" role="alert"><?= e($repositoryError) ?></p>
    <?php endif; ?>

    <section class="ar-stats" aria-label="Resumen del repositorio">
        <article class="ar-stat ar-stat-projects">
            <span class="ar-stat-icon"><i class="fa-solid fa-book-open"></i></span>
            <div><strong><?= (int) $repositorySummary['published'] ?></strong><span>Proyectos publicados</span></div>
        </article>
        <article class="ar-stat ar-stat-materials">
            <span class="ar-stat-icon"><i class="fa-solid fa-file-lines"></i></span>
            <div><strong><?= count($supportMaterials) ?></strong><span>Materiales de apoyo</span></div>
        </article>
        <article class="ar-stat ar-stat-pending">
            <span class="ar-stat-icon"><i class="fa-regular fa-clock"></i></span>
            <div><strong><?= (int) $repositorySummary['eligible'] + (int) $repositorySummary['incomplete'] ?></strong><span>Pendientes de publicación</span></div>
        </article>
    </section>

    <section class="ar-catalog">
        <nav class="ar-tabs" aria-label="Contenido del repositorio">
            <button class="active" type="button" data-repository-tab="projects" aria-selected="true">
                <i class="fa-solid fa-diagram-project"></i>
                <span class="ar-tab-label">Proyectos publicados</span>
                <span class="ar-tab-count"><?= (int) $repositorySummary['published'] ?></span>
            </button>
            <button type="button" data-repository-tab="materials" aria-selected="false">
                <i class="fa-solid fa-folder-open"></i>
                <span class="ar-tab-label">Material de apoyo</span>
                <span class="ar-tab-count"><?= count($supportMaterials) ?></span>
            </button>
        </nav>

        <div class="ar-tools">
            <label class="ar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="arSearch" type="search" placeholder="Buscar en el repositorio..." autocomplete="off">
                <button id="arClearSearch" type="button" aria-label="Limpiar búsqueda" hidden>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </label>
            <label class="ar-filter-control" data-filter-for="projects">
                <span>Tipo</span>
                <select id="arTypeFilter">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($repositoryCatalogs['types'] as $type): ?>
                        <option value="<?= e(mb_strtolower($type['name'], 'UTF-8')) ?>"><?= e($type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (count($repositoryCatalogs['periods']) === 1): ?>
                <div class="ar-filter-control ar-fixed-filter" data-filter-for="projects">
                    <span>Período académico</span>
                    <div><i class="fa-regular fa-calendar"></i><strong><?= e($repositoryCatalogs['periods'][0]['name']) ?></strong></div>
                    <input id="arPeriodFilter" type="hidden" value="<?= e(mb_strtolower($repositoryCatalogs['periods'][0]['name'], 'UTF-8')) ?>" data-fixed-filter>
                </div>
            <?php else: ?>
                <label class="ar-filter-control" data-filter-for="projects">
                    <span>Período académico</span>
                    <select id="arPeriodFilter">
                        <option value="">Todos los períodos</option>
                        <?php foreach ($repositoryCatalogs['periods'] as $period): ?>
                            <option value="<?= e(mb_strtolower($period['name'], 'UTF-8')) ?>"><?= e($period['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label class="ar-filter-control" data-filter-for="materials" hidden>
                <span>Categoría</span>
                <select id="arCategoryFilter">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($materialCategoryLabels as $category): ?>
                        <option value="<?= e(mb_strtolower($category, 'UTF-8')) ?>"><?= e($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <section class="ar-panel" data-repository-panel="projects">
            <header class="ar-section-head">
                <div><span>Catálogo institucional</span><h2>Proyectos publicados</h2></div>
                <p><strong id="arProjectCount"><?= count($publishedProjects) ?></strong> resultados visibles</p>
            </header>

            <div class="ar-grid" id="arProjectGrid">
                <?php foreach ($publishedProjects as $project): ?>
                    <?php $projectSearch = implode(' ', [
                        $project['code'], $project['type_name'], $project['title'],
                        $project['authors'] ?: '', $project['tutor_name'] ?: '',
                        $project['period_name'],
                    ]); ?>
                    <article class="ar-project-card"
                        data-repository-item="projects"
                        data-type-code="<?= e($project['type_code']) ?>"
                        data-search="<?= e(mb_strtolower($projectSearch, 'UTF-8')) ?>"
                        data-type="<?= e(mb_strtolower($project['type_name'], 'UTF-8')) ?>"
                        data-period="<?= e(mb_strtolower($project['period_name'], 'UTF-8')) ?>">
                        <header>
                            <span class="ar-code"><?= e($project['code']) ?></span>
                            <span class="ar-project-type"><?= e($project['type_name']) ?></span>
                        </header>
                        <div class="ar-card-copy">
                            <h3 title="<?= e($project['title']) ?>"><?= e($project['title']) ?></h3>
                            <dl>
                                <div><dt><i class="fa-solid fa-users"></i> Autores</dt><dd><?= e($project['authors'] ?: 'Sin autores registrados') ?></dd></div>
                                <div><dt><i class="fa-solid fa-user-tie"></i> Tutor</dt><dd><?= e($project['tutor_name'] ?: 'Sin asignar') ?></dd></div>
                                <div><dt><i class="fa-regular fa-calendar"></i> Período</dt><dd><?= e($project['period_name']) ?></dd></div>
                            </dl>
                        </div>
                        <div class="ar-card-meta">
                            <span><i class="fa-regular fa-file-lines"></i> <?= (int) $project['file_count'] ?> documentos</span>
                            <span><i class="fa-solid fa-globe"></i> <?= e($formatDate($project['published_at'])) ?></span>
                        </div>
                        <footer>
                            <a class="ar-primary-action" href="<?= e(route('project-detail') . '&id=' . (int) $project['id'] . '&tab=information') ?>">
                                <i class="fa-solid fa-diagram-project"></i> Abrir expediente
                            </a>
                            <button class="ar-icon-action ar-danger-action" data-publish="unpublish" data-id="<?= (int) $project['id'] ?>" data-tooltip="Retirar del repositorio" type="button" aria-label="Retirar del repositorio">
                                <i class="fa-solid fa-eye-slash"></i>
                            </button>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ar-empty" id="arProjectsEmpty" <?= $publishedProjects ? 'hidden' : '' ?>>
                <span><i class="fa-solid fa-book-open"></i></span>
                <h2>Aún no existen proyectos publicados.</h2>
                <p>Los proyectos aprobados aparecerán aquí después de completar su publicación.</p>
            </div>
            <footer class="ar-pagination" data-pagination-for="projects" hidden>
                <span data-pagination-summary>Mostrando 0 de 0</span>
                <label>Mostrar
                    <select data-page-size>
                        <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="75">75</option><option value="100">100</option>
                    </select>
                </label>
                <nav data-pagination-pages aria-label="Paginación de proyectos publicados"></nav>
            </footer>
        </section>

        <section class="ar-panel" data-repository-panel="materials" hidden>
            <header class="ar-section-head">
                <div><span>Recursos académicos</span><h2>Material de apoyo</h2></div>
                <p><strong id="arMaterialCount"><?= count($supportMaterials) ?></strong> resultados visibles</p>
            </header>

            <div class="ar-grid ar-material-grid" id="arMaterialGrid">
                <?php foreach ($supportMaterials as $material): ?>
                    <?php $materialSearch = implode(' ', [
                        $material['title'], $material['category_label'], $material['description'],
                        $material['type'], $material['publisher'] ?? '', implode(' ', $material['keywords']),
                    ]); ?>
                    <article class="ar-material-card"
                        data-repository-item="materials"
                        data-category-slug="<?= e($material['category_slug']) ?>"
                        data-search="<?= e(mb_strtolower($materialSearch, 'UTF-8')) ?>"
                        data-category="<?= e(mb_strtolower($material['category_label'], 'UTF-8')) ?>"
                        data-period="<?= e(mb_strtolower($material['pao_label'], 'UTF-8')) ?>">
                        <header>
                            <span class="ar-material-icon"><i class="fa-regular fa-file-lines"></i></span>
                            <div><span><?= e($material['type']) ?></span><strong><?= e($material['category_label']) ?></strong></div>
                            <span class="ar-available"><?= e($material['status']) ?></span>
                        </header>
                        <div class="ar-card-copy">
                            <h3 title="<?= e($material['title']) ?>"><?= e($material['title']) ?></h3>
                            <p><?= e($material['description']) ?></p>
                        </div>
                        <div class="ar-card-meta">
                            <span><i class="fa-regular fa-calendar"></i> <?= e($material['publication_date']) ?></span>
                            <span><i class="fa-solid fa-download"></i> <?= number_format((int) $material['downloads'], 0, ',', '.') ?> descargas</span>
                        </div>
                        <footer>
                            <a class="ar-primary-action" href="<?= e(route('support-material-detail') . '&id=' . (int) $material['id']) ?>">
                                <i class="fa-regular fa-eye"></i> Ver
                            </a>
                            <button class="ar-icon-action ar-danger-action" type="button" data-withdraw-material aria-label="Retirar material" data-tooltip="Retirar del repositorio">
                                <i class="fa-solid fa-eye-slash"></i>
                            </button>
                        </footer>
                        <script type="application/json" data-material-json><?= json_encode([
                            'id'=>$material['id'],'title'=>$material['title'],'category_id'=>(int)$material['category_id'],
                            'material_type'=>$material['material_type'],'description'=>$material['description'],
                            'full_description'=>$material['full_description'],'publisher'=>$material['publisher'],
                            'publication_date'=>$material['publication_date_iso'],'keywords'=>implode(', ',$material['keywords']),
                            'files'=>array_map(static fn(array $file):array=>[
                                'id'=>$file['id'],'name'=>$file['name'],'format'=>$file['format'],
                                'size'=>$file['size'],'primary'=>$file['primary'],
                            ],$material['files']),
                        ], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ar-empty" id="arMaterialsEmpty" <?= $supportMaterials ? 'hidden' : '' ?>>
                <span><i class="fa-solid fa-folder-open"></i></span>
                <h2>Aún no existen materiales de apoyo.</h2>
                <p>Los recursos institucionales publicados aparecerán en esta sección.</p>
            </div>
            <footer class="ar-pagination" data-pagination-for="materials" hidden>
                <span data-pagination-summary>Mostrando 0 de 0</span>
                <label>Mostrar
                    <select data-page-size>
                        <option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="75">75</option><option value="100">100</option>
                    </select>
                </label>
                <nav data-pagination-pages aria-label="Paginación de materiales de apoyo"></nav>
            </footer>
        </section>
    </section>

    <div class="ar-withdrawn-modal" id="arWithdrawnModal" hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="arWithdrawnTitle">
            <header>
                <div><span>Contenido conservado</span><h2 id="arWithdrawnTitle">Publicaciones retiradas</h2><p>Proyectos que dejaron de mostrarse en el repositorio y pueden restaurarse.</p></div>
                <button type="button" data-close-withdrawn aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="ar-withdrawn-list">
                <?php if (!$withdrawnPublications && !$withdrawnMaterials): ?>
                    <div class="ar-withdrawn-empty"><i class="fa-regular fa-circle-check"></i><strong>No hay publicaciones retiradas</strong><p>Los proyectos retirados aparecerán aquí.</p></div>
                <?php else: ?>
                    <?php if ($withdrawnPublications): ?><h3 class="ar-withdrawn-group-title">Proyectos</h3><?php endif; ?>
                    <?php foreach ($withdrawnPublications as $project): ?>
                        <article>
                            <div><span><?= e($project['code']) ?> · <?= e($project['type_name']) ?></span><h3><?= e($project['title']) ?></h3><p><?= e($project['period_name']) ?> · <?= (int) $project['file_count'] ?> documentos</p></div>
                            <small>Retirado el <?= e($formatDate($project['withdrawn_at'])) ?></small>
                            <button type="button" data-restore-publication data-id="<?= (int) $project['id'] ?>"><i class="fa-solid fa-rotate-left"></i> Restaurar publicación</button>
                        </article>
                    <?php endforeach; ?>
                    <?php if ($withdrawnMaterials): ?><h3 class="ar-withdrawn-group-title">Material de apoyo</h3><?php endif; ?>
                    <?php foreach ($withdrawnMaterials as $material): ?>
                        <article>
                            <div><span><?= e($material['type']) ?> · <?= e($material['category_label']) ?></span><h3><?= e($material['title']) ?></h3><p><?= (int) $material['files_count'] ?> archivos · <?= number_format((int)$material['downloads'],0,',','.') ?> descargas</p></div>
                            <small>Retirado el <?= e($formatDate($material['withdrawn_at'])) ?></small>
                            <button type="button" data-restore-material data-id="<?= (int) $material['id'] ?>"><i class="fa-solid fa-rotate-left"></i> Restaurar material</button>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="ar-material-modal" id="arMaterialEditModal" hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="arMaterialEditTitle">
            <header><div><span>Material de apoyo</span><h2 id="arMaterialEditTitle">Editar material</h2><p>Actualiza la información visible en el repositorio.</p></div><button type="button" data-close-material-edit aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header>
            <form id="arMaterialEditForm">
                <input type="hidden" name="_csrf" value="<?= e($repositoryCsrf) ?>">
                <input type="hidden" name="id">
                <div class="ar-material-form-grid">
                    <label class="wide">Título<input name="title" maxlength="220" required></label>
                    <label>Categoría<select name="category_id" required><?php foreach($materialCategories as $category):?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach;?></select></label>
                    <label>Tipo de material<input name="material_type" maxlength="100" required></label>
                    <label class="wide">Descripción corta<textarea name="description" maxlength="500" rows="2" required></textarea></label>
                    <label class="wide">Descripción completa<textarea name="full_description" rows="5" required></textarea></label>
                    <label>Responsable<input name="publisher" maxlength="180" required></label>
                    <label>Fecha de publicación<input name="publication_date" type="date" required></label>
                    <label class="wide">Palabras clave<input name="keywords" placeholder="Separadas por comas"></label>
                </div>
                <footer><button type="button" data-close-material-edit>Cancelar</button><button type="submit">Guardar cambios</button></footer>
            </form>
        </section>
    </div>

    <div class="ar-material-modal" id="arMaterialFilesModal" hidden>
        <section role="dialog" aria-modal="true" aria-labelledby="arMaterialFilesTitle">
            <header><div><span>Documentos</span><h2 id="arMaterialFilesTitle">Gestionar archivos</h2><p id="arMaterialFilesSubtitle"></p></div><button type="button" data-close-material-files aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></header>
            <div class="ar-material-files-list" id="arMaterialFilesList"></div>
            <form id="arMaterialFileForm" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= e($repositoryCsrf) ?>">
                <input type="hidden" name="material_id">
                <input type="hidden" name="action" value="add">
                <label>Agregar archivo<input name="file" type="file" accept=".pdf,.docx,.xlsx,.pptx,.png,.jpg,.jpeg,.webp,.txt,.zip" required></label>
                <label class="ar-primary-check"><input name="is_primary" type="checkbox" value="1"> Usar como archivo principal</label>
                <footer><button type="button" data-close-material-files>Cerrar</button><button type="submit">Agregar archivo</button></footer>
            </form>
        </section>
    </div>

    <div class="ar-confirm" id="arConfirm" hidden>
        <div role="alertdialog" aria-modal="true" aria-labelledby="arConfirmTitle" aria-describedby="arConfirmText">
            <span><i class="fa-solid fa-triangle-exclamation"></i></span>
            <h2 id="arConfirmTitle">Confirmar acción</h2>
            <p id="arConfirmText"></p>
            <div>
                <button type="button" data-confirm-cancel>Cancelar</button>
                <button type="button" class="danger" data-confirm-accept>Retirar proyecto</button>
            </div>
        </div>
    </div>
    <div class="ar-toast" id="arToast" role="status" aria-live="polite" hidden><i class="fa-solid fa-circle-check"></i><span></span></div>
    <div class="ar-tooltip" id="arTooltip" role="tooltip" hidden></div>
    <div id="arConfig" data-endpoint="<?= e($repositoryPublishEndpoint) ?>" data-material-save="<?= e($materialSaveEndpoint) ?>" data-material-status="<?= e($materialStatusEndpoint) ?>" data-material-file="<?= e($materialFileEndpoint) ?>" data-csrf="<?= e($repositoryCsrf) ?>"></div>
</div>
